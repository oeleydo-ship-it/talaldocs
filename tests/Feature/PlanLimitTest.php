<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_plan_cannot_create_a_second_project(): void
    {
        $owner = User::factory()->onboarded()->create();

        $this->assertSame(1, Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->count());

        $this->actingAs($owner)
            ->post(route('projects.store'), [
                'name' => 'Second site',
                'subdomain' => 'second-site-xyz',
            ])
            ->assertSessionHasErrors('plan');
    }
}
