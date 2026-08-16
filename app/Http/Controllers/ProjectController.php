<?php

namespace App\Http\Controllers;

use App\Enums\PageStatus;
use App\Enums\ProjectVisibility;
use App\Http\Controllers\Concerns\ResolvesWorkspaceProject;
use App\Http\Controllers\AiDocumentationController;
use App\Models\DocumentationVersion;
use App\Models\Language;
use App\Models\Page;
use App\Models\Project;
use App\Models\ProjectLanguage;
use App\Services\CloudflareService;
use App\Support\Audit;
use App\Support\Markdown;
use App\Support\PlanGate;
use App\Support\Subdomain;
use App\Support\WelcomePageContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use ResolvesWorkspaceProject;

    public function __construct(
        private PlanGate $plans,
        private Audit $audit,
        private CloudflareService $cloudflare,
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $request->user()?->currentWorkspace;

        abort_unless($workspace !== null, 403);

        $projects = Project::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'subdomain' => $project->subdomain,
                'visibility' => $project->visibility->value,
                'public_url' => $project->publicUrl((string) config('anytdocs.domain')),
                'created_at' => $project->created_at?->toIso8601String(),
            ]);

        return Inertia::render('projects/index', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
            ],
            'projects' => $projects,
            'canCreate' => $this->plans->canCreateProject($workspace),
            'projectLimit' => $this->plans->limit($workspace, 'projects'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request);
        $this->authorize('update', $workspace);
        $this->plans->assertCanCreateProject($workspace);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'subdomain' => Subdomain::rules(),
        ]);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'subdomain' => Subdomain::normalize($data['subdomain']),
            'visibility' => ProjectVisibility::Public,
        ]);

        $language = Language::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true]);
        $version = DocumentationVersion::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'name' => 'Latest',
            'slug' => 'latest',
            'is_default' => true,
        ]);
        ProjectLanguage::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'language_id' => $language->id,
            'is_default' => true,
        ]);

        $intro = WelcomePageContent::markdown();
        Page::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'documentation_version_id' => $version->id,
            'language_id' => $language->id,
            'title' => 'Welcome',
            'subtitle' => WelcomePageContent::SUBTITLE,
            'slug' => 'welcome',
            'markdown' => $intro,
            'html' => app(Markdown::class)->render($intro, $workspace->id),
            'published_markdown' => $intro,
            'published_html' => app(Markdown::class)->render($intro, $workspace->id),
            'status' => PageStatus::Published,
            'published_at' => now(),
        ]);

        try {
            $this->cloudflare->provisionTenantSubdomain($project);
        } catch (\Throwable) {
            // DNS provisioning is optional; project creation should still succeed.
        }

        return redirect()->route('projects.editor', $project);
    }

    public function show(Request $request, int $project): Response
    {
        $model = Project::query()
            ->where('workspace_id', $request->user()->current_workspace_id)
            ->findOrFail($project);

        $this->authorize('view', $model);

        return Inertia::render('projects/show', [
            'project' => [
                'id' => $model->id,
                'name' => $model->name,
                'slug' => $model->slug,
                'subdomain' => $model->subdomain,
                'visibility' => $model->visibility->value,
                'public_url' => $model->publicUrl((string) config('anytdocs.domain')),
                'pages_count' => $model->pages()->count(),
                'published_pages_count' => $model->pages()->where('status', PageStatus::Published)->count(),
                'versions_count' => $model->versions()->count(),
                'languages_count' => ProjectLanguage::query()->where('project_id', $model->id)->count(),
            ],
            'appDomain' => config('anytdocs.domain'),
            'ai' => AiDocumentationController::aiMetaForProject($model),
        ]);
    }

    public function update(Request $request, int $project): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('manageSettings', $model);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'subdomain' => [
                'required',
                'string',
                'min:3',
                'max:63',
                'regex:/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || Subdomain::isReserved($value)) {
                        $fail('That subdomain is reserved. Please choose another.');
                    }
                },
                Rule::unique('projects', 'subdomain')->ignore($model->id),
            ],
        ]);

        $model->update([
            'name' => $data['name'],
            'subdomain' => Subdomain::normalize($data['subdomain']),
        ]);

        $this->audit->record($model->workspace_id, 'project.updated', $request->user(), $model);

        return back();
    }

    public function destroy(Request $request, int $project): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('manageSettings', $model);

        $model->delete();
        $this->audit->record($model->workspace_id, 'project.deleted', $request->user(), $model);

        return redirect()->route('projects.index');
    }
}
