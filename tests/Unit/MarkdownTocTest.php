<?php

namespace Tests\Unit;

use App\Support\Markdown;
use Tests\TestCase;

class MarkdownTocTest extends TestCase
{
    public function test_table_of_contents_extracts_h2_and_h3_headings(): void
    {
        $markdown = app(Markdown::class);
        $html = $markdown->injectHeadingIds($markdown->render("## Install\n\nSteps\n\n### Requirements\n\nDetails", 1));

        $toc = $markdown->tableOfContents($html);

        $this->assertCount(2, $toc);
        $this->assertSame('Install', $toc[0]['text']);
        $this->assertSame(2, $toc[0]['level']);
        $this->assertSame('Requirements', $toc[1]['text']);
        $this->assertSame(3, $toc[1]['level']);
        $this->assertSame($toc[0]['id'], 'install-0');
        $this->assertStringContainsString('id="'.$toc[0]['id'].'"', $html);
        $this->assertStringContainsString('id="'.$toc[1]['id'].'"', $html);
    }

    public function test_table_of_contents_is_empty_without_section_headings(): void
    {
        $markdown = app(Markdown::class);
        $html = $markdown->injectHeadingIds($markdown->render("# Page title\n\nPlain paragraph.", 1));

        $this->assertSame([], $markdown->tableOfContents($html));
    }

    public function test_inject_heading_ids_is_idempotent_for_published_html(): void
    {
        $markdown = app(Markdown::class);
        $published = '<h2 id="install-0">Install</h2><p>Text</p><h3 id="requirements-1">Requirements</h3>';

        $html = $markdown->injectHeadingIds($published);

        $this->assertSame($published, $html);
        $this->assertSame([
            ['id' => 'install-0', 'text' => 'Install', 'level' => 2],
            ['id' => 'requirements-1', 'text' => 'Requirements', 'level' => 3],
        ], $markdown->tableOfContents($html));
    }
}
