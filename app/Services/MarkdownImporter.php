<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Project;
use App\Models\User;
use App\Support\Audit;
use App\Support\FrontMatter;
use App\Support\Markdown;
use App\Support\PagePublisher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class MarkdownImporter
{
    public const CONFLICT_STRATEGIES = ['unique', 'skip', 'update'];

    public const MAX_FILES = 100;

    public const MAX_ZIP_ENTRIES = 500;

    public const MAX_DOCUMENT_BYTES = 2_097_152;

    public const MARKDOWN_EXTENSIONS = ['md', 'markdown', 'mdx'];

    public function __construct(
        private Markdown $markdown,
        private PagePublisher $publisher,
        private Audit $audit,
    ) {}

    /**
     * @param  list<UploadedFile>  $uploads
     * @return array{
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     pages: list<array{id: int, title: string, slug: string, action: string}>,
     *     errors: list<array{file: string, message: string}>
     * }
     */
    public function import(
        Project $project,
        array $uploads,
        int $versionId,
        int $languageId,
        string $conflict = 'unique',
        bool $publish = false,
        ?User $user = null,
    ): array {
        $conflict = in_array($conflict, self::CONFLICT_STRATEGIES, true) ? $conflict : 'unique';

        $errors = [];
        $documents = $this->collectDocuments($uploads, $errors);

        usort($documents, fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $pages = [];
        $folderPages = [];
        $folderCreated = [];

        foreach ($documents as $document) {
            try {
                $result = $this->importDocument(
                    $project,
                    $document,
                    $versionId,
                    $languageId,
                    $conflict,
                    $publish,
                    $user,
                    $folderPages,
                    $folderCreated,
                );
            } catch (Throwable $exception) {
                $errors[] = [
                    'file' => $document['path'],
                    'message' => Str::limit($exception->getMessage(), 200),
                ];

                continue;
            }

            match ($result['action']) {
                'created' => $created++,
                'updated' => $updated++,
                default => $skipped++,
            };

            if ($result['action'] !== 'skipped') {
                $pages[] = $result;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'pages' => $pages,
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<UploadedFile>  $uploads
     * @param  list<array{file: string, message: string}>  $errors
     * @return list<array{path: string, contents: string}>
     */
    private function collectDocuments(array $uploads, array &$errors): array
    {
        $documents = [];

        foreach ($uploads as $upload) {
            $name = $this->safeName($upload->getClientOriginalName());
            $extension = Str::lower((string) pathinfo($name, PATHINFO_EXTENSION));

            if ($extension === 'zip') {
                foreach ($this->expandArchive($upload, $errors) as $document) {
                    $documents[] = $document;
                }

                continue;
            }

            if (! in_array($extension, self::MARKDOWN_EXTENSIONS, true)) {
                $errors[] = ['file' => $name, 'message' => 'Unsupported file type.'];

                continue;
            }

            $contents = (string) file_get_contents((string) $upload->getRealPath());

            if (strlen($contents) > self::MAX_DOCUMENT_BYTES) {
                $errors[] = ['file' => $name, 'message' => 'File is larger than 2 MB.'];

                continue;
            }

            $documents[] = ['path' => $name, 'contents' => $contents];
        }

        return array_slice($documents, 0, self::MAX_FILES);
    }

    /**
     * @param  list<array{file: string, message: string}>  $errors
     * @return list<array{path: string, contents: string}>
     */
    private function expandArchive(UploadedFile $upload, array &$errors): array
    {
        $archiveName = $this->safeName($upload->getClientOriginalName());
        $zip = new ZipArchive;

        if ($zip->open((string) $upload->getRealPath()) !== true) {
            $errors[] = ['file' => $archiveName, 'message' => 'Archive could not be opened.'];

            return [];
        }

        $documents = [];
        $entryCount = min($zip->numFiles, self::MAX_ZIP_ENTRIES);

        for ($index = 0; $index < $entryCount; $index++) {
            $entry = (string) $zip->getNameIndex($index);

            if ($entry === '' || str_ends_with($entry, '/')) {
                continue;
            }

            $path = $this->normalizeArchivePath($entry);

            if ($path === null) {
                $errors[] = ['file' => $archiveName.' › '.$entry, 'message' => 'Unsafe path in archive.'];

                continue;
            }

            if (! in_array(Str::lower((string) pathinfo($path, PATHINFO_EXTENSION)), self::MARKDOWN_EXTENSIONS, true)) {
                continue;
            }

            $stat = $zip->statIndex($index);

            if (is_array($stat) && (int) $stat['size'] > self::MAX_DOCUMENT_BYTES) {
                $errors[] = ['file' => $archiveName.' › '.$path, 'message' => 'File is larger than 2 MB.'];

                continue;
            }

            $contents = $zip->getFromIndex($index);

            if ($contents === false) {
                $errors[] = ['file' => $archiveName.' › '.$path, 'message' => 'Entry could not be read.'];

                continue;
            }

            $documents[] = ['path' => $path, 'contents' => $contents];
        }

        $zip->close();

        if ($documents === []) {
            $errors[] = ['file' => $archiveName, 'message' => 'Archive contains no Markdown files.'];
        }

        return $documents;
    }

    private function normalizeArchivePath(string $entry): ?string
    {
        $path = str_replace('\\', '/', $entry);

        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:/#', $path) === 1) {
            return null;
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                return null;
            }

            if (str_starts_with($segment, '__MACOSX')) {
                return null;
            }

            $segments[] = $segment;
        }

        return $segments === [] ? null : implode('/', $segments);
    }

    private function safeName(?string $name): string
    {
        $name = str_replace('\\', '/', (string) $name);

        return basename($name) ?: 'upload';
    }

    /**
     * @param  array{path: string, contents: string}  $document
     * @param  array<string, Page>  $folderPages
     * @param  array<string, bool>  $folderCreated
     * @return array{id: int, title: string, slug: string, action: string}
     */
    private function importDocument(
        Project $project,
        array $document,
        int $versionId,
        int $languageId,
        string $conflict,
        bool $publish,
        ?User $user,
        array &$folderPages,
        array &$folderCreated,
    ): array {
        $parsed = FrontMatter::parse($document['contents']);
        $body = $parsed['body'];
        $meta = $parsed['data'];

        $segments = explode('/', $document['path']);
        $filename = (string) pathinfo((string) array_pop($segments), PATHINFO_FILENAME);
        $isFolderIndex = $segments !== [] && in_array(Str::lower($filename), ['index', 'readme'], true);

        $parent = $this->parentPage(
            $project,
            $versionId,
            $languageId,
            $segments,
            $folderPages,
            $folderCreated,
        );

        if ($isFolderIndex && $parent !== null) {
            $key = implode('/', $segments);
            $title = $this->resolveTitle($meta, $body, (string) end($segments));

            $this->publisher->saveDraft($parent, $title, $parent->slug, $body, $user);

            if ($publish) {
                $this->publisher->publish($parent, $user);
            }

            $this->audit->record($project->workspace_id, 'page.imported', $user, $parent, [
                'source' => $document['path'],
                'action' => 'section',
            ]);

            return [
                'id' => $parent->id,
                'title' => $title,
                'slug' => $parent->slug,
                'action' => ($folderCreated[$key] ?? false) ? 'created' : 'updated',
            ];
        }

        $title = $this->resolveTitle($meta, $body, $filename);
        $slug = Str::slug($meta['slug'] ?? $filename) ?: 'page';

        $existing = Page::query()
            ->where('documentation_version_id', $versionId)
            ->where('language_id', $languageId)
            ->where('slug', $slug)
            ->first();

        if ($existing !== null && $conflict === 'skip') {
            return ['id' => $existing->id, 'title' => $existing->title, 'slug' => $slug, 'action' => 'skipped'];
        }

        if ($existing !== null && $conflict === 'update') {
            $this->publisher->recordRevision($existing, $user, 'import');
            $this->publisher->saveDraft($existing, $title, $slug, $body, $user);
            if ($parent !== null) {
                $existing->forceFill(['parent_id' => $parent->id])->save();
            }

            if ($publish) {
                $this->publisher->publish($existing, $user);
            }

            $this->audit->record($project->workspace_id, 'page.imported', $user, $existing, [
                'source' => $document['path'],
                'action' => 'updated',
            ]);

            return ['id' => $existing->id, 'title' => $title, 'slug' => $slug, 'action' => 'updated'];
        }

        $page = $this->createPage(
            $project,
            $versionId,
            $languageId,
            $title,
            $this->uniqueSlug($slug, $versionId, $languageId),
            $body,
            $parent,
            $this->resolvePosition($meta),
        );

        if ($publish) {
            $this->publisher->publish($page, $user);
        }

        $this->audit->record($project->workspace_id, 'page.imported', $user, $page, [
            'source' => $document['path'],
            'action' => 'created',
        ]);

        return ['id' => $page->id, 'title' => $page->title, 'slug' => $page->slug, 'action' => 'created'];
    }

    /**
     * @param  list<string>  $segments
     * @param  array<string, Page>  $folderPages
     * @param  array<string, bool>  $folderCreated
     */
    private function parentPage(
        Project $project,
        int $versionId,
        int $languageId,
        array $segments,
        array &$folderPages,
        array &$folderCreated,
    ): ?Page {
        $parent = null;

        foreach ($segments as $depth => $segment) {
            $parent = $this->folderPage(
                $project,
                $versionId,
                $languageId,
                array_slice($segments, 0, $depth + 1),
                $parent,
                $folderPages,
                $folderCreated,
            );
        }

        return $parent;
    }

    /**
     * @param  list<string>  $segments
     * @param  array<string, Page>  $folderPages
     * @param  array<string, bool>  $folderCreated
     */
    private function folderPage(
        Project $project,
        int $versionId,
        int $languageId,
        array $segments,
        ?Page $parent,
        array &$folderPages,
        array &$folderCreated,
    ): Page {
        $key = implode('/', $segments);

        if (isset($folderPages[$key])) {
            return $folderPages[$key];
        }

        $name = (string) end($segments);
        $slug = Str::slug($name) ?: 'section';

        $existing = Page::query()
            ->where('documentation_version_id', $versionId)
            ->where('language_id', $languageId)
            ->where('parent_id', $parent?->id)
            ->where('slug', $slug)
            ->first();

        if ($existing !== null) {
            return $folderPages[$key] = $existing;
        }

        $title = $this->humanize($name);
        $folderCreated[$key] = true;

        return $folderPages[$key] = $this->createPage(
            $project,
            $versionId,
            $languageId,
            $title,
            $this->uniqueSlug($slug, $versionId, $languageId),
            '# '.$title,
            $parent,
            null,
        );
    }

    private function createPage(
        Project $project,
        int $versionId,
        int $languageId,
        string $title,
        string $slug,
        string $body,
        ?Page $parent,
        ?int $position,
    ): Page {
        return Page::query()->create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'documentation_version_id' => $versionId,
            'language_id' => $languageId,
            'parent_id' => $parent?->id,
            'title' => Str::limit($title, 180, ''),
            'slug' => $slug,
            'markdown' => $body,
            'html' => $this->markdown->render($body, $project->workspace_id),
            'position' => $position ?? $this->nextPosition($versionId, $languageId),
        ]);
    }

    /**
     * @param  array<string, string>  $meta
     */
    private function resolvePosition(array $meta): ?int
    {
        foreach (['position', 'order', 'sidebar_position', 'weight'] as $key) {
            if (isset($meta[$key]) && is_numeric($meta[$key])) {
                return (int) $meta[$key];
            }
        }

        return null;
    }

    private function nextPosition(int $versionId, int $languageId): int
    {
        return (int) Page::query()
            ->where('documentation_version_id', $versionId)
            ->where('language_id', $languageId)
            ->max('position') + 1;
    }

    /**
     * @param  array<string, string>  $meta
     */
    private function resolveTitle(array $meta, string $body, string $filename): string
    {
        $title = trim($meta['title'] ?? '');

        if ($title !== '') {
            return $title;
        }

        if (preg_match('/^#\s+(.+)$/m', $body, $matches) === 1) {
            $heading = trim($matches[1]);

            if ($heading !== '') {
                return $heading;
            }
        }

        return $this->humanize($filename);
    }

    private function humanize(string $value): string
    {
        $value = preg_replace('/^\d+[-_. ]+/', '', $value) ?? $value;
        $value = trim(str_replace(['-', '_'], ' ', $value));

        return $value === '' ? 'Untitled' : Str::title($value);
    }

    private function uniqueSlug(string $slug, int $versionId, int $languageId): string
    {
        $base = $slug;
        $candidate = $base;
        $i = 1;

        while (Page::query()
            ->where('documentation_version_id', $versionId)
            ->where('language_id', $languageId)
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $base.'-'.$i++;
        }

        return $candidate;
    }
}
