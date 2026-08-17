<?php

namespace App\Support;

use App\Models\PlatformSetting;
use App\Models\Project;
use Illuminate\Support\Str;

class PlatformPublicContent
{
    /**
     * @return array{
     *     home: array{eyebrow: string, heading: string, tagline: string},
     *     examples: array{eyebrow: string, heading: string, intro: string, demos_heading: string, demos_description: string},
     *     features: array{eyebrow: string, heading: string, intro: string}
     * }
     */
    public static function defaults(?string $appName = null): array
    {
        $appName ??= PlatformConfig::appName();

        return [
            'home' => [
                'eyebrow' => 'Documentation platform for modern product teams',
                'heading' => 'Publish docs your customers will actually read.',
                'tagline' => 'Beautiful documentation for software teams. Publish on your subdomain, bring your own domain, and ship faster with '.$appName.'.',
            ],
            'examples' => [
                'eyebrow' => 'Examples',
                'heading' => 'Docs that feel premium out of the box',
                'intro' => $appName.' ships readable typography, nested navigation, branded themes, search, and optional Ask AI — so your public site looks polished on day one.',
                'demos_heading' => 'Live demo sites',
                'demos_description' => 'Published documentation from seeded workspaces on this instance.',
            ],
            'features' => [
                'eyebrow' => 'Features',
                'heading' => 'Everything you need to ship docs',
                'intro' => 'From first draft to custom domain — '.$appName.' covers authoring, beautiful public sites, AI assistance, announcements, analytics, and team collaboration.',
            ],
        ];
    }

    /**
     * Stored values plus resolved copy and demo cards for the platform admin form.
     *
     * @return array<string, mixed>
     */
    public static function forAdmin(): array
    {
        $stored = self::stored();
        $defaults = self::defaults();
        $managed = (bool) ($stored['demos_managed'] ?? false);
        $demos = $managed
            ? self::sanitizeDemos(is_array($stored['demos'] ?? null) ? $stored['demos'] : [])
            : self::discoveredDemos();

        return [
            'copy' => [
                'home' => self::storedSection($stored['home'] ?? null, ['eyebrow', 'heading', 'tagline']),
                'examples' => self::storedSection($stored['examples'] ?? null, [
                    'eyebrow',
                    'heading',
                    'intro',
                    'demos_heading',
                    'demos_description',
                ]),
                'features' => self::storedSection($stored['features'] ?? null, ['eyebrow', 'heading', 'intro']),
            ],
            'defaults' => $defaults,
            'demos' => $demos,
            'demos_managed' => $managed,
        ];
    }

    /**
     * Resolved marketing copy with hardcoded fallbacks when a field is empty.
     *
     * @return array{
     *     home: array{eyebrow: string, heading: string, tagline: string},
     *     examples: array{eyebrow: string, heading: string, intro: string, demos_heading: string, demos_description: string},
     *     features: array{eyebrow: string, heading: string, intro: string}
     * }
     */
    public static function forMarketing(): array
    {
        $stored = self::stored();
        $defaults = self::defaults();
        $brandingTagline = PlatformConfig::brandingForFrontend()['tagline'] ?? null;

        if (filled($brandingTagline) && ! filled(data_get($stored, 'home.tagline'))) {
            $defaults['home']['tagline'] = (string) $brandingTagline;
        }

        return [
            'home' => self::mergeSection($defaults['home'], $stored['home'] ?? null),
            'examples' => self::mergeSection($defaults['examples'], $stored['examples'] ?? null),
            'features' => self::mergeSection($defaults['features'], $stored['features'] ?? null),
        ];
    }

