<?php

namespace Database\Factories;

use App\Enums\PageStatus;
use App\Models\DocumentationVersion;
use App\Models\Language;
use App\Models\Page;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'project_id' => Project::factory(),
            'workspace_id' => function (array $attributes): int {
                return Project::query()->withoutGlobalScopes()->findOrFail($attributes['project_id'])->workspace_id;
            },
            'documentation_version_id' => function (array $attributes): int {
                $project = Project::query()->withoutGlobalScopes()->findOrFail($attributes['project_id']);

                return DocumentationVersion::query()
                    ->withoutGlobalScopes()
                    ->where('project_id', $project->id)
                    ->value('id')
                    ?? DocumentationVersion::factory()->create([
                        'project_id' => $project->id,
                        'workspace_id' => $project->workspace_id,
                    ])->id;
            },
            'language_id' => fn (): int => Language::query()->firstOrCreate(
                ['code' => 'en'],
                ['name' => 'English', 'is_default' => true],
            )->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('##'),
            'markdown' => '# '.$title."\n\n".fake()->paragraph(),
            'html' => '<h1>'.$title.'</h1>',
            'status' => PageStatus::Draft,
            'position' => 0,
            'hidden' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'status' => PageStatus::Published,
                'published_markdown' => $attributes['markdown'] ?? '# Doc',
                'published_html' => $attributes['html'] ?? '<h1>Doc</h1>',
                'published_at' => now(),
            ];
        });
    }
}
