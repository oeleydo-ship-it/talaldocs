<?php

namespace Database\Factories;

use App\Models\Block;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Block>
 */
class BlockFactory extends Factory
{
    protected $model = Block::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'workspace_id' => Workspace::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'markdown' => '## '.fake()->sentence()."\n\n".fake()->paragraph(),
            'html' => '<h2>'.fake()->sentence().'</h2><p>'.fake()->paragraph().'</p>',
        ];
    }
}
