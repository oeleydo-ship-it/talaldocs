<?php

use App\Enums\PageStatus;
use App\Models\DocIndexChunk;
use App\Models\DocumentationVersion;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Project;
use App\Models\ProjectLanguage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/**
 * @return array{0: Project, 1: int, 2: int}
 */
function importContext(User $owner): array
{
    $project = Project::query()
        ->withoutGlobalScopes()
        ->where('workspace_id', $owner->current_workspace_id)
        ->firstOrFail();

    $version = DocumentationVersion::query()
        ->withoutGlobalScopes()
        ->where('project_id', $project->id)
        ->firstOrFail();

    $language = Language::query()->firstOrCreate(
        ['code' => 'en'],
        ['name' => 'English', 'is_default' => true],
    );

    ProjectLanguage::query()->firstOrCreate([
        'project_id' => $project->id,
        'language_id' => $language->id,
    ], [
        'workspace_id' => $project->workspace_id,
        'is_default' => true,
    ]);

    return [$project, $version->id, $language->id];
}

/**
 * @param  array<string, string>  $entries
 */
function markdownZip(array $entries, string $name = 'docs.zip'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'import').'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($entries as $entry => $contents) {
        $zip->addFromString($entry, $contents);
    }

    $zip->close();

    return new UploadedFile($path, $name, 'application/zip', null, true);
}

it('imports a single markdown file using front matter title and slug', function (): void {
    $owner = User::factory()->onboarded()->create();
    [$project, $versionId, $languageId] = importContext($owner);

    $file = UploadedFile::fake()->createWithContent(
        'raw-name.md',
        "---\ntitle: Getting Started\nslug: getting-started\nposition: 3\n---\n\n# Ignored heading\n\nWelcome.",
    );

    $this->actingAs($owner)
        ->post(route('pages.import', $project->id), [
            'files' => [$file],
            'documentation_version_id' => $versionId,
            'language_id' => $languageId,
        ])
        ->assertRedirect()
        ->assertSessionHas('import.created', 1);

    $page = Page::query()->withoutGlobalScopes()->where('slug', 'getting-started')->firstOrFail();

    expect($page->title)->toBe('Getting Started')
        ->and($page->position)->toBe(3)
        ->and($page->status)->toBe(PageStatus::Draft)
        ->and($page->markdown)->toContain('Welcome.')
        ->and($page->markdown)->not->toContain('title: Getting Started')
        ->and($page->html)->toContain('Welcome.');
});

it('imports multiple files deriving titles from headings and filenames', function (): void {
    $owner = User::factory()->onboarded()->create();
    [$project, $versionId, $languageId] = importContext($owner);

    $this->actingAs($owner)
        ->post(route('pages.import', $project->id), [
            'files' => [
                UploadedFile::fake()->createWithContent('one.md', "# First Page\n\nBody"),
                UploadedFile::fake()->createWithContent('second_page.markdown', 'No heading here'),
            ],
            'documentation_version_id' => $versionId,
            'language_id' => $languageId,
        ])
        ->assertSessionHas('import.created', 2);

    expect(Page::query()->withoutGlobalScopes()->where('slug', 'one')->value('title'))->toBe('First Page')
        ->and(Page::query()->withoutGlobalScopes()->where('slug', 'second-page')->value('title'))->toBe('Second Page');
});

it('imports a zip archive and nests pages under folder pages', function (): void {
    $owner = User::factory()->onboarded()->create();
    [$project, $versionId, $languageId] = importContext($owner);

    $zip = markdownZip([
        'guides/index.md' => "# Guides overview\n\nStart here.",
        'guides/advanced/tuning.md' => "# Tuning\n\nDetails.",
        'assets/logo.png' => 'not markdown',
    ]);

    $this->actingAs($owner)
        ->post(route('pages.import', $project->id), [
            'files' => [$zip],
            'documentation_version_id' => $versionId,
            'language_id' => $languageId,
        ])
        ->assertSessionHas('import.created', 2);

    expect(Page::query()->withoutGlobalScopes()->where('project_id', $project->id)->count())->toBe(3);

    $guides = Page::query()->withoutGlobalScopes()->where('slug', 'guides')->firstOrFail();
    $advanced = Page::query()->withoutGlobalScopes()->where('slug', 'advanced')->firstOrFail();
    $tuning = Page::query()->withoutGlobalScopes()->where('slug', 'tuning')->firstOrFail();

    expect($guides->title)->toBe('Guides overview')
        ->and($guides->parent_id)->toBeNull()
        ->and($advanced->parent_id)->toBe($guides->id)
        ->and($tuning->parent_id)->toBe($advanced->id);
});

