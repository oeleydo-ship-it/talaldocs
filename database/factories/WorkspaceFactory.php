<?php

namespace Database\Factories;

use App\Enums\WorkspaceRole;
use App\Models\DocumentationVersion;
use App\Models\Language;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'company_name' => $name,
            'plan_id' => fn (): int => Plan::query()->firstOrCreate(
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
                    ],
                    'is_active' => true,
                ],
            )->id,
        ];
    }

    public function ownedBy(User $user): static
    {
        return $this->afterCreating(function (Workspace $workspace) use ($user): void {
            $workspace->members()->create([
                'user_id' => $user->id,
                'role' => WorkspaceRole::Owner,
            ]);

            $user->forceFill([
                'current_workspace_id' => $workspace->id,
                'onboarded_at' => $user->onboarded_at ?? now(),
            ])->save();
        });
    }

    public function withProject(): static
    {
        return $this->afterCreating(function (Workspace $workspace): void {
            Language::query()->firstOrCreate(
                ['code' => 'en'],
                ['name' => 'English', 'is_default' => true],
            );

            $project = Project::factory()->create([
                'workspace_id' => $workspace->id,
            ]);

            DocumentationVersion::factory()->create([
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
            ]);
        });
    }
}
