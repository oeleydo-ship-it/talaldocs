<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $project_id
 * @property PostType $type
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $markdown
 * @property string|null $html
 * @property PostStatus $status
 * @property Carbon|null $published_at
 * @property int $position
 */
#[Fillable([
    'workspace_id',
    'project_id',
    'type',
    'title',
    'slug',
    'excerpt',
    'markdown',
    'html',
    'status',
    'published_at',
    'position',
])]
class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'position' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PostType::class,
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published;
    }
}
