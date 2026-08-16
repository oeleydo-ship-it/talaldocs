<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_does_not_include_another_workspace_project(): void
    {
        $ownerA = User::factory()->onboarded()->create();
        $projectA = Project::query()->withoutGlobalScopes()->where('workspace_id', $ownerA->current_workspace_id)->first();

        $ownerB = User::factory()->onboarded()->create();

        $this->actingAs($ownerB)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee($projectA->subdomain)
            ->assertSee(Project::query()->withoutGlobalScopes()->where('workspace_id', $ownerB->current_workspace_id)->value('subdomain'));
    }

    public function test_a_member_cannot_open_another_workspace_project(): void
    {
        $ownerA = User::factory()->onboarded()->create();
        $projectA = Project::query()->withoutGlobalScopes()->where('workspace_id', $ownerA->current_workspace_id)->first();

        $ownerB = User::factory()->onboarded()->create();

        $this->actingAs($ownerB)
            ->get(route('projects.show', $projectA))
            ->assertNotFound();
    }
}
