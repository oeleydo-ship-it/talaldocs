<?php

namespace App\Models;

use App\Enums\DocsLayout;
use App\Enums\DocsTemplate;
use App\Enums\DomainStatus;
use App\Enums\ProjectVisibility;
use App\Models\Concerns\BelongsToWorkspace;
use App\Support\PublicProjectResolver;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property string $slug
 * @property string $subdomain
 * @property ProjectVisibility $visibility
 * @property bool $use_path_urls
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Workspace $workspace
 */
#[Fillable([
    'workspace_id',
    'name',
    'slug',
    'subdomain',
    'visibility',
    'use_path_urls',
    'password',
    'logo_path',
    'favicon_path',
    'og_image_path',
    'primary_color',
    'accent_color',
    'font_family',
    'heading_font',
    'docs_template',
    'docs_layout',
    'github_edit_url',
    'header_links',
    'ai_indexed_at',
    'default_language_id',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'docs_template' => 'classic',
        'docs_layout' => 'centered',
        'primary_color' => '#0f172a',
        'accent_color' => '#2563eb',
        'font_family' => 'Inter',
        'heading_font' => 'Inter',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => ProjectVisibility::class,
            'docs_template' => DocsTemplate::class,
            'docs_layout' => DocsLayout::class,
            'use_path_urls' => 'boolean',
            'password' => 'hashed',
            'ai_indexed_at' => 'datetime',
            'header_links' => 'array',
        ];
    }

    /**
     * @return HasMany<DocumentationVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentationVersion::class);
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /**
     * @return HasMany<CustomDomain, $this>
     */
    public function customDomains(): HasMany
    {
        return $this->hasMany(CustomDomain::class);
    }

    /**
     * @return HasMany<ProjectLanguage, $this>
     */
    public function projectLanguages(): HasMany
    {
        return $this->hasMany(ProjectLanguage::class);
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Globally unique segment for public documentation URL paths.
     */
    public function publicPathKey(): string
    {
        return $this->subdomain;
    }

    public function docsBasePath(): string
    {
        return '/docs/'.$this->publicPathKey();
    }

    /**
     * Path prefix for public docs links on the current request.
     * Empty on verified custom hosts so pages live at `/latest/en/{slug}`.
     */
    public function visitorDocsBasePath(?Request $request = null): string
    {
        try {
            $request ??= request();
        } catch (\Throwable) {
            return $this->docsBasePath();
        }

        if (! $request instanceof Request) {
            return $this->docsBasePath();
        }

        if (app(PublicProjectResolver::class)->servesProjectAtCustomHost($request, $this)) {
            return '';
        }

        return $this->docsBasePath();
    }

    public function publicUrl(?string $platformDomain = null): string
    {
        $platformDomain ??= (string) config('anytdocs.domain');
        $appUrl = rtrim((string) config('app.url'), '/');
        $isLocalHost = str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1');

        if ($this->use_path_urls || $isLocalHost) {
            return $appUrl.$this->docsBasePath();
        }

        $primaryDomain = $this->customDomains()
            ->where('is_primary', true)
            ->where('status', DomainStatus::Active)
            ->value('hostname');

        if (filled($primaryDomain)) {
            return 'https://'.strtolower((string) $primaryDomain);
        }

        return 'https://'.$this->subdomain.'.'.$platformDomain;
    }

    public function brandingAssetUrl(?string $path): ?string
    {
        return filled($path) ? '/storage/'.$path : null;
    }

    public function brandingAssetAbsoluteUrl(?string $path): ?string
    {
        $relative = $this->brandingAssetUrl($path);

        return $relative ? url($relative) : null;
    }
}
