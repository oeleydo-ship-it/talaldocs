<?php

namespace Tests\Unit;

use App\Support\Markdown;
use Tests\TestCase;

class MarkdownMediaTest extends TestCase
{
    public function test_renders_youtube_embed_with_responsive_wrapper(): void
    {
        $html = app(Markdown::class)->render('::video[youtube](dQw4w9WgXcQ)', 1);

        $this->assertStringContainsString('docs-video-aspect', $html);
        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $html);
    }

    public function test_renders_callout_boxes(): void
    {
        $html = app(Markdown::class)->render(":::tip\nHelpful hint\n:::", 1);

        $this->assertStringContainsString('docs-callout-tip', $html);
        $this->assertStringContainsString('Helpful hint', $html);
    }

    public function test_rejects_unsafe_iframe_sources(): void
    {
        $html = app(Markdown::class)->render("::embed\n<iframe src=\"https://evil.example/embed\"></iframe>\n::", 1);

        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('::embed', $html);
    }

    public function test_renders_external_image_urls(): void
    {
        $html = app(Markdown::class)->render('![Logo](https://example.com/logo.png)', 1);

        $this->assertStringContainsString('docs-image', $html);
        $this->assertStringContainsString('https://example.com/logo.png', $html);
    }
}
