<?php

namespace App\Services;

use App\Support\PlatformAiConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AiPageGenerator
{
    /**
     * @var array<string, array{label: string, instruction: string}>
     */
    public const AUDIENCES = [
        'developers' => [
            'label' => 'Developers',
            'instruction' => 'Write for software developers and technical implementers. Assume familiarity with APIs, CLIs, configuration files, and common dev workflows.',
        ],
        'end_users' => [
            'label' => 'End users',
            'instruction' => 'Write for non-technical end users. Avoid jargon, explain UI steps clearly, and focus on accomplishing tasks in the product.',
        ],
    ];

    /**
     * @var array<string, array{label: string, instruction: string}>
     */
    public const TONES = [
        'technical' => [
            'label' => 'Technical',
            'instruction' => 'Use a precise, professional tone. Prefer active voice and concrete steps.',
        ],
        'friendly' => [
            'label' => 'Friendly',
            'instruction' => 'Use a warm, approachable tone while staying accurate and actionable.',
        ],
    ];

    /**
     * @param  array{
     *     project: string,
     *     title: string,
     *     slug: string,
     *     markdown: string,
     *     siblings: list<string>,
     *     description: string,
     *     suggest_metadata: bool
     * }  $context
     * @return array{
     *     markdown: string,
     *     summary: string,
     *     title: string|null,
     *     subtitle: string|null
     * }
     */
    public function generate(array $context, string $audience, string $tone): array
    {
        PlatformAiConfig::apply();

        $apiKey = (string) config('ai.api_key');

        if ($apiKey === '') {
            throw ValidationException::withMessages([
                'ai' => 'AI is not configured. Enable AI in Platform → Settings.',
            ]);
        }

        try {
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
                            'content' => $this->buildPrompt($context, $audience, $tone),
                        ],
                    ],
                    options: [
                        'response_format' => ['type' => 'json_object'],
                        'temperature' => 0.4,
                    ],
                ));
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Could not reach the AI provider: '.$exception->getMessage());
        }

        if (! $response->successful()) {
            throw new RuntimeException(PlatformAiConfig::formatApiError($response));
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('AI provider returned an empty response.');
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($content, true);

        if (! is_array($payload)) {
            throw new RuntimeException('AI provider returned an invalid generation response.');
        }

        $markdown = trim((string) ($payload['markdown'] ?? ''));

        if ($markdown === '') {
            throw new RuntimeException('AI provider did not return page Markdown.');
        }

        $title = trim((string) ($payload['title'] ?? ''));
        $subtitle = trim((string) ($payload['subtitle'] ?? ''));

        return [
            'markdown' => $markdown,
            'summary' => Str::limit(trim((string) ($payload['summary'] ?? '')), 600),
            'title' => $context['suggest_metadata'] && $title !== '' ? Str::limit($title, 180) : null,
            'subtitle' => $context['suggest_metadata'] && $subtitle !== '' ? Str::limit($subtitle, 280) : null,
        ];
    }

    public static function isConfigured(): bool
    {
        return PlatformAiConfig::isConfigured();
    }

    /**
     * @param  array{
     *     project: string,
     *     title: string,
     *     slug: string,
     *     markdown: string,
     *     siblings: list<string>,
     *     description: string,
     *     suggest_metadata: bool
     * }  $context
     */
    private function buildPrompt(array $context, string $audience, string $tone): string
    {
        $audienceInstruction = self::AUDIENCES[$audience]['instruction'] ?? self::AUDIENCES['developers']['instruction'];
        $toneInstruction = self::TONES[$tone]['instruction'] ?? self::TONES['technical']['instruction'];
        $siblings = $context['siblings'] === [] ? 'None' : implode(', ', $context['siblings']);
        $existing = trim($context['markdown']);
        $existingSection = $existing === ''
            ? 'The page is currently empty.'
            : <<<SECTION
Current page Markdown (for context only — write a fresh page that replaces this content), delimited by <<<PAGE and PAGE>>>:
<<<PAGE
{$existing}
PAGE>>>
SECTION;

        $metadataFields = $context['suggest_metadata']
            ? '"title": "Suggested page title", "subtitle": "Optional one-line subtitle",'
            : '"title": null, "subtitle": null,';

        return <<<PROMPT
Write a complete documentation page for a documentation project.

Project: {$context['project']}
Page title: {$context['title']}
Page slug: {$context['slug']}
Other pages in this documentation set: {$siblings}

What this page should cover:
{$context['description']}

Audience:
{$audienceInstruction}

Tone:
{$toneInstruction}

{$existingSection}

Return JSON with this exact shape:
{
  "summary": "One or two sentences describing what you wrote",
  {$metadataFields}
  "markdown": "The full page in Markdown"
}

Requirements:
- "markdown" must be the complete page body, not a fragment or outline only.
- Use clear heading hierarchy starting with a single H1 that matches the page topic.
- Use lists, tables, and fenced code blocks with language hints where they help readers.
- Use callouts with this syntax when helpful:

:::tip
Helpful advice
:::

:::warning
Important caution
:::

:::info
Neutral context
:::

- Do not invent product features that are not implied by the brief or existing content.
- Do not wrap the Markdown in a code fence.
PROMPT;
    }
}
