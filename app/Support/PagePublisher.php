<?php

namespace App\Support;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Services\DocsIndexService;

class PagePublisher
{
    public function __construct(
        private Markdown $markdown,
        private DocsIndexService $index,
    ) {}

    public function saveDraft(Page $page, string $title, string $slug, string $markdown, ?User $user = null, bool $revision = false, ?string $subtitle = null): Page
    {
        $html = $this->markdown->render($markdown, $page->workspace_id);

        $page->fill([
            'title' => $title,
            'subtitle' => $subtitle,
            'slug' => $slug,
            'markdown' => $markdown,
            'html' => $html,
        ])->save();

        if ($revision) {
            $this->revision($page, $user, 'save');
        }

        return $page->refresh();
    }

    public function publish(Page $page, ?User $user = null): Page
    {
        $html = $this->markdown->injectHeadingIds(
            $this->markdown->render((string) $page->markdown, $page->workspace_id),
        );

        $page->forceFill([
            'html' => $html,
            'published_markdown' => $page->markdown,
            'published_html' => $html,
            'status' => PageStatus::Published,
            'published_at' => now(),
        ])->save();

        $this->revision($page, $user, 'publish');

        $this->index->indexPage($page->refresh());

        return $page;
    }

    public function unpublish(Page $page, ?User $user = null): Page
    {
        $page->forceFill([
            'status' => PageStatus::Draft,
            'published_at' => null,
        ])->save();

        $this->revision($page, $user, 'unpublish');

        $this->index->removePage($page->refresh());

        return $page;
    }

    public function restoreRevision(Page $page, PageRevision $revision, ?User $user = null): Page
    {
        return $this->saveDraft(
            $page,
            $page->title,
            $page->slug,
            (string) $revision->markdown,
            $user,
            true,
        );
    }

    public function recordRevision(Page $page, ?User $user, string $event): void
    {
        $this->revision($page, $user, $event);
    }

    private function revision(Page $page, ?User $user, string $event): void
    {
        PageRevision::query()->create([
            'workspace_id' => $page->workspace_id,
            'page_id' => $page->id,
            'user_id' => $user?->id,
            'markdown' => $page->markdown,
            'html' => $page->html,
            'event' => $event,
        ]);
    }
}
