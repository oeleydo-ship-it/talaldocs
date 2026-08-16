<?php

namespace Database\Seeders;

use App\Enums\PageStatus;
use App\Models\DocumentationVersion;
use App\Models\Language;
use App\Models\Page;
use App\Models\Project;
use App\Models\ProjectLanguage;
use App\Models\Scopes\WorkspaceScope;
use App\Models\User;
use App\Support\Markdown;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            LanguageSeeder::class,
        ]);

        if (! User::query()->where('email', 'test@example.com')->exists()) {
            User::factory()->onboarded()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'company_name' => 'Acme',
            ]);
        }

        User::query()->updateOrCreate(
            ['email' => 'admin@anytdocs.test'],
            [
                'name' => 'Platform Admin',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_platform_admin' => true,
            ],
        );

        $existing = User::query()->where('email', 'pinoycurl@gmail.com')->first();

        if ($existing) {
            $existing->forceFill(['email_verified_at' => $existing->email_verified_at ?? now()])->save();
            $this->ensurePublishedWelcome($existing);
        }
    }

    private function ensurePublishedWelcome(User $user): void
    {
        if ($user->current_workspace_id === null) {
            return;
        }

        $project = Project::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('workspace_id', $user->current_workspace_id)
            ->first();

        if ($project === null) {
            return;
        }

        $language = Language::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true]);
        $version = DocumentationVersion::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $project->id)
            ->first();

        if ($version === null) {
            return;
        }

        ProjectLanguage::query()->firstOrCreate([
            'project_id' => $project->id,
            'language_id' => $language->id,
        ], [
            'workspace_id' => $project->workspace_id,
            'is_default' => true,
        ]);

        if (Page::query()->withoutGlobalScope(WorkspaceScope::class)->where('project_id', $project->id)->exists()) {
            return;
        }

        $intro = "# Welcome\n\nYour documentation is ready to edit and publish.";

        Page::query()->create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'documentation_version_id' => $version->id,
            'language_id' => $language->id,
            'title' => 'Welcome',
            'slug' => 'welcome',
            'markdown' => $intro,
            'html' => app(Markdown::class)->render($intro, $project->workspace_id),
            'published_markdown' => $intro,
            'published_html' => app(Markdown::class)->render($intro, $project->workspace_id),
            'status' => PageStatus::Published,
            'published_at' => now(),
        ]);
    }
}
