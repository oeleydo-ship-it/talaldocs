<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_switch_between_member_workspaces(): void
    {
        $user = User::factory()->onboarded()->create();
        $workspaceA = Workspace::query()->findOrFail($user->current_workspace_id);
        $projectA = Project::query()->withoutGlobalScopes()->where('workspace_id', $workspaceA->id)->firstOrFail();

        $workspaceB = Workspace::factory()->create(['name' => 'Beta Team']);
        WorkspaceMember::query()->create([
            'workspace_id' => $workspaceB->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Editor,
        ]);
        $projectB = Project::factory()->create(['workspace_id' => $workspaceB->id, 'name' => 'Beta Docs']);

        $this->actingAs($user)
            ->post(route('workspace.switch', $workspaceB->id))
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertSame($workspaceB->id, $user->current_workspace_id);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Beta Docs')
            ->assertDontSee($projectA->name);

        $this->actingAs($user)
            ->get(route('projects.show', $projectB->id))
            ->assertOk();
    }

    public function test_user_cannot_switch_to_a_workspace_they_do_not_belong_to(): void
    {
        $user = User::factory()->onboarded()->create();
        $other = User::factory()->onboarded()->create();
        $foreignWorkspace = Workspace::query()->findOrFail($other->current_workspace_id);

        $this->actingAs($user)
            ->post(route('workspace.switch', $foreignWorkspace->id))
            ->assertForbidden();

        $this->assertNotSame($foreignWorkspace->id, $user->fresh()->current_workspace_id);
    }
}
