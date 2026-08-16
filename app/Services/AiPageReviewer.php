<?php

namespace App\Services;

use App\Support\PlatformAiConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AiPageReviewer
{
    /**
     * @var array<string, array{label: string, instruction: string}>
     */
    public const INTENTS = [
        'accuracy' => [
            'label' => 'Accuracy & correctness',
            'instruction' => 'Review the page for factual accuracy, internal contradictions, outdated or incorrect commands, wrong option names, broken or placeholder links, and missing steps a reader needs to succeed. Correct what is wrong and fill obvious gaps without inventing product features that are not implied by the content.',
        ],
        'clarity' => [
            'label' => 'Grammar & clarity',
            'instruction' => 'Fix grammar, spelling, punctuation, and awkward phrasing. Tighten wordy sentences and make the tone consistent and direct. Do not change the technical meaning or restructure the page.',
        ],
        'structure' => [
            'label' => 'Structure & headings',
            'instruction' => 'Improve the document structure: heading hierarchy, ordering of sections, list and table formatting, and code fence language hints. Keep the wording of the existing prose unless a change is required for the new structure.',
        ],
    ];

    /**
     * @param  array{project: string, title: string, markdown: string, siblings: list<string>}  $context
     * @return array{markdown: string, summary: string, notes: list<string>}
     */
    public function review(array $context, string $intent): array
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
                            'content' => 'You are a meticulous technical documentation editor for '.config('app.name', 'Docs').'. Return only valid JSON matching the requested schema.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $this->buildPrompt($context, $intent),
                        ],
                    ],
                    options: [
                        'response_format' => ['type' => 'json_object'],
                        'temperature' => 0.2,
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
            throw new RuntimeException('AI provider returned an invalid review response.');
        }

        $markdown = trim((string) ($payload['markdown'] ?? ''));

        if ($markdown === '') {
            throw new RuntimeException('AI provider did not return corrected Markdown.');
        }

        $notes = [];

        foreach (is_array($payload['notes'] ?? null) ? $payload['notes'] : [] as $note) {
            if (is_string($note) && trim($note) !== '') {
                $notes[] = Str::limit(trim($note), 400);
            }
        }

        return [
            'markdown' => $markdown,
            'summary' => Str::limit(trim((string) ($payload['summary'] ?? '')), 600),
            'notes' => array_slice($notes, 0, 15),
        ];
    }

    public static function isConfigured(): bool
    {
        return PlatformAiConfig::isConfigured();
    }

    /**
     * @param  array{project: string, title: string, markdown: string, siblings: list<string>}  $context
     */
    private function buildPrompt(array $context, string $intent): string
    {
        $instruction = self::INTENTS[$intent]['instruction'] ?? self::INTENTS['accuracy']['instruction'];
        $siblings = $context['siblings'] === [] ? 'None' : implode(', ', $context['siblings']);

        return <<<PROMPT
Review a single documentation page and return a corrected version.

Project: {$context['project']}
Page title: {$context['title']}
Other pages in this documentation set: {$siblings}

Review focus:
{$instruction}

Current page Markdown, delimited by <<<PAGE and PAGE>>>:
<<<PAGE
{$context['markdown']}
PAGE>>>

Return JSON with this exact shape:
{
  "summary": "One or two sentences describing the overall state of the page",
  "notes": ["Each issue you found and why it matters, one per entry"],
  "markdown": "The full corrected page in Markdown"
}

Requirements:
- "markdown" must be the complete page, not a fragment or a diff.
- Preserve front matter, reusable block tags like {{block:slug}}, image paths, and code samples unless they are the problem.
- If the page is already correct, return it unchanged with an empty notes array.
- Do not wrap the Markdown in a code fence.
PROMPT;
    }
}
