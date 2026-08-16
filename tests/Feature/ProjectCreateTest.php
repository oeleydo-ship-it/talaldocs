<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_project_when_plan_allows(): void
    {
        $owner = User::factory()->onboarded()->create();
        $workspace = $owner->currentWorkspace;

        $proPlan = Plan::query()->firstOrCreate(
            ['slug' => 'pro-test'],
            [
                'name' => 'Pro Test',
                'price_cents' => 2900,
                'limits' => ['projects' => 5, 'members' => 10, 'custom_domains' => 1],
                'features' => ['custom_domain' => true],
                'is_active' => true,
            ],
        );

        $workspace?->forceFill(['plan_id' => $proPlan->id])->save();

        $this->assertSame(1, Project::query()->withoutGlobalScopes()->where('workspace_id', $workspace?->id)->count());

        $this->actingAs($owner)
            ->post(route('projects.store'), [
                'name' => 'Second docs',
                'subdomain' => 'second-docs-abc',
            ])
            ->assertRedirect();

        $this->assertSame(2, Project::query()->withoutGlobalScopes()->where('workspace_id', $workspace?->id)->count());
    }

    public function test_owner_can_update_project_name_and_subdomain(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $this->actingAs($owner)
            ->patch(route('projects.update', $project), [
                'name' => 'Renamed docs',
                'subdomain' => 'renamed-docs-xyz',
            ])
            ->assertRedirect();

        $project->refresh();

        $this->assertSame('Renamed docs', $project->name);
        $this->assertSame('renamed-docs-xyz', $project->subdomain);
    }
}
