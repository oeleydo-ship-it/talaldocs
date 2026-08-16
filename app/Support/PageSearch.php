<?php

namespace App\Support;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\Scopes\WorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PageSearch
{
    /**
     * @return Builder<Page>
     */
    public function publishedForProject(
        int $projectId,
        string $query,
        ?int $documentationVersionId = null,
        ?int $languageId = null,
    ): Builder {
        $builder = Page::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $projectId)
            ->where('status', PageStatus::Published)
            ->where(fn ($query) => $query->where('hidden', false)->orWhereNull('hidden'));

        if ($documentationVersionId !== null) {
            $builder->where('documentation_version_id', $documentationVersionId);
        }

        if ($languageId !== null) {
            $builder->where('language_id', $languageId);
        }

        $query = trim($query);

        if ($query === '') {
            return $builder;
        }

        return $this->applyTextSearch($builder, $query);
    }

    /**
     * @return Builder<Page>
     */
    private function applyTextSearch(Builder $builder, string $query): Builder
    {
        $terms = $this->searchTerms($query);

        return $builder->where(function (Builder $inner) use ($query, $terms): void {
            $this->applyTermMatch($inner, $query);

            foreach ($terms as $term) {
                if ($term === mb_strtolower($query)) {
                    continue;
                }

                $inner->orWhere(function (Builder $nested) use ($term): void {
                    $this->applyTermMatch($nested, $term);
                });
            }
        });
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

    private function applyTermMatch(Builder $builder, string $term): void
    {
        $like = '%'.$term.'%';

        $builder->where('title', 'like', $like)
            ->orWhere('published_markdown', 'like', $like)
            ->orWhere('published_html', 'like', $like);
    }

    public function excerpt(Page $page, string $query, int $length = 120): ?string
    {
        $haystack = strip_tags((string) ($page->published_markdown ?: $page->published_html ?: ''));
        $haystack = preg_replace('/\s+/u', ' ', $haystack) ?? $haystack;
        $haystack = trim($haystack);

        if ($haystack === '') {
            return null;
        }

        $terms = $this->searchTerms($query);
        $needle = $terms[0] ?? mb_strtolower(trim($query));

        if ($needle === '') {
            return Str::limit($haystack, $length);
        }

        $position = mb_stripos($haystack, $needle);

        if ($position === false) {
            return Str::limit($haystack, $length);
        }

        $start = max(0, $position - (int) ($length / 4));
        $snippet = mb_substr($haystack, $start, $length);

        if ($start > 0) {
            $snippet = '…'.ltrim($snippet);
        }

        if (mb_strlen($haystack) > $start + $length) {
            $snippet = rtrim($snippet).'…';
        }

        return $snippet;
    }
}
