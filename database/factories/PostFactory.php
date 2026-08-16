<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Models\Post;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'project_id' => Project::factory(),
            'workspace_id' => function (array $attributes): int {
                return Project::query()->withoutGlobalScopes()->findOrFail($attributes['project_id'])->workspace_id;
            },
            'type' => PostType::Announcement,
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(12),
            'markdown' => '# '.$title."\n\n".fake()->paragraph(),
            'html' => '<p>'.fake()->paragraph().'</p>',
            'status' => PostStatus::Draft,
            'published_at' => null,
            'position' => 0,
        ];
    }

    public function announcement(): static
    {
        return $this->state(fn (): array => ['type' => PostType::Announcement]);
    }

    public function changelog(): static
    {
        return $this->state(fn (): array => ['type' => PostType::Changelog]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);
    }
}