    /**
     * Public Examples page demo cards: custom list when managed, otherwise published projects.
     *
     * @return list<array{id: string, name: string, subdomain: string, layout: string, url: string}>
     */
    public static function demoSites(): array
    {
        $stored = self::stored();

        if ((bool) ($stored['demos_managed'] ?? false)) {
            $demos = self::sanitizeDemos(is_array($stored['demos'] ?? null) ? $stored['demos'] : []);

            return array_values(array_map(
                fn (array $demo): array => [
                    'id' => $demo['id'],
                    'name' => $demo['title'],
                    'subdomain' => $demo['subtitle'],
                    'layout' => $demo['badge'],
                    'url' => $demo['url'],
                ],
                array_values(array_filter($demos, fn (array $demo): bool => $demo['enabled'])),
            ));
        }

        return self::discoveredDemos();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function sanitize(array $payload): array
    {
        $copyKeys = [
            'home' => ['eyebrow', 'heading', 'tagline'],
            'examples' => ['eyebrow', 'heading', 'intro', 'demos_heading', 'demos_description'],
            'features' => ['eyebrow', 'heading', 'intro'],
        ];

        $copy = [];

        foreach ($copyKeys as $section => $fields) {
            $copy[$section] = self::storedSection($payload[$section] ?? null, $fields);
        }

        return [
            ...$copy,
            'demos_managed' => filter_var($payload['demos_managed'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'demos' => self::sanitizeDemos(is_array($payload['demos'] ?? null) ? $payload['demos'] : []),
        ];
    }

    public static function isValidDemoUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return strlen($url) <= 500;
        }

        $candidate = preg_match('#^https?://#i', $url) === 1 ? $url : 'https://'.$url;

        return filter_var($candidate, FILTER_VALIDATE_URL) !== false;
    }

    public static function normalizeDemoUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return $url;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return 'https://'.ltrim($url, '/');
    }

    /**
     * @param  list<mixed>  $demos
     * @return list<array{id: string, title: string, subtitle: string, badge: string, url: string, sort_order: int, enabled: bool}>
     */
    public static function sanitizeDemos(array $demos): array
    {
        $clean = [];

        foreach (array_values($demos) as $index => $demo) {
            if (! is_array($demo)) {
                continue;
            }

            $title = trim((string) ($demo['title'] ?? ''));
            $url = trim((string) ($demo['url'] ?? ''));

            if ($title === '' || $url === '') {
                continue;
            }

            $badge = strtolower(trim((string) ($demo['badge'] ?? 'classic')));

            if (! in_array($badge, ['classic', 'gitbook'], true)) {
                $badge = 'classic';
            }

            $id = trim((string) ($demo['id'] ?? ''));

            if ($id === '' || strlen($id) > 64) {
                $id = (string) Str::uuid();
            }

            $clean[] = [
                'id' => $id,
                'title' => Str::limit($title, 120, ''),
                'subtitle' => Str::limit(trim((string) ($demo['subtitle'] ?? '')), 120, ''),
                'badge' => $badge,
                'url' => self::normalizeDemoUrl(Str::limit($url, 500, '')),
                'sort_order' => (int) ($demo['sort_order'] ?? $index),
                'enabled' => filter_var($demo['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        usort($clean, fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

        foreach ($clean as $index => &$row) {
            $row['sort_order'] = $index;
        }

        unset($row);

        return array_values($clean);
    }

    /**
     * @return list<array{id: string, name: string, subdomain: string, layout: string, url: string, title?: string, subtitle?: string, badge?: string, sort_order?: int, enabled?: bool}>
     */
    public static function discoveredDemos(): array
    {
        return Project::query()
            ->withoutGlobalScopes()
            ->whereHas('pages', fn ($query) => $query->whereNotNull('published_at'))
            ->latest('id')
            ->limit(6)
            ->get(['id', 'name', 'subdomain', 'docs_template'])
            ->values()
            ->map(function (Project $project, int $index): array {
                $layout = $project->docs_template?->value ?? 'classic';

                return [
                    'id' => 'project-'.$project->id,
                    'name' => $project->name,
                    'subdomain' => $project->subdomain,
                    'layout' => $layout,
                    'url' => $project->docsBasePath(),
                    'title' => $project->name,
                    'subtitle' => $project->subdomain,
                    'badge' => $layout,
                    'sort_order' => $index,
                    'enabled' => true,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function stored(): array
    {
        if (! PlatformConfig::tableAvailable()) {
            return [];
        }

        $content = PlatformSetting::instance()->public_content;

        return is_array($content) ? $content : [];
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, string>
     */
    private static function storedSection(mixed $section, array $fields): array
    {
        $section = is_array($section) ? $section : [];
        $out = [];

        foreach ($fields as $field) {
            $value = $section[$field] ?? '';
            $out[$field] = is_string($value) ? trim($value) : '';
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $defaults
     * @return array<string, string>
     */
    private static function mergeSection(array $defaults, mixed $override): array
    {
        $override = is_array($override) ? $override : [];
        $out = [];

        foreach ($defaults as $key => $value) {
            $candidate = $override[$key] ?? null;
            $out[$key] = is_string($candidate) && trim($candidate) !== '' ? trim($candidate) : $value;
        }

        return $out;
    }
}
