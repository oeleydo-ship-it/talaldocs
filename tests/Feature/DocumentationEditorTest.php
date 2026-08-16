<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentationEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_editor_and_another_tenant_cannot(): void
    {
        $ownerA = User::factory()->onboarded()->create();
        $projectA = Project::query()->withoutGlobalScopes()->where('workspace_id', $ownerA->current_workspace_id)->firstOrFail();
        Page::factory()->published()->create([
            'project_id' => $projectA->id,
            'workspace_id' => $projectA->workspace_id,
        ]);

        $this->actingAs($ownerA)
            ->get(route('projects.editor', $projectA))
            ->assertOk();

        $ownerB = User::factory()->onboarded()->create();

        $this->actingAs($ownerB)
            ->get(route('projects.editor', $projectA))
            ->assertNotFound();
    }

    public function test_owner_can_publish_a_page(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();
        $page = Page::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'markdown' => "# Hello\n\nWorld",
        ]);

        $this->actingAs($owner)
            ->post(route('pages.publish', [$project, $page]))
            ->assertRedirect();

        $page->refresh();

        $this->assertSame(PageStatus::Published, $page->status);
        $this->assertNotNull($page->published_html);
    }

    public function test_owner_can_create_a_subpage_under_a_parent(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();
        $parent = Page::factory()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'title' => 'Getting started',
            'slug' => 'getting-started',
        ]);

        $this->actingAs($owner)
            ->post(route('pages.store', $project), [
                'title' => 'Nested details',
                'documentation_version_id' => $parent->documentation_version_id,
                'language_id' => $parent->language_id,
                'parent_id' => $parent->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pages', [
            'project_id' => $project->id,
            'title' => 'Nested details',
            'parent_id' => $parent->id,
        ]);
    }
}
