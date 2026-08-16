<?php

namespace App\Support;

use App\Models\Block;
use Illuminate\Support\Str;

class Markdown
{
    /** @var list<string> */
    private const IFRAME_ALLOWLIST = [
        'youtube.com',
        'www.youtube.com',
        'youtube-nocookie.com',
        'www.youtube-nocookie.com',
        'player.vimeo.com',
        'vimeo.com',
        'www.vimeo.com',
        'loom.com',
        'www.loom.com',
    ];

    public function render(?string $markdown, int $workspaceId): string
    {
        $expanded = $this->expandBlocks((string) $markdown, $workspaceId);
        [$expanded, $embeds] = $this->extractEmbeds($expanded);
        [$expanded, $callouts] = $this->extractCallouts($expanded);

        $html = Str::markdown($expanded, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $html = $this->injectEmbeds($html, $embeds);
        $html = $this->injectCallouts($html, $callouts, $workspaceId);
        $html = $this->enhanceCodeBlocks($html);
        $html = $this->wrapImages($html);

        return $html;
    }

    public function enhanceCodeBlocks(string $html): string
    {
        return (string) preg_replace_callback(
            '/<pre><code(?: class="language-([^"]*)")?>(.*?)<\/code><\/pre>/si',
            function (array $match): string {
                $language = $match[1] ?? '';
                $code = html_entity_decode(strip_tags($match[2]));
                $encoded = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $langClass = $language !== '' ? ' language-'.htmlspecialchars($language, ENT_QUOTES, 'UTF-8') : '';

                return '<div class="docs-code-block" data-language="'.htmlspecialchars($language, ENT_QUOTES, 'UTF-8').'">'
                    .'<button type="button" class="docs-code-copy" aria-label="Copy code">Copy</button>'
                    .'<pre><code class="docs-code'.$langClass.'">'.$encoded.'</code></pre>'
                    .'</div>';
            },
            $html,
        );
    }

    public function expandBlocks(string $markdown, int $workspaceId): string
    {
        return (string) preg_replace_callback('/\{\{block:([a-z0-9\-]+)\}\}/i', function (array $matches) use ($workspaceId): string {
            $block = Block::query()
                ->withoutGlobalScopes()
                ->where('workspace_id', $workspaceId)
                ->where('slug', $matches[1])
                ->first();

            return $block?->markdown ?? '';
        }, $markdown);
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function extractEmbeds(string $markdown): array
    {
        $embeds = [];
        $index = 0;

        $markdown = (string) preg_replace_callback(
            '/::video\[(\w+)\]\(([^)]+)\)/i',
            function (array $match) use (&$embeds, &$index): string {
                $provider = strtolower($match[1]);
                $value = trim($match[2]);
                $html = $this->buildVideoEmbed($provider, $value);

                if ($html === null) {
                    return $match[0];
                }

                $placeholder = '%%ANYTDOCS_EMBED_'.$index.'%%';
                $embeds[$index] = $html;
                $index++;

                return "\n\n".$placeholder."\n\n";
            },
            $markdown,
        );

        $markdown = (string) preg_replace_callback(
            '/::embed\s*\n([\s\S]*?)\n::/i',
            function (array $match) use (&$embeds, &$index): string {
                $html = $this->sanitizeRawEmbed(trim($match[1]));

                if ($html === null) {
                    return $match[0];
                }

                $placeholder = '%%ANYTDOCS_EMBED_'.$index.'%%';
                $embeds[$index] = $html;
                $index++;

                return "\n\n".$placeholder."\n\n";
            },
            $markdown,
        );

        return [$markdown, $embeds];
    }

    /**
     * @return array{0: string, 1: list<array{type: string, body: string}>}
     */
    private function extractCallouts(string $markdown): array
    {
        $callouts = [];
        $index = 0;

        $markdown = (string) preg_replace_callback(
            '/:::(tip|warning|info|note)\s*\n([\s\S]*?)\n:::/i',
            function (array $match) use (&$callouts, &$index): string {
                $placeholder = '%%ANYTDOCS_CALLOUT_'.$index.'%%';
                $callouts[$index] = [
                    'type' => strtolower($match[1]),
                    'body' => trim($match[2]),
                ];
                $index++;

                return "\n\n".$placeholder."\n\n";
            },
            $markdown,
        );

        return [$markdown, $callouts];
    }

    /**
     * @param  list<string>  $embeds
     */
    private function injectEmbeds(string $html, array $embeds): string
    {
        foreach ($embeds as $index => $embedHtml) {
            $html = str_replace(
                '<p>%%ANYTDOCS_EMBED_'.$index.'%%</p>',
                $embedHtml,
                $html,
            );
            $html = str_replace('%%ANYTDOCS_EMBED_'.$index.'%%', $embedHtml, $html);
        }

        return $html;
    }

    /**
     * @param  list<array{type: string, body: string}>  $callouts
     */
    private function injectCallouts(string $html, array $callouts, int $workspaceId): string
    {
        foreach ($callouts as $index => $callout) {
            $inner = $this->render($callout['body'], $workspaceId);
            $label = ucfirst($callout['type']);
            $type = htmlspecialchars($callout['type'], ENT_QUOTES, 'UTF-8');
            $block = '<div class="docs-callout docs-callout-'.$type.'" role="note">'
                .'<p class="docs-callout-title">'.$label.'</p>'
                .'<div class="docs-callout-body">'.$inner.'</div>'
                .'</div>';

            $html = str_replace(
                '<p>%%ANYTDOCS_CALLOUT_'.$index.'%%</p>',
                $block,
                $html,
            );
            $html = str_replace('%%ANYTDOCS_CALLOUT_'.$index.'%%', $block, $html);
        }

        return $html;
    }

    private function buildVideoEmbed(string $provider, string $value): ?string
    {
        return match ($provider) {
            'youtube' => $this->youtubeEmbed($value),
            'vimeo' => $this->vimeoEmbed($value),
            'loom' => $this->loomEmbed($value),
            'url', 'file' => $this->videoFileEmbed($value),
            default => null,
        };
    }

    private function youtubeEmbed(string $value): ?string
    {
        $id = $this->parseYoutubeId($value);

        if ($id === null) {
            return null;
        }

        $src = 'https://www.youtube-nocookie.com/embed/'.rawurlencode($id);

        return $this->iframeWrapper($src, 'YouTube video');
    }

    private function vimeoEmbed(string $value): ?string
    {
        $id = $this->parseVimeoId($value);

        if ($id === null) {
            return null;
        }

        $src = 'https://player.vimeo.com/video/'.rawurlencode($id);

        return $this->iframeWrapper($src, 'Vimeo video');
    }

    private function loomEmbed(string $value): ?string
    {
        if (preg_match('/loom\.com\/(?:share|embed)\/([a-zA-Z0-9]+)/', $value, $match)) {
            $src = 'https://www.loom.com/embed/'.rawurlencode($match[1]);

            return $this->iframeWrapper($src, 'Loom video');
        }

        if (preg_match('/^[a-zA-Z0-9]+$/', $value)) {
            $src = 'https://www.loom.com/embed/'.rawurlencode($value);

            return $this->iframeWrapper($src, 'Loom video');
        }

        return null;
    }

    private function videoFileEmbed(string $value): ?string
    {
        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        $safe = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        return '<div class="docs-video"><video class="docs-video-native" controls preload="metadata" src="'.$safe.'"></video></div>';
    }

    private function iframeWrapper(string $src, string $title): ?string
    {
        if (! $this->isAllowedIframeSrc($src)) {
            return null;
        }

        $safeSrc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return '<div class="docs-video"><div class="docs-video-aspect">'
            .'<iframe src="'.$safeSrc.'" title="'.$safeTitle.'" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>'
            .'</div></div>';
    }

    private function sanitizeRawEmbed(string $raw): ?string
    {
        if (! preg_match('/<iframe\b[^>]*\ssrc=["\']([^"\']+)["\'][^>]*>/i', $raw, $match)) {
            return null;
        }

        $src = html_entity_decode($match[1]);

        if (! $this->isAllowedIframeSrc($src)) {
            return null;
        }

        return $this->iframeWrapper($src, 'Embedded video');
    }

    private function isAllowedIframeSrc(string $src): bool
    {
        $host = parse_url($src, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);

        foreach (self::IFRAME_ALLOWLIST as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    private function parseYoutubeId(string $value): ?string
    {
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $value)) {
            return $value;
        }

        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $value, $match)) {
            return $match[1];
        }

        return null;
    }

