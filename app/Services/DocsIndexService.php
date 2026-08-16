<?php

namespace App\Services;

use App\Enums\PageStatus;
use App\Models\DocIndexChunk;
use App\Models\Page;
use App\Models\Project;
use App\Models\Scopes\WorkspaceScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DocsIndexService
{
    private const int MaxChunkLength = 2500;

    /**
     * @return array{pages: int, chunks: int}
     */
    public function indexProject(Project $project): array
    {
        $pages = Page::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $project->id)
            ->where('status', PageStatus::Published)
            ->where(fn ($query) => $query->where('hidden', false)->orWhereNull('hidden'))
            ->get();

        $chunkCount = 0;

        foreach ($pages as $page) {
            $chunkCount += $this->indexPage($page);
        }

        DocIndexChunk::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $project->id)
            ->whereNotIn('page_id', $pages->pluck('id'))
            ->delete();

        $project->forceFill(['ai_indexed_at' => now()])->save();

        return [
            'pages' => $pages->count(),
            'chunks' => $chunkCount,
        ];
    }

    public function indexPage(Page $page): int
    {
        DocIndexChunk::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('page_id', $page->id)
            ->delete();

        if ($page->status !== PageStatus::Published || $page->hidden) {
            return 0;
        }

        $chunks = $this->buildChunks($page);

        if ($chunks === []) {
            return 0;
        }

        $now = now();

        foreach ($chunks as $chunk) {
            DocIndexChunk::query()->create([
                'workspace_id' => $page->workspace_id,
                'project_id' => $page->project_id,
                'page_id' => $page->id,
                'documentation_version_id' => $page->documentation_version_id,
                'language_id' => $page->language_id,
                'page_title' => $page->title,
                'page_slug' => $page->slug,
                'heading' => $chunk['heading'],
                'content' => $chunk['content'],
                'position' => $chunk['position'],
                'indexed_at' => $now,
            ]);
        }

        Project::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->whereKey($page->project_id)
            ->update(['ai_indexed_at' => $now]);

        return count($chunks);
    }

    public function removePage(Page $page): void
    {
        DocIndexChunk::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('page_id', $page->id)
            ->delete();
    }

    /**
     * @return array{pages: int, chunks: int, indexed_at: string|null}
     */
    public function stats(Project $project): array
    {
        $chunks = DocIndexChunk::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $project->id);

        return [
            'pages' => (clone $chunks)->distinct('page_id')->count('page_id'),
            'chunks' => (clone $chunks)->count(),
            'indexed_at' => $project->ai_indexed_at?->toIso8601String(),
        ];
    }

    /**
     * @return Collection<int, array{page: Page, chunk: DocIndexChunk, score: int}>
     */
    public function search(
        Project $project,
        int $documentationVersionId,
        int $languageId,
        string $question,
        int $limit = 5,
    ): Collection {
        $terms = $this->searchTerms($question);

        if ($terms === []) {
            return collect();
        }

        $chunks = DocIndexChunk::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $project->id)
            ->where('documentation_version_id', $documentationVersionId)
            ->where('language_id', $languageId)
            ->orderBy('position')
            ->get();

        if ($chunks->isEmpty()) {
            return collect();
        }

        $scored = $chunks->map(function (DocIndexChunk $chunk) use ($terms, $question): array {
            $haystack = mb_strtolower(trim($chunk->page_title.' '.($chunk->heading ?? '').' '.$chunk->content));
            $score = 0;

            if (str_contains($haystack, mb_strtolower($question))) {
                $score += 10;
            }

            foreach ($terms as $term) {
                if (str_contains($haystack, $term)) {
                    $score += mb_strlen($term) >= 4 ? 3 : 1;
                }

                if ($chunk->heading !== null && str_contains(mb_strtolower($chunk->heading), $term)) {
                    $score += 2;
                }

                if (str_contains(mb_strtolower($chunk->page_title), $term)) {
                    $score += 2;
                }
            }

            return [
                'chunk' => $chunk,
                'score' => $score,
            ];
        })->filter(fn (array $row): bool => $row['score'] > 0)
            ->sortByDesc('score')
            ->values();

        $pageIds = [];
        $results = collect();

        foreach ($scored as $row) {
            /** @var DocIndexChunk $chunk */
            $chunk = $row['chunk'];

            if (in_array($chunk->page_id, $pageIds, true)) {
                continue;
            }

            $page = Page::query()
                ->withoutGlobalScope(WorkspaceScope::class)
                ->find($chunk->page_id);

            if (! $page instanceof Page) {
                continue;
            }

            $pageIds[] = $chunk->page_id;
            $results->push([
                'page' => $page,
                'chunk' => $chunk,
                'score' => $row['score'],
            ]);

            if ($results->count() >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * @return list<array{heading: string|null, content: string, position: int}>
     */
    private function buildChunks(Page $page): array
    {
        $markdown = trim((string) ($page->published_markdown ?: strip_tags((string) $page->published_html)));

        if ($markdown === '') {
            return [];
        }

        $sections = preg_split('/\n(?=##\s+)/u', $markdown) ?: [$markdown];
        $chunks = [];
        $position = 0;

        foreach ($sections as $section) {
            $section = trim($section);

            if ($section === '') {
                continue;
            }

            $heading = null;

            if (preg_match('/^##\s+(.+)$/mu', $section, $matches)) {
                $heading = trim($matches[1]);
            }

            $content = $this->plainText($section);

            if ($content === '') {
                continue;
            }

            foreach ($this->splitContent($content) as $piece) {
                $chunks[] = [
                    'heading' => $heading,
                    'content' => $piece,
                    'position' => $position++,
                ];
            }
        }

        return $chunks;
    }

    private function plainText(string $markdown): string
    {
        $text = preg_replace('/```[\s\S]*?```/u', ' ', $markdown) ?? $markdown;
        $text = preg_replace('/`([^`]+)`/u', '$1', $text) ?? $text;
        $text = preg_replace('/!\[[^\]]*\]\([^)]+\)/u', ' ', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/u', '$1', $text) ?? $text;
        $text = preg_replace('/^#{1,6}\s+/mu', '', $text) ?? $text;
        $text = preg_replace('/^\s*[-*+]\s+/mu', '', $text) ?? $text;
        $text = preg_replace('/^\s*\d+\.\s+/mu', '', $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return list<string>
     */
    private function splitContent(string $content): array
    {
        if (mb_strlen($content) <= self::MaxChunkLength) {
            return [$content];
        }

        $parts = [];
        $remaining = $content;

        while ($remaining !== '') {
            if (mb_strlen($remaining) <= self::MaxChunkLength) {
                $parts[] = $remaining;
                break;
            }

            $slice = mb_substr($remaining, 0, self::MaxChunkLength);
            $break = mb_strrpos($slice, '. ');

            if ($break === false || $break < (int) (self::MaxChunkLength * 0.5)) {
                $break = mb_strrpos($slice, ' ');
            }

            if ($break === false || $break < 1) {
                $parts[] = $slice;
                $remaining = mb_substr($remaining, self::MaxChunkLength);
            } else {
                $parts[] = trim(mb_substr($remaining, 0, $break + 1));
                $remaining = trim(mb_substr($remaining, $break + 1));
            }
        }

        return array_values(array_filter($parts, fn (string $part): bool => $part !== ''));
    }

    /**
     * @return list<string>
     */
    private function searchTerms(string $query): array
    {
        $terms = preg_split('/\s+/u', mb_strtolower(trim($query))) ?: [];

        return array_values(array_unique(array_filter(
            $terms,
            fn (string $term): bool => mb_strlen($term) >= 2,
        )));
    }
}
