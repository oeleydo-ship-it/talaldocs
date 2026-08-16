<?php

namespace App\Support;

use App\Models\Page;
use Illuminate\Support\Collection;

class DocsNavigation
{
    /**
     * @param  Collection<int, Page>  $pages
     * @return list<array{id: int, title: string, slug: string, children: list<mixed>}>
     */
    public function tree(Collection $pages): array
    {
        $ids = $pages->pluck('id')->all();
        $byParent = $pages->groupBy(function (Page $page) use ($ids): string {
            return $page->parent_id !== null && in_array($page->parent_id, $ids, true)
                ? (string) $page->parent_id
                : 'root';
        });

        $build = function (string $key) use (&$build, $byParent): array {
            $nodes = $byParent->get($key, collect())->sortBy('position')->values();

            return $nodes->map(function (Page $page) use ($build): array {
                return [
                    'id' => $page->id,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'children' => $build((string) $page->id),
                ];
            })->all();
        };

        return $build('root');
    }

    /**
     * Depth-first page order for prev/next navigation.
     *
     * @param  Collection<int, Page>  $pages
     * @return Collection<int, Page>
     */
    public function linearPages(Collection $pages): Collection
    {
        $ids = $pages->pluck('id')->all();
        $byParent = $pages->groupBy(function (Page $page) use ($ids): string {
            return $page->parent_id !== null && in_array($page->parent_id, $ids, true)
                ? (string) $page->parent_id
                : 'root';
        });
        $ordered = collect();

        $walk = function (string $key) use (&$walk, $byParent, &$ordered): void {
            $nodes = $byParent->get($key, collect())->sortBy('position')->values();

            foreach ($nodes as $page) {
                $ordered->push($page);
                $walk((string) $page->id);
            }
        };

        $walk('root');

        return $ordered;
    }

    /**
     * @param  Collection<int, Page>  $pages
     * @return list<array{title: string, items: list<array{id: int, title: string, slug: string}>}>
     */
    public function sidebarGroups(Collection $pages): array
    {
        $roots = $pages->whereNull('parent_id')->sortBy('position')->values();
        $groups = [];
        $ungrouped = [];

        foreach ($roots as $root) {
            $children = $pages->where('parent_id', $root->id)->sortBy('position')->values();

            if ($children->isNotEmpty()) {
                $groups[] = [
                    'title' => $root->title,
                    'items' => $children->map(fn (Page $page): array => $this->navItem($page))->all(),
                ];

                continue;
            }

            $ungrouped[] = $this->navItem($root);
        }

        if ($ungrouped !== []) {
            array_unshift($groups, [
                'title' => 'Documentation',
                'items' => $ungrouped,
            ]);
        }

        return $groups;
    }

    /**
     * @param  Collection<int, Page>  $pages
     * @return list<array{title: string, slug: string|null}>
     */
    public function breadcrumbs(Collection $pages, Page $current): array
    {
        $trail = [];
        $cursor = $current;

        while ($cursor instanceof Page) {
            array_unshift($trail, [
                'title' => $cursor->title,
                'slug' => $cursor->slug,
            ]);

            $cursor = $cursor->parent_id
                ? $pages->firstWhere('id', $cursor->parent_id)
                : null;
        }

        array_unshift($trail, [
            'title' => 'Documentation',
            'slug' => null,
        ]);

        return $trail;
    }

    /**
     * @return array{id: int, title: string, slug: string}
     */
    private function navItem(Page $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
        ];
    }
}
