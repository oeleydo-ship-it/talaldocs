<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectHeaderLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_save_header_links_in_settings(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $links = [
            ['label' => 'Dashboard', 'url' => 'https://example.com/dashboard'],
            ['label' => 'Changelog', 'url' => '/changelog'],
        ];

        $this->actingAs($owner)
            ->from(route('projects.settings', $project))
            ->post(route('projects.header-links', $project), [
                'header_links' => $links,
            ])
            ->assertRedirect(route('projects.settings', $project));

        $project->refresh();

        $this->assertSame($links, $project->header_links);
    }

    public function test_empty_array_clears_header_links(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $project->forceFill([
            'header_links' => [
                ['label' => 'API', 'url' => 'https://example.com/api'],
            ],
        ])->save();

        $this->actingAs($owner)
            ->post(route('projects.header-links', $project), [
                'header_links' => [],
            ])
            ->assertRedirect();

        $project->refresh();

        $this->assertNull($project->header_links);
    }

    public function test_invalid_header_link_url_is_rejected(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $this->actingAs($owner)
            ->from(route('projects.settings', $project))
            ->post(route('projects.header-links', $project), [
                'header_links' => [
                    ['label' => 'Bad link', 'url' => 'ftp://example.com'],
                ],
            ])
            ->assertSessionHasErrors('header_links.0.url');

        $project->refresh();

        $this->assertNull($project->header_links);
    }

    public function test_settings_page_includes_header_links(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $links = [
            ['label' => 'Dashboard', 'url' => 'https://example.com'],
        ];

        $project->forceFill(['header_links' => $links])->save();

        $this->actingAs($owner)
            ->get(route('projects.settings', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/settings')
                ->where('project.header_links', $links)
            );
    }

    public function test_public_docs_page_includes_header_links_in_props(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $links = [
            ['label' => 'API', 'url' => 'https://api.example.com'],
            ['label' => 'Status', 'url' => '/status'],
        ];

        $project->forceFill(['header_links' => $links])->save();

        $page = Page::factory()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'title' => 'Header links page',
            'slug' => 'header-links-page',
            'published_html' => '<p>Content</p>',
        ]);

        $this->get($project->docsBasePath().'/latest/en/'.$page->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $assert) => $assert
                ->component('docs/show')
                ->where('headerLinks', $links)
            );
    }
}
