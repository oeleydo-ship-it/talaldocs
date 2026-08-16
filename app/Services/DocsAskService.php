<?php

namespace App\Services;

use App\Enums\PageStatus;
use App\Models\DocumentationVersion;
use App\Models\Language;
use App\Models\Page;
use App\Models\Project;
use App\Models\ProjectLanguage;
use App\Models\Scopes\WorkspaceScope;
use App\Support\PageSearch;
use App\Support\PlatformAiConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class DocsAskService
{
    private const int MaxPages = 5;

    private const int MaxCharsPerPage = 2500;

    public function __construct(
        private PageSearch $search,
        private DocsIndexService $index,
    ) {}

    public static function isConfigured(): bool
    {
        return PlatformAiConfig::isConfigured();
    }

    /**
     * @return array{
     *     answer: string,
     *     answer_html: string,
     *     sources: list<array{title: string, slug: string, url: string}>
     * }
     */
    public function ask(Project $project, string $question): array
    {
        $apiKey = (string) config('ai.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('AI is not configured.');
        }

        $question = trim($question);

        if ($question === '') {
            throw new RuntimeException('Question cannot be empty.');
        }

        $version = $this->defaultVersion($project);
        $language = $this->defaultLanguage($project);
        $retrieval = $this->retrieveContext($project, $version, $language, $question);
        $answer = $this->callAi($question, $retrieval['context'], $project->name);

        return [
            'answer' => $answer,
            'answer_html' => Str::markdown($answer, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
            'sources' => $retrieval['sources'],
        ];
    }

    private function defaultVersion(Project $project): DocumentationVersion
    {
        return DocumentationVersion::query()
            ->withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->orderByDesc('is_default')
            ->firstOrFail();
    }

    private function defaultLanguage(Project $project): Language
    {
        $default = ProjectLanguage::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $project->id)
            ->where('is_default', true)
            ->first();

        return Language::query()->findOrFail(
            $default?->language_id ?? Language::query()->where('code', 'en')->value('id'),
        );
    }

    /**
     * @return array{context: string, sources: list<array{title: string, slug: string, url: string}>}
     */
    private function retrieveContext(
        Project $project,
        DocumentationVersion $version,
        Language $language,
        string $question,
    ): array {
        $indexed = $this->index->search(
            $project,
            $version->id,
            $language->id,
            $question,
            self::MaxPages,
        );

        if ($indexed->isNotEmpty()) {
            return [
                'context' => $this->buildIndexedContext($indexed),
                'sources' => $indexed->map(function (array $row) use ($project, $version, $language): array {
                    /** @var Page $page */
                    $page = $row['page'];

                    return [
                        'title' => $page->title,
                        'slug' => $page->slug,
                        'url' => $project->publicUrl().'/'.$version->slug.'/'.$language->code.'/'.$page->slug,
                    ];
                })->values()->all(),
            ];
        }

        $pages = $this->retrieveFallbackPages($project, $version, $language, $question);

        return [
            'context' => $this->buildContext($pages),
            'sources' => $pages->map(fn (Page $page): array => [
                'title' => $page->title,
                'slug' => $page->slug,
                'url' => $project->publicUrl().'/'.$version->slug.'/'.$language->code.'/'.$page->slug,
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, array{page: Page, chunk: \App\Models\DocIndexChunk, score: int}>  $results
     */
    private function buildIndexedContext(Collection $results): string
    {
        return $results->map(function (array $row): string {
            /** @var Page $page */
            $page = $row['page'];
            /** @var \App\Models\DocIndexChunk $chunk */
            $chunk = $row['chunk'];

            $heading = $chunk->heading ?: $page->title;
            $content = $chunk->content;

            if (mb_strlen($content) > self::MaxCharsPerPage) {
                $content = mb_substr($content, 0, self::MaxCharsPerPage).'…';
            }

            return "## {$heading}\n\n{$content}";
        })->implode("\n\n---\n\n");
    }

    /**
     * @return Collection<int, Page>
     */
    private function retrieveFallbackPages(
        Project $project,
        DocumentationVersion $version,
        Language $language,
        string $question,
    ): Collection {
        $matched = $this->search
            ->publishedForProject($project->id, $question, $version->id, $language->id)
            ->limit(self::MaxPages)
            ->get(['id', 'title', 'slug', 'published_markdown']);

        if ($matched->isNotEmpty()) {
            return $matched;
        }

        return Page::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $project->id)
            ->where('documentation_version_id', $version->id)
            ->where('language_id', $language->id)
            ->where('status', PageStatus::Published)
            ->where(fn ($query) => $query->where('hidden', false)->orWhereNull('hidden'))
            ->orderBy('position')
            ->limit(3)
            ->get(['id', 'title', 'slug', 'published_markdown']);
    }

    /**
     * @param  Collection<int, Page>  $pages
     */
    private function buildContext(Collection $pages): string
    {
        return $pages->map(function (Page $page): string {
            $markdown = trim(strip_tags((string) $page->published_markdown));

            if (mb_strlen($markdown) > self::MaxCharsPerPage) {
                $markdown = mb_substr($markdown, 0, self::MaxCharsPerPage).'…';
            }

            return "## {$page->title}\n\n{$markdown}";
        })->implode("\n\n---\n\n");
    }

    private function callAi(string $question, string $context, string $projectName): string
    {
        $response = Http::timeout((int) config('ai.timeout'))
            ->withToken((string) config('ai.api_key'))
            ->acceptJson()
            ->post((string) config('ai.base_url').'/chat/completions', PlatformAiConfig::chatCompletionParams(
                messages: [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful documentation assistant for '.$projectName.'. '
                            .'Answer questions using ONLY the provided documentation excerpts from the indexed knowledge base. '
                            .'If the answer is not in the context, say you could not find that information in the docs. '
                            .'Be concise and use Markdown formatting. Do not invent features or steps.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Documentation excerpts:\n\n{$context}\n\nQuestion: {$question}",
                    ],
                ],
                options: [
                    'temperature' => 0.3,
                ],
            ));

        if (! $response->successful()) {
            throw new RuntimeException(PlatformAiConfig::formatApiError($response));
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('AI provider returned an empty response.');
        }

        return trim($content);
    }
}
