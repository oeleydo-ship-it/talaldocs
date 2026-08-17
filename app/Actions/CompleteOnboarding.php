<?php

namespace App\Actions;

use App\Enums\AiGenerationJobStatus;
use App\Enums\PageStatus;
use App\Enums\ProjectVisibility;
use App\Enums\WorkspaceRole;
use App\Models\AiGenerationJob;
use App\Models\DocumentationVersion;
use App\Models\Language;
use App\Models\Page;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectLanguage;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AiDocumentationGenerator;
use App\Support\AiGenerationJobDispatcher;
use App\Support\BillingAccess;
use App\Support\Markdown;
use App\Support\PlanGate;
use App\Support\Subdomain;
use App\Support\UrlSafetyValidator;
use App\Support\WelcomePageContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteOnboarding
{
    /**
     * @param  array{
     *     name: string,
     *     company_name: string,
     *     workspace_name: string,
     *     subdomain: string,
     *     website_url?: string|null,
     *     product_description?: string|null,
     *     publish_ai_pages?: bool|null
     * }  $data
     */
    public function handle(User $user, array $data): Workspace
    {
        return DB::transaction(function () use ($user, $data): Workspace {
            $plan = Plan::query()->firstOrCreate(
                ['slug' => 'free'],
                [
                    'name' => 'Free',
                    'price_cents' => 0,
                    'limits' => [
                        'projects' => 1,
                        'members' => 3,
                        'custom_domains' => 0,
                    ],
                    'features' => [
                        'custom_domain' => false,
                        'advanced_branding' => false,
                        'analytics' => false,
                        'versioning' => false,
                        'localization' => false,
                        'audit_log' => false,
                        'sso' => false,
                        'ai_generation' => false,
                    ],
                    'is_active' => true,
                ],
            );

            Language::query()->firstOrCreate(
                ['code' => 'en'],
                ['name' => 'English', 'is_default' => true],
            );

            $workspace = Workspace::query()->create([
                'name' => $data['workspace_name'],
                'slug' => Subdomain::uniqueSlug($data['workspace_name']),
                'company_name' => $data['company_name'],
                'plan_id' => $plan->id,
            ]);

            app(BillingAccess::class)->startLocalTrialIfEligible($workspace);

            $workspace->members()->create([
                'user_id' => $user->id,
                'role' => WorkspaceRole::Owner,
            ]);

            $subdomain = Subdomain::normalize($data['subdomain']);

            $project = Project::query()->create([
                'workspace_id' => $workspace->id,
                'name' => 'Getting started',
                'slug' => 'getting-started',
                'subdomain' => $subdomain,
                'visibility' => ProjectVisibility::Public,
                'use_path_urls' => false,
            ]);

            $language = Language::query()->firstOrCreate(
                ['code' => 'en'],
                ['name' => 'English', 'is_default' => true],
            );

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

            $project->forceFill(['default_language_id' => $language->id])->save();

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
                'position' => 0,
            ]);

            $user->forceFill([
                'name' => $data['name'],
                'company_name' => $data['company_name'],
                'current_workspace_id' => $workspace->id,
                'onboarded_at' => now(),
            ])->save();

            $this->maybeQueueWebsiteImport($workspace, $project, $user, $data);

            return $workspace->refresh();
        });
    }

    /**
     * @param  array{
     *     website_url?: string|null,
     *     product_description?: string|null,
     *     publish_ai_pages?: bool|null
     * }  $data
     */
    private function maybeQueueWebsiteImport(Workspace $workspace, Project $project, User $user, array $data): void
    {
        $websiteUrl = trim((string) ($data['website_url'] ?? ''));

        if ($websiteUrl === '' || ! AiDocumentationGenerator::isConfigured()) {
            return;
        }

        if (! app(PlanGate::class)->canUseAiGeneration($workspace)) {
            return;
        }

        try {
            app(UrlSafetyValidator::class)->assertSafe($websiteUrl);
        } catch (ValidationException) {
            return;
        }

        $job = AiGenerationJob::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'source_url' => $websiteUrl,
            'product_description' => filled($data['product_description'] ?? null)
                ? (string) $data['product_description']
                : null,
            'publish_immediately' => (bool) ($data['publish_ai_pages'] ?? false),
            'status' => AiGenerationJobStatus::Pending,
        ]);

        AiGenerationJobDispatcher::dispatch($job->id);
    }
}
