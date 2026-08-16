<?php

namespace App\Services;

use App\Support\UrlSafetyValidator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class WebsiteAnalyzer
{
    public function __construct(private UrlSafetyValidator $urlSafety) {}

    /**
     * @return array{
     *     url: string,
     *     title: string|null,
     *     description: string|null,
     *     og_title: string|null,
     *     og_description: string|null,
     *     headings: list<string>,
     *     text: string
     * }
     */
    public function analyze(string $url): array
    {
        $normalizedUrl = $this->normalizeUrl($url);
        $this->urlSafety->assertSafe($normalizedUrl);
        $this->assertAllowedByRobots($normalizedUrl);

        $response = Http::timeout((int) config('ai.fetch_timeout'))
            ->withHeaders([
                'User-Agent' => (string) config('ai.user_agent'),
                'Accept' => 'text/html,application/xhtml+xml',
            ])
            ->get($normalizedUrl);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'url' => 'Could not fetch the website (HTTP '.$response->status().').',
            ]);
        }

        $body = (string) $response->body();
        $maxBytes = (int) config('ai.max_fetch_bytes');

        if (strlen($body) > $maxBytes) {
            $body = substr($body, 0, $maxBytes);
        }

        return $this->extractContent($normalizedUrl, $body);
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (! Str::startsWith(strtolower($url), ['http://', 'https://'])) {
            $url = 'https://'.$url;
        }

        return $url;
    }

    private function assertAllowedByRobots(string $url): void
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return;
        }

        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
        $path = $parts['path'] ?? '/';

        $robotsUrl = rtrim($origin, '/').'/robots.txt';

        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => (string) config('ai.user_agent')])
                ->get($robotsUrl);
        } catch (RuntimeException) {
            return;
        }

        if (! $response->successful()) {
            return;
        }

        $rules = $this->parseRobotsRules((string) $response->body());

        foreach ($rules as $rule) {
            if ($this->robotsDisallowsPath($rule, $path)) {
                throw ValidationException::withMessages([
                    'url' => 'This URL is blocked by the site robots.txt policy.',
                ]);
            }
        }
    }

    /**
     * @return list<array{path: string, allow: bool}>
     */
    private function parseRobotsRules(string $content): array
    {
        $rules = [];
        $applies = false;

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');

            if ($line === '') {
                continue;
            }

            if (preg_match('/^user-agent:\s*(.+)$/i', $line, $matches) === 1) {
                $agent = strtolower(trim($matches[1]));
                $botToken = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) config('app.name', 'Docs')) ?: 'docs');
                $applies = $agent === '*'
                    || str_contains($agent, $botToken)
                    || str_contains($agent, 'docsbot');

                continue;
            }

            if (! $applies) {
                continue;
            }

            if (preg_match('/^(disallow|allow):\s*(.*)$/i', $line, $matches) === 1) {
                $rules[] = [
                    'path' => trim($matches[2]),
                    'allow' => strtolower($matches[1]) === 'allow',
                ];
            }
        }

        return $rules;
    }

    /**
     * @param  array{path: string, allow: bool}  $rule
     */
    private function robotsDisallowsPath(array $rule, string $path): bool
    {
        if ($rule['path'] === '') {
            return ! $rule['allow'];
        }

        $matches = Str::startsWith($path, $rule['path']);

        if (! $matches) {
            return false;
        }

        return ! $rule['allow'];
    }

    /**
     * @return array{
     *     url: string,
     *     title: string|null,
     *     description: string|null,
     *     og_title: string|null,
     *     og_description: string|null,
     *     headings: list<string>,
     *     text: string
     * }
     */
    private function extractContent(string $url, string $html): array
    {
        $document = new \DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $title = $this->nodeText($document->getElementsByTagName('title')->item(0));
        $description = $this->metaContent($document, 'description');
        $ogTitle = $this->metaProperty($document, 'og:title');
        $ogDescription = $this->metaProperty($document, 'og:description');

        $headings = [];

        foreach (['h1', 'h2', 'h3'] as $tag) {
            foreach ($document->getElementsByTagName($tag) as $node) {
                $text = trim(preg_replace('/\s+/u', ' ', $node->textContent ?? '') ?? '');

                if ($text !== '') {
                    $headings[] = $text;
                }
            }
        }

        $this->stripUnwantedNodes($document);

        $text = trim(preg_replace('/\s+/u', ' ', $document->textContent ?? '') ?? '');

        if (strlen($text) > 12000) {
            $text = substr($text, 0, 12000);
        }

        return [
            'url' => $url,
            'title' => $title,
            'description' => $description,
            'og_title' => $ogTitle,
            'og_description' => $ogDescription,
            'headings' => array_values(array_unique($headings)),
            'text' => $text,
        ];
    }

    private function stripUnwantedNodes(\DOMDocument $document): void
    {
        $removeTags = ['script', 'style', 'noscript', 'svg', 'iframe'];

        foreach ($removeTags as $tag) {
            while (($nodes = $document->getElementsByTagName($tag))->length > 0) {
                $node = $nodes->item(0);

                if ($node?->parentNode !== null) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
    }

    private function nodeText(?\DOMNode $node): ?string
    {
        if ($node === null) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', $node->textContent ?? '') ?? '');

        return $text === '' ? null : $text;
    }

    private function metaContent(\DOMDocument $document, string $name): ?string
    {
        foreach ($document->getElementsByTagName('meta') as $meta) {
            if (! $meta instanceof \DOMElement) {
                continue;
            }

            if (strtolower($meta->getAttribute('name')) === strtolower($name)) {
                $content = trim($meta->getAttribute('content'));

                return $content === '' ? null : $content;
            }
        }

        return null;
    }

    private function metaProperty(\DOMDocument $document, string $property): ?string
    {
        foreach ($document->getElementsByTagName('meta') as $meta) {
            if (! $meta instanceof \DOMElement) {
                continue;
            }

            if (strtolower($meta->getAttribute('property')) === strtolower($property)) {
                $content = trim($meta->getAttribute('content'));

                return $content === '' ? null : $content;
            }
        }

        return null;
    }
}
