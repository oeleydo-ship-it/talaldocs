<?php

namespace Tests\Feature;

use App\Enums\DocsLayout;
use App\Enums\DocsTemplate;
use App\Enums\PageStatus;
use App\Enums\ProjectVisibility;
use App\Models\DocumentationVersion;
use App\Models\Language;
use App\Models\Page;
use App\Models\Project;
use App\Models\ProjectLanguage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_nested_pages_appear_in_public_nav_tree(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();
        $parent = Page::factory()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'title' => 'Getting started',
            'slug' => 'getting-started',
        ]);

        Page::factory()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'documentation_version_id' => $parent->documentation_version_id,
            'language_id' => $parent->language_id,
            'parent_id' => $parent->id,
            'title' => 'Nested guide',
            'slug' => 'nested-guide',
        ]);

        $this->get($project->docsBasePath().'/latest/en/'.$parent->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docs/show')
                ->where('navTree.0.slug', $parent->slug)
                ->where('navTree.0.children.0.slug', 'nested-guide')
                ->where('navTree.0.children.0.title', 'Nested guide')
            );
    }

    public function test_gitbook_template_renders_without_error(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();
        $project->forceFill(['docs_template' => DocsTemplate::Gitbook])->save();

        $page = Page::factory()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'title' => 'Welcome',
            'subtitle' => 'Get started quickly',
            'slug' => 'welcome',
            'published_html' => '<h1>Welcome</h1><ol><li>Step one</li><li>Step two</li></ol>',
        ]);

        $this->get($project->docsBasePath().'/latest/en/'.$page->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docs/show')
                ->where('template', 'gitbook')
                ->where('docsLayout', 'centered')
                ->where('directoryUrl', $project->docsBasePath().'/directory')
                ->where('announcementsUrl', $project->docsBasePath().'/announcements')
                ->where('changelogUrl', $project->docsBasePath().'/changelog')
                ->has('docsHomeUrl')
                ->where('page.title', 'Welcome')
                ->where('page.subtitle', 'Get started quickly')
                ->has('sidebarGroups')
                ->has('navTree')
                ->has('breadcrumbs')
                ->where('toc', [])
            );
    }

    public function test_public_docs_table_of_contents_includes_section_headings(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $page = Page::factory()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'title' => 'Setup guide',
            'slug' => 'setup-guide',
            'published_html' => '<h2>Install</h2><p>Run the installer.</p><h3>Requirements</h3><p>Node 20+</p>',
        ]);

        $this->get($project->docsBasePath().'/latest/en/'.$page->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docs/show')
                ->has('toc', 2)
                ->where('toc.0.text', 'Install')
                ->where('toc.0.level', 2)
                ->where('toc.1.text', 'Requirements')
                ->where('toc.1.level', 3)
            );
    }

    public function test_gitbook_wide_layout_is_passed_to_docs_page(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();
        $project->forceFill([
            'docs_template' => DocsTemplate::Gitbook,
            'docs_layout' => DocsLayout::Wide,
        ])->save();

        $page = Page::factory()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'slug' => 'welcome',
        ]);

        $this->get($project->docsBasePath().'/latest/en/'.$page->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docs/show')
                ->where('template', 'gitbook')
                ->where('docsLayout', 'wide')
            );
    }

    public function test_published_public_docs_are_visible_and_private_docs_are_not(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();
        $page = Page::factory()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'title' => 'Getting started',
            'slug' => 'getting-started',
            'published_html' => '<h1>Getting started</h1><h2>Install</h2>',
        ]);

        $this->get($project->docsBasePath().'/latest/en/'.$page->slug)
            ->assertOk()
            ->assertSee('Getting started');

        $project->forceFill(['visibility' => ProjectVisibility::Private])->save();

        $this->get($project->docsBasePath().'/latest/en/'.$page->slug)
            ->assertForbidden();
    }

    public function test_public_docs_show_the_requested_page_not_the_first_page(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        Page::factory()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'title' => 'Welcome',
            'slug' => 'welcome',
            'published_html' => '<p>Welcome to the docs</p>',
            'position' => 0,
        ]);

        $install = Page::factory()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'title' => 'How to install',
            'slug' => 'installation',
            'published_html' => '<p>Run the installer package</p>',
            'position' => 1,
        ]);

        $this->get($project->docsBasePath().'/latest/en/'.$install->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docs/show')
                ->where('page.title', 'How to install')
                ->where('page.slug', 'installation')
            )
            ->assertDontSee('Welcome to the docs');
    }

    public function test_public_docs_feedback_creates_feedback_row(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();
        $page = Page::factory()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'slug' => 'intro',
        ]);

        $this->from($project->docsBasePath().'/latest/en/'.$page->slug)
            ->post(route('docs.feedback', $project->subdomain), [
                'page_id' => $page->id,
                'helpful' => false,
                'comment' => 'Needs more examples',
            ])
            ->assertRedirect($project->docsBasePath().'/latest/en/'.$page->slug);

        $this->assertDatabaseHas('feedback', [
            'project_id' => $project->id,
            'page_id' => $page->id,
            'helpful' => 0,
            'comment' => 'Needs more examples',
        ]);
    }

    public function test_sitemap_is_available_for_public_projects(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();
        Page::factory()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'slug' => 'intro',
        ]);

        $this->get(route('docs.sitemap', $project->subdomain))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml');
    }

    public function test_projects_in_different_workspaces_have_unique_public_urls_and_content(): void
    {
        Language::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true]);

        $projectA = $this->createOnboardingStyleProject('platform-admin', 'Platform A docs');
        $projectB = $this->createOnboardingStyleProject('testing', 'Uplary docs');

        $this->assertSame('getting-started', $projectA->slug);
        $this->assertSame('getting-started', $projectB->slug);
        $this->assertNotSame($projectA->publicUrl(), $projectB->publicUrl());
        $this->assertStringContainsString('/docs/platform-admin', $projectA->publicUrl());
        $this->assertStringContainsString('/docs/testing', $projectB->publicUrl());

        $this->get($projectA->docsBasePath().'/latest/en/welcome')
            ->assertOk()
            ->assertSee('Platform A docs');

        $this->get($projectB->docsBasePath().'/latest/en/welcome')
            ->assertOk()
            ->assertSee('Uplary docs')
            ->assertDontSee('Platform A docs');
    }

    private function createOnboardingStyleProject(string $subdomain, string $welcomeTitle): Project
    {
        $workspace = Workspace::factory()->create();
        $language = Language::query()->where('code', 'en')->firstOrFail();

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Getting started',
            'slug' => 'getting-started',
            'subdomain' => $subdomain,
            'visibility' => ProjectVisibility::Public,
            'use_path_urls' => false,
        ]);

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

        Page::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'documentation_version_id' => $version->id,
            'language_id' => $language->id,
            'title' => $welcomeTitle,
            'slug' => 'welcome',
            'markdown' => "# {$welcomeTitle}",
            'html' => "<h1>{$welcomeTitle}</h1>",
            'published_markdown' => "# {$welcomeTitle}",
            'published_html' => "<h1>{$welcomeTitle}</h1>",
            'status' => PageStatus::Published,
            'published_at' => now(),
            'position' => 0,
        ]);

        return $project->refresh();
    }

    public function test_public_docs_search_returns_matching_pages(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $version = DocumentationVersion::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $project->id)
            ->firstOrFail();

        $language = Language::query()->where('code', 'en')->firstOrFail();

        Page::factory()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'documentation_version_id' => $version->id,
            'language_id' => $language->id,
            'title' => 'Install the CLI',
            'slug' => 'install-cli',
            'published_markdown' => "## Install\n\nLearn how to install the CLI on your machine.",
            'published_html' => '<h2>Install</h2><p>Learn how to install the CLI on your machine.</p>',
        ]);

        $this->get($project->docsBasePath().'/search?q=install&version_id='.$version->id.'&language_id='.$language->id)
            ->assertOk()
            ->assertJsonFragment(['title' => 'Install the CLI', 'slug' => 'install-cli'])
            ->assertJsonStructure([['title', 'slug', 'excerpt']]);

        $this->get($project->docsBasePath().'/search?q=how&version_id='.$version->id.'&language_id='.$language->id)
            ->assertOk()
            ->assertJsonFragment(['title' => 'Install the CLI']);
    }

    public function test_public_docs_search_uses_subdomain_not_project_slug(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();
        $project->forceFill(['slug' => 'internal-slug', 'subdomain' => 'public-docs-key'])->save();

        $page = Page::factory()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'title' => 'Quickstart',
            'slug' => 'quickstart',
            'published_markdown' => 'How to get started quickly.',
        ]);

        $this->get('/docs/internal-slug/search?q=quick')
            ->assertNotFound();

        $this->get('/docs/public-docs-key/search?q=quick')
            ->assertOk()
            ->assertJsonFragment(['slug' => $page->slug]);
    }
}
