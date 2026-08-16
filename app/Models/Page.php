<?php

namespace App\Models;

use App\Enums\PageStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $project_id
 * @property int $documentation_version_id
 * @property int $language_id
 * @property int|null $parent_id
 * @property string $title
 * @property string $slug
 * @property string|null $markdown
 * @property string|null $html
 * @property string|null $published_markdown
 * @property string|null $published_html
 * @property PageStatus $status
 * @property Carbon|null $published_at
 * @property int $position
 * @property bool $hidden
 */
#[Fillable([
    'workspace_id',
    'project_id',
    'documentation_version_id',
    'language_id',
    'parent_id',
    'title',
    'subtitle',
    'slug',
    'markdown',
    'html',
    'published_markdown',
    'published_html',
    'status',
    'published_at',
    'position',
    'hidden',
])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'hidden' => false,
        'status' => 'draft',
        'position' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'published_at' => 'datetime',
            'hidden' => 'boolean',
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

    /**
     * @return BelongsTo<DocumentationVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentationVersion::class, 'documentation_version_id');
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    /**
     * @return HasMany<PageRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->latest('id');
    }

    public function isPublished(): bool
    {
        return $this->status === PageStatus::Published && $this->published_html !== null;
    }
}
