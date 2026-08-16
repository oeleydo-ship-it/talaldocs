<?php

namespace App\Services;

use App\Support\PlatformAiConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AiDocumentationGenerator
{
    /**
     * @param  array{
     *     url: string,
     *     title: string|null,
     *     description: string|null,
     *     og_title: string|null,
     *     og_description: string|null,
     *     headings: list<string>,
     *     text: string
     * }  $website
     * @return array{
     *     project_summary: string,
     *     pages: list<array{title: string, slug: string, markdown: string}>
     * }
     */
    public function generate(array $website, ?string $productDescription = null): array
    {
        PlatformAiConfig::apply();

        $apiKey = (string) config('ai.api_key');

        if ($apiKey === '') {
            throw ValidationException::withMessages([
                'ai' => 'AI is not configured. Enable AI in Platform → Settings.',
            ]);
        }

        $prompt = $this->buildPrompt($website, $productDescription);

        $response = Http::timeout((int) config('ai.timeout'))
            ->withToken($apiKey)
            ->acceptJson()
            ->post((string) config('ai.base_url').'/chat/completions', PlatformAiConfig::chatCompletionParams(
                messages: [
                    [
                        'role' => 'system',
                        'content' => 'You are a technical documentation writer for '.config('app.name', 'Docs').'. Return only valid JSON matching the requested schema.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                options: [
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.4,
                ],
            ));

        if (! $response->successful()) {
            throw new RuntimeException(PlatformAiConfig::formatApiError($response));
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('AI provider returned an empty response.');
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($content, true);

        if (! is_array($payload) || ! isset($payload['pages']) || ! is_array($payload['pages'])) {
            throw new RuntimeException('AI provider returned invalid documentation JSON.');
        }

        $pages = [];

        foreach ($payload['pages'] as $page) {
            if (! is_array($page)) {
                continue;
            }

            $title = trim((string) ($page['title'] ?? ''));
            $slug = trim((string) ($page['slug'] ?? ''));
            $markdown = trim((string) ($page['markdown'] ?? ''));

            if ($title === '' || $slug === '' || $markdown === '') {
                continue;
            }

            $pages[] = [
                'title' => $title,
                'slug' => $slug,
                'markdown' => $markdown,
            ];
        }

        if ($pages === []) {
            throw new RuntimeException('AI provider did not return any documentation pages.');
        }

        return [
            'project_summary' => trim((string) ($payload['project_summary'] ?? '')),
            'pages' => $pages,
        ];
    }

    /**
     * @param  array{
     *     url: string,
     *     title: string|null,
     *     description: string|null,
     *     og_title: string|null,
     *     og_description: string|null,
     *     headings: list<string>,
     *     text: string
     * }  $website
     */
    private function buildPrompt(array $website, ?string $productDescription): string
    {
        $headings = implode("\n- ", $website['headings']);
        $description = $productDescription ?: 'Not provided';

        return <<<PROMPT
Create documentation for a product based on this public website content.

Website URL: {$website['url']}
Page title: {$website['title']}
Meta description: {$website['description']}
Open Graph title: {$website['og_title']}
Open Graph description: {$website['og_description']}
Owner-provided description: {$description}

Headings found on the page:
- {$headings}

Visible page text:
{$website['text']}

Return JSON with this exact shape:
{
  "project_summary": "One paragraph summarizing the product for docs readers",
  "pages": [
    {"title": "Welcome", "slug": "welcome", "markdown": "# Welcome\\n\\n..."},
    {"title": "Getting started", "slug": "getting-started", "markdown": "..."},
    {"title": "Installation & setup", "slug": "installation-setup", "markdown": "..."},
    {"title": "Key features", "slug": "key-features", "markdown": "..."},
    {"title": "FAQ", "slug": "faq", "markdown": "..."}
  ]
}

Requirements:
- Use clear Markdown with headings, lists, and code blocks where helpful.
- Extract product name, value proposition, features, and setup steps from the site content.
- Include setup instructions tailored for web users visiting the public docs site.
- Mention API hints only if they appear in the source content.
- Keep each page practical and concise.
- Use URL-safe slugs (lowercase, hyphens).
PROMPT;
    }

    public static function isConfigured(): bool
    {
        return PlatformAiConfig::isConfigured();
    }
}