it('rejects zip entries that escape the archive root', function (): void {
    $owner = User::factory()->onboarded()->create();
    [$project, $versionId, $languageId] = importContext($owner);

    $zip = markdownZip(['../../evil.md' => '# Evil']);

    $this->actingAs($owner)
        ->post(route('pages.import', $project->id), [
            'files' => [$zip],
            'documentation_version_id' => $versionId,
            'language_id' => $languageId,
        ])
        ->assertSessionHas('import.created', 0);

    expect(Page::query()->withoutGlobalScopes()->where('slug', 'evil')->exists())->toBeFalse();
});

it('creates a unique slug when the slug already exists', function (): void {
    $owner = User::factory()->onboarded()->create();
    [$project, $versionId, $languageId] = importContext($owner);

    Page::factory()->create([
        'project_id' => $project->id,
        'workspace_id' => $project->workspace_id,
        'documentation_version_id' => $versionId,
        'language_id' => $languageId,
        'slug' => 'overview',
    ]);

    $this->actingAs($owner)
        ->post(route('pages.import', $project->id), [
            'files' => [UploadedFile::fake()->createWithContent('overview.md', '# Overview')],
            'documentation_version_id' => $versionId,
            'language_id' => $languageId,
            'conflict' => 'unique',
        ])
        ->assertSessionHas('import.created', 1);

    expect(Page::query()->withoutGlobalScopes()->where('slug', 'overview-1')->exists())->toBeTrue();
});

it('skips or updates existing pages depending on the conflict strategy', function (): void {
    $owner = User::factory()->onboarded()->create();
    [$project, $versionId, $languageId] = importContext($owner);

    $existing = Page::factory()->create([
        'project_id' => $project->id,
        'workspace_id' => $project->workspace_id,
        'documentation_version_id' => $versionId,
        'language_id' => $languageId,
        'slug' => 'overview',
        'markdown' => '# Original',
    ]);

    $this->actingAs($owner)
        ->post(route('pages.import', $project->id), [
            'files' => [UploadedFile::fake()->createWithContent('overview.md', '# Skipped')],
            'documentation_version_id' => $versionId,
            'language_id' => $languageId,
            'conflict' => 'skip',
        ])
        ->assertSessionHas('import.skipped', 1);

    expect($existing->fresh()?->markdown)->toBe('# Original');

    $this->actingAs($owner)
        ->post(route('pages.import', $project->id), [
            'files' => [UploadedFile::fake()->createWithContent('overview.md', '# Replaced')],
            'documentation_version_id' => $versionId,
            'language_id' => $languageId,
            'conflict' => 'update',
        ])
        ->assertSessionHas('import.updated', 1);

    expect($existing->fresh()?->markdown)->toBe('# Replaced')
        ->and(PageRevision::query()->withoutGlobalScopes()->where('page_id', $existing->id)->where('event', 'import')->exists())->toBeTrue()
        ->and(Page::query()->withoutGlobalScopes()->where('project_id', $project->id)->count())->toBe(1);
});

it('publishes imported pages immediately when requested', function (): void {
    $owner = User::factory()->onboarded()->create();
    [$project, $versionId, $languageId] = importContext($owner);

    $this->actingAs($owner)
        ->post(route('pages.import', $project->id), [
            'files' => [UploadedFile::fake()->createWithContent('launch.md', "# Launch\n\nShip it.")],
            'documentation_version_id' => $versionId,
            'language_id' => $languageId,
            'publish_immediately' => true,
        ])
        ->assertSessionHas('import.created', 1);

    $page = Page::query()->withoutGlobalScopes()->where('slug', 'launch')->firstOrFail();

    expect($page->status)->toBe(PageStatus::Published)
        ->and($page->published_html)->not->toBeNull()
        ->and(DocIndexChunk::query()->withoutGlobalScopes()->where('page_id', $page->id)->exists())->toBeTrue();
});

it('rejects files that are not markdown or zip archives', function (): void {
    $owner = User::factory()->onboarded()->create();
    [$project, $versionId, $languageId] = importContext($owner);

    $this->actingAs($owner)
        ->post(route('pages.import', $project->id), [
            'files' => [UploadedFile::fake()->createWithContent('notes.exe', 'binary')],
            'documentation_version_id' => $versionId,
            'language_id' => $languageId,
        ])
        ->assertSessionHasErrors('files.0');

    expect(Page::query()->withoutGlobalScopes()->where('project_id', $project->id)->count())->toBe(0);
});

it('denies importing into a project from another workspace', function (): void {
    $owner = User::factory()->onboarded()->create();
    [$project, $versionId, $languageId] = importContext($owner);

    $intruder = User::factory()->onboarded()->create();

    $this->actingAs($intruder)
        ->post(route('pages.import', $project->id), [
            'files' => [UploadedFile::fake()->createWithContent('sneaky.md', '# Sneaky')],
            'documentation_version_id' => $versionId,
            'language_id' => $languageId,
        ])
        ->assertNotFound();

    expect(Page::query()->withoutGlobalScopes()->where('slug', 'sneaky')->exists())->toBeFalse();
});
