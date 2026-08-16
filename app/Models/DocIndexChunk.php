<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $project_id
 * @property int $page_id
 * @property int $documentation_version_id
 * @property int $language_id
 * @property string $page_title
 * @property string $page_slug
 * @property string|null $heading
 * @property string $content
 * @property int $position
 */
class DocIndexChunk extends Model
{
    use BelongsToWorkspace;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'project_id',
        'page_id',
        'documentation_version_id',
        'language_id',
        'page_title',
        'page_slug',
        'heading',
        'content',
        'position',
        'indexed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'indexed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