    private function parseVimeoId(string $value): ?string
    {
        if (preg_match('/^\d+$/', $value)) {
            return $value;
        }

        if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $value, $match)) {
            return $match[1];
        }

        return null;
    }

    private function wrapImages(string $html): string
    {
        return (string) preg_replace_callback(
            '/<img\b([^>]*?)>/i',
            function (array $match): string {
                $tag = $match[0];

                if (str_contains($tag, 'docs-image')) {
                    return $tag;
                }

                if (preg_match('/\ssrc=["\']([^"\']+)["\']/i', $tag, $srcMatch)) {
                    $src = $srcMatch[1];

                    if (! filter_var($src, FILTER_VALIDATE_URL) && ! str_starts_with($src, '/')) {
                        return $tag;
                    }
                }

                return '<figure class="docs-image">'.$tag.'</figure>';
            },
            $html,
        );
    }

    /**
     * @return list<array{id: string, text: string, level: int}>
     */
    public function tableOfContents(string $html): array
    {
        preg_match_all('/<h([2-4])([^>]*)>(.*?)<\/h\1>/si', $html, $matches, PREG_SET_ORDER);

        $items = [];

        foreach ($matches as $index => $match) {
            $text = trim(html_entity_decode(strip_tags($match[3])));

            if ($text === '') {
                continue;
            }

            $id = $this->headingIdFromAttributes($match[2]) ?? Str::slug($text).'-'.$index;

            $items[] = [
                'id' => $id,
                'text' => $text,
                'level' => (int) $match[1],
            ];
        }

        return $items;
    }

    public function injectHeadingIds(string $html): string
    {
        $index = 0;

        return (string) preg_replace_callback('/<h([2-4])([^>]*)>(.*?)<\/h\1>/si', function (array $match) use (&$index): string {
            if ($this->headingIdFromAttributes($match[2]) !== null) {
                $index++;

                return $match[0];
            }

            $text = trim(html_entity_decode(strip_tags($match[3])));
            $id = Str::slug($text !== '' ? $text : 'section').'-'.$index;
            $index++;

            return '<h'.$match[1].' id="'.$id.'"'.$match[2].'>'.$match[3].'</h'.$match[1].'>';
        }, $html);
    }

    private function headingIdFromAttributes(string $attributes): ?string
    {
        if (preg_match('/\bid=(["\'])([^"\']+)\1/', $attributes, $idMatch)) {
            return $idMatch[2];
        }

        return null;
    }
}
