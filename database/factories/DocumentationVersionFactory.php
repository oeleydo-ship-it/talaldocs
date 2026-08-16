<?php

namespace Database\Factories;

use App\Models\DocumentationVersion;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentationVersion>
 */
class DocumentationVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'workspace_id' => function (array $attributes): int {
                $project = Project::query()->withoutGlobalScopes()->findOrFail($attributes['project_id']);

                return $project->workspace_id;
            },
            'name' => 'Latest',
            'slug' => 'latest',
            'is_default' => true,
        ];
    }
}
