<?php

namespace App\Models;

use App\Enums\AiGenerationJobStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $project_id
 * @property int|null $user_id
 * @property string $source_url
 * @property string|null $product_description
 * @property AiGenerationJobStatus $status
 * @property array<string, mixed>|null $result_summary
 * @property string|null $error
 * @property bool $publish_immediately
 */
#[Fillable([
    'workspace_id',
    'project_id',
    'user_id',
    'source_url',
    'product_description',
    'status',
    'result_summary',
    'error',
    'publish_immediately',
])]
class AiGenerationJob extends Model
{
    use BelongsToWorkspace;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AiGenerationJobStatus::class,
            'result_summary' => 'array',
            'publish_immediately' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
