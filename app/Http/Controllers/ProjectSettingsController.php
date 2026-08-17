<?php

namespace App\Http\Controllers;

use App\Enums\DocsLayout;
use App\Enums\DocsTemplate;
use App\Enums\DomainStatus;
use App\Enums\ProjectVisibility;
use App\Http\Controllers\Concerns\ResolvesWorkspaceProject;
use App\Jobs\VerifyCustomDomainJob;
use App\Models\CustomDomain;
use App\Models\DocumentationVersion;
use App\Models\Language;
use App\Models\Page;
use App\Models\ProjectLanguage;
use App\Rules\HeaderMenuUrl;
use App\Services\CloudflareService;
use App\Services\DocsIndexService;
use App\Support\Audit;
use App\Support\PlanGate;
use App\Support\PlatformCloudflareConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectSettingsController extends Controller
{
    use ResolvesWorkspaceProject;

    public function __construct(
        private PlanGate $plans,
        private Audit $audit,
        private DocsIndexService $docsIndex,
        private CloudflareService $cloudflare,
    ) {}

    public function show(Request $request, int $project): Response
    {
        $model = $this->project($request, $project);
        $workspace = $this->workspace($request);

        return Inertia::render('projects/settings', [
            'project' => [
                'id' => $model->id,
                'name' => $model->name,
                'slug' => $model->slug,
                'subdomain' => $model->subdomain,
                'visibility' => $model->visibility->value,
                'has_password' => filled($model->password),
                'use_path_urls' => $model->use_path_urls,
                'primary_color' => $model->primary_color,
                'accent_color' => $model->accent_color,
                'font_family' => $model->font_family,
                'heading_font' => $model->heading_font,
                'docs_template' => $model->docs_template->value,
                'docs_layout' => $model->docs_layout->value,
                'github_edit_url' => $model->github_edit_url,
                'header_links' => $model->header_links ?? [],
                'logo_url' => $model->brandingAssetUrl($model->logo_path),
                'favicon_url' => $model->brandingAssetUrl($model->favicon_path),
                'og_image_url' => $model->brandingAssetUrl($model->og_image_path),
                'public_url' => $model->publicUrl(),
            ],
            'cname_target' => PlatformCloudflareConfig::publicCnameTarget(),
            'domains' => $model->customDomains()->with('project')->get()->map(function (CustomDomain $domain) use ($model): array {
                $domain->setRelation('project', $model);

                return $this->domainPayload($domain);
            }),
            'versions' => $model->versions()->orderByDesc('is_default')->get(),
            'languages' => ProjectLanguage::query()->with('language')->where('project_id', $model->id)->get()
                ->map(fn (ProjectLanguage $row): array => [
                    'id' => $row->id,
                    'language_id' => $row->language_id,
                    'code' => $row->language?->code,
                    'name' => $row->language?->name,
                    'is_default' => $row->is_default,
                ]),
            'availableLanguages' => Language::query()->orderBy('name')->get(['id', 'code', 'name']),
            'features' => [
                'custom_domain' => $this->plans->hasFeature($workspace, 'custom_domain'),
                'versioning' => $this->plans->hasFeature($workspace, 'versioning'),
                'localization' => $this->plans->hasFeature($workspace, 'localization'),
                'advanced_branding' => $this->plans->hasFeature($workspace, 'advanced_branding'),
            ],
            'aiIndex' => $this->docsIndex->stats($model),
        ]);
    }

    public function visibility(Request $request, int $project): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('manageSettings', $model);

        $data = $request->validate([
            'visibility' => ['required', Rule::enum(ProjectVisibility::class)],
            'password' => ['nullable', 'string', 'min:4'],
            'use_path_urls' => ['sometimes', 'boolean'],
        ]);

        $model->visibility = ProjectVisibility::from($data['visibility']);
        $model->use_path_urls = (bool) ($data['use_path_urls'] ?? $model->use_path_urls);

        if ($model->visibility === ProjectVisibility::Password && filled($data['password'] ?? null)) {
            $model->password = $data['password'];
        }

        if ($model->visibility !== ProjectVisibility::Password) {
            $model->password = null;
        }

        $model->save();
        $this->audit->record($model->workspace_id, 'project.visibility', $request->user(), $model, [
            'visibility' => $model->visibility->value,
        ]);

        return back();
    }

    public function branding(Request $request, int $project): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('manageSettings', $model);

        $data = $request->validate([
            'primary_color' => ['required', 'string', 'max:20'],
            'accent_color' => ['required', 'string', 'max:20'],
            'font_family' => ['required', 'string', 'max:80'],
            'heading_font' => ['required', 'string', 'max:80'],
            'docs_template' => ['sometimes', Rule::enum(DocsTemplate::class)],
            'docs_layout' => ['sometimes', Rule::enum(DocsLayout::class)],
            'github_edit_url' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,ico,svg', 'max:512'],
            'og_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
        ]);

        foreach (['logo' => 'logo_path', 'favicon' => 'favicon_path', 'og_image' => 'og_image_path'] as $file => $column) {
            if ($request->hasFile($file)) {
                if (filled($model->{$column})) {
                    Storage::disk('public')->delete($model->{$column});
                }

                $model->{$column} = $request->file($file)->store('branding/'.$model->id, 'public');
            }
        }

        $model->fill([
            'primary_color' => $data['primary_color'],
            'accent_color' => $data['accent_color'],
            'font_family' => $data['font_family'],
            'heading_font' => $data['heading_font'],
            'docs_template' => isset($data['docs_template'])
                ? DocsTemplate::from($data['docs_template'])
                : $model->docs_template,
            'docs_layout' => isset($data['docs_layout'])
                ? DocsLayout::from($data['docs_layout'])
                : $model->docs_layout,
            'github_edit_url' => filled($data['github_edit_url'] ?? null)
                ? $data['github_edit_url']
                : null,
        ])->save();

        $this->audit->record($model->workspace_id, 'project.branding', $request->user(), $model);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Branding saved.'),
        ]);

        return back();
    }

    public function headerLinks(Request $request, int $project): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('manageSettings', $model);

        $data = $request->validate([
            'header_links' => ['nullable', 'array', 'max:8'],
            'header_links.*.label' => ['required', 'string', 'max:40'],
            'header_links.*.url' => ['required', 'string', 'max:500', new HeaderMenuUrl],
        ]);

        $links = collect($data['header_links'] ?? [])
            ->map(fn (array $link): array => [
                'label' => trim($link['label']),
                'url' => trim($link['url']),
            ])
            ->filter(fn (array $link): bool => $link['label'] !== '' && $link['url'] !== '')
            ->values()
            ->all();

        $model->header_links = $links === [] ? null : $links;
        $model->save();

        $this->audit->record($model->workspace_id, 'project.header_links', $request->user(), $model);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Header menu saved.'),
        ]);

        return back();
    }

    public function storeDomain(Request $request, int $project): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('manageSettings', $model);
        $this->plans->assertCanAddDomain($this->workspace($request));

        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:180', 'unique:custom_domains,hostname'],
        ]);

        $domain = CustomDomain::query()->create([
            'workspace_id' => $model->workspace_id,
            'project_id' => $model->id,
            'hostname' => strtolower($data['hostname']),
            'verification_token' => Str::lower(Str::random(32)),
            'status' => DomainStatus::Pending,
        ]);

        if ($this->cloudflare->isConfigured()) {
            try {
                $remote = $this->cloudflare->ensureCustomHostname($domain);
                $domain->applyCloudflareHostname($remote);
            } catch (\Throwable $exception) {
                $domain->forceFill([
                    'status' => DomainStatus::Failed,
                    'error_message' => $this->cloudflare->userMessage($exception),
                ])->save();
            }
        }

        VerifyCustomDomainJob::dispatch($domain->id);
        $this->audit->record($model->workspace_id, 'domain.created', $request->user(), $domain);

        return back();
    }

    public function verifyDomain(Request $request, int $project, int $domain): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('manageSettings', $model);
        $record = CustomDomain::query()->where('project_id', $model->id)->findOrFail($domain);
        VerifyCustomDomainJob::dispatchSync($record->id);

        return back();
    }

    public function primaryDomain(Request $request, int $project, int $domain): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('manageSettings', $model);
        $record = CustomDomain::query()->where('project_id', $model->id)->findOrFail($domain);
        abort_unless($record->status === DomainStatus::Active, 422);

        CustomDomain::query()->where('project_id', $model->id)->update(['is_primary' => false]);
        $record->forceFill(['is_primary' => true])->save();

        return back();
    }

    public function destroyDomain(Request $request, int $project, int $domain): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('manageSettings', $model);
        $record = CustomDomain::query()->where('project_id', $model->id)->findOrFail($domain);
        $this->cloudflare->deleteCustomHostname($record);
        $record->delete();

        return back();
    }

    public function storeVersion(Request $request, int $project): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('manageSettings', $model);
        $this->plans->assertCanAddVersion($this->workspace($request));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'copy_from' => ['nullable', 'integer'],
        ]);

        $version = DocumentationVersion::query()->create([
            'workspace_id' => $model->workspace_id,
            'project_id' => $model->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']) ?: 'v'.Str::lower(Str::random(4)),
            'is_default' => false,
        ]);

        if (! empty($data['copy_from'])) {
            $source = Page::query()
                ->where('project_id', $model->id)
                ->where('documentation_version_id', $data['copy_from'])
                ->get();

            foreach ($source as $page) {
                Page::query()->create([
                    ...$page->only([
                        'workspace_id',
                        'project_id',
                        'language_id',
                        'title',
                        'slug',
                        'subtitle',
                        'markdown',
                        'html',
                        'published_markdown',
                        'published_html',
                        'status',
                        'published_at',
                        'position',
                        'hidden',
                    ]),
                    'documentation_version_id' => $version->id,
                    'parent_id' => null,
                ]);
            }
        }

        return back();
    }

    public function defaultVersion(Request $request, int $project, int $version): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('manageSettings', $model);
        DocumentationVersion::query()->where('project_id', $model->id)->update(['is_default' => false]);
        DocumentationVersion::query()->where('project_id', $model->id)->findOrFail($version)->update(['is_default' => true]);

        return back();
    }

    public function storeLanguage(Request $request, int $project): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('manageSettings', $model);
        $this->plans->assertCanAddLanguage($this->workspace($request), $model->id);

        $data = $request->validate([
            'language_id' => ['required', 'integer', 'exists:languages,id'],
        ]);

        ProjectLanguage::query()->firstOrCreate([
            'project_id' => $model->id,
            'language_id' => $data['language_id'],
        ], [
            'workspace_id' => $model->workspace_id,
            'is_default' => ProjectLanguage::query()->where('project_id', $model->id)->doesntExist(),
        ]);

        return back();
    }

    public function defaultLanguage(Request $request, int $project, int $language): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('manageSettings', $model);
        ProjectLanguage::query()->where('project_id', $model->id)->update(['is_default' => false]);
        ProjectLanguage::query()->where('project_id', $model->id)->where('language_id', $language)->update(['is_default' => true]);
        $model->forceFill(['default_language_id' => $language])->save();

        return back();
    }

    public function reindexAiKnowledge(Request $request, int $project): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('manageSettings', $model);

        $result = $this->docsIndex->indexProject($model);

        return back()->with('toast', sprintf(
            'AI knowledge index updated: %d pages, %d chunks indexed.',
            $result['pages'],
            $result['chunks'],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function domainPayload(CustomDomain $domain): array
    {
        return [
            'id' => $domain->id,
            'hostname' => $domain->hostname,
            'verification_token' => $domain->verification_token,
            'status' => $domain->status->value,
            'is_primary' => $domain->is_primary,
            'verified_at' => $domain->verified_at?->toIso8601String(),
            'last_checked_at' => $domain->last_checked_at?->toIso8601String(),
            'error_message' => $domain->error_message,
            'ssl_status' => $domain->ssl_status,
            'ownership_txt_name' => $domain->ownership_txt_name,
            'ownership_txt_value' => $domain->ownership_txt_value,
            'ssl_txt_name' => $domain->ssl_txt_name,
            'ssl_txt_value' => $domain->ssl_txt_value,
            'cname_target' => PlatformCloudflareConfig::publicCnameTarget(),
            'tenant_cname_target' => $domain->tenantCnameTarget(),
            'cloudflare_managed' => PlatformCloudflareConfig::usesCustomHostnames() || $domain->usesCloudflare(),
            'ssl_ready' => $domain->sslReady(),
        ];
    }
}
