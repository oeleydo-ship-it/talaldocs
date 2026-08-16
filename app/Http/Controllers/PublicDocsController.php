<?php

namespace App\Http\Controllers;

use App\Enums\PageStatus;
use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\ProjectVisibility;
use App\Models\DocumentationVersion;
use App\Models\Feedback;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageView;
use App\Models\Post;
use App\Models\Project;
use App\Models\ProjectLanguage;
use App\Models\Scopes\WorkspaceScope;
use App\Support\DocsNavigation;
use App\Support\Markdown;
use App\Support\PageSearch;
use App\Support\PlanGate;
use App\Support\PublicProjectResolver;
use App\Services\DocsAskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PublicDocsController extends Controller
{
    public function __construct(
        private PublicProjectResolver $resolver,
        private Markdown $markdown,
        private PageSearch $search,
        private DocsNavigation $navigation,
        private PlanGate $plans,
        private DocsAskService $docsAsk,
    ) {}

    public function show(Request $request, ?string $project = null, ?string $docVersion = null, ?string $docLocale = null, ?string $docSlug = null): Response|RedirectResponse|HttpResponse
    {
        $model = $this->resolver->fromRequest($request, $project);
        abort_unless($model instanceof Project, 404);

        if ($gate = $this->guard($request, $model)) {
            return $gate;
        }

        $versionModel = $this->version($model, $docVersion);
        $language = $this->language($model, $docLocale);

        $pages = Page::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $model->id)
            ->where('documentation_version_id', $versionModel->id)
            ->where('language_id', $language->id)
            ->where('status', PageStatus::Published)
            ->where(fn ($query) => $query->where('hidden', false)->orWhereNull('hidden'))
            ->orderBy('position')
            ->get();

        abort_if($pages->isEmpty(), 404);

        $current = $docSlug
            ? $pages->firstWhere('slug', $docSlug)
            : $pages->first();

        abort_unless($current instanceof Page, 404);

        $html = $this->markdown->injectHeadingIds((string) $current->published_html);
        $toc = $this->markdown->tableOfContents($html);
        $linear = $this->navigation->linearPages($pages);
        $position = $linear->search(fn (Page $page): bool => $page->id === $current->id);
        $prev = $position > 0 ? $linear[$position - 1] : null;
        $next = $position !== false && $position < $linear->count() - 1 ? $linear[$position + 1] : null;

        $this->recordView($request, $model, $current);

        $hreflang = Page::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $model->id)
            ->where('documentation_version_id', $versionModel->id)
            ->where('slug', $current->slug)
            ->where('status', PageStatus::Published)
            ->get()
            ->map(function (Page $page) use ($model, $versionModel): array {
                $code = Language::query()->find($page->language_id)?->code ?? 'en';

                return [
                    'hreflang' => $code,
                    'href' => $this->docUrl($model, $versionModel->slug, $code, $page->slug),
                ];
            });

        $workspace = $model->workspace()->first();
        $sidebarGroups = $this->navigation->sidebarGroups($pages);
        $navTree = $this->navigation->tree($pages);
        $breadcrumbs = $this->navigation->breadcrumbs($pages, $current);

        return Inertia::render('docs/show', array_merge($this->shellProps($model, $workspace), [
            'docsLayout' => $model->docs_layout->value,
            'canonicalUrl' => $this->docUrl($model, $versionModel->slug, $language->code, $current->slug),
            'version' => $versionModel->only(['id', 'name', 'slug']),
            'locale' => $language->only(['id', 'code', 'name']),
            'versions' => DocumentationVersion::query()->withoutGlobalScope(WorkspaceScope::class)->where('project_id', $model->id)->get(['id', 'name', 'slug', 'is_default']),
            'languages' => ProjectLanguage::query()->withoutGlobalScope(WorkspaceScope::class)->with('language')->where('project_id', $model->id)->get()
                ->map(fn (ProjectLanguage $row): array => [
                    'code' => $row->language?->code,
                    'name' => $row->language?->name,
                ]),
            'pages' => $pages->map(fn (Page $page): array => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'parent_id' => $page->parent_id,
            ]),
            'page' => [
                'id' => $current->id,
                'title' => $current->title,
                'subtitle' => $current->subtitle,
                'slug' => $current->slug,
                'html' => $html,
                'updated_at' => $current->updated_at?->toIso8601String(),
            ],
            'sidebarGroups' => $sidebarGroups,
            'navTree' => $navTree,
            'breadcrumbs' => $breadcrumbs,
            'toc' => $toc,
            'prev' => $prev ? ['title' => $prev->title, 'slug' => $prev->slug] : null,
            'next' => $next ? ['title' => $next->title, 'slug' => $next->slug] : null,
            'hreflang' => $hreflang,
            'basePath' => $model->docsBasePath().'/'.$versionModel->slug.'/'.$language->code,
            'feedbackUrl' => $model->docsBasePath().'/feedback',
            'aiAskEnabled' => DocsAskService::isConfigured(),
            'askUrl' => $model->docsBasePath().'/ask',
            'searchUrl' => $model->docsBasePath().'/search',
        ]));
    }

    public function directory(Request $request, string $project): Response|RedirectResponse|HttpResponse
    {
        $model = $this->resolver->fromRequest($request, $project);
        abort_unless($model instanceof Project, 404);

        if ($gate = $this->guard($request, $model)) {
            return $gate;
        }

        $workspace = $model->workspace()->first();
        $docsHomeUrl = $this->docsHomeUrl($model);
        $docPages = $this->publishedPages($model)
            ->take(24)
            ->map(fn (Page $page): array => [
                'title' => $page->title,
                'slug' => $page->slug,
                'url' => $this->pagePublicUrl($model, $page),
            ])
            ->values()
            ->all();

        $announcements = $this->publishedPosts($model, PostType::Announcement)
            ->take(12)
            ->map(fn (Post $post): array => $this->serializePublicPost($model, $post))
            ->values()
            ->all();

        $changelog = $this->publishedPosts($model, PostType::Changelog)
            ->take(12)
            ->map(fn (Post $post): array => $this->serializePublicPost($model, $post))
            ->values()
            ->all();

        return Inertia::render('docs/directory', array_merge($this->shellProps($model, $workspace), [
            'docsHomeUrl' => $docsHomeUrl,
            'sections' => [
                [
                    'key' => 'documentation',
                    'title' => 'Documentation',
                    'description' => 'Browse published documentation pages.',
                    'viewAllUrl' => $docsHomeUrl,
                    'items' => $docPages,
                ],
                [
                    'key' => 'announcements',
                    'title' => 'Announcements',
                    'description' => 'Product updates and important notices.',
                    'viewAllUrl' => $model->docsBasePath().'/announcements',
                    'items' => $announcements,
                ],
                [
                    'key' => 'changelog',
                    'title' => 'Changelog',
                    'description' => 'Release notes and version history.',
                    'viewAllUrl' => $model->docsBasePath().'/changelog',
                    'items' => $changelog,
                ],
            ],
        ]));
    }

    public function announcements(Request $request, string $project): Response|RedirectResponse|HttpResponse
    {
        return $this->postIndex($request, $project, PostType::Announcement, 'docs/announcements/index', 'Announcements');
    }

    public function announcement(Request $request, string $project, string $slug): Response|RedirectResponse|HttpResponse
    {
        return $this->postShow($request, $project, PostType::Announcement, $slug, 'docs/announcements/show');
    }

    public function changelog(Request $request, string $project): Response|RedirectResponse|HttpResponse
    {
        return $this->postIndex($request, $project, PostType::Changelog, 'docs/changelog/index', 'Changelog');
    }

    public function changelogShow(Request $request, string $project, string $slug): Response|RedirectResponse|HttpResponse
    {
        return $this->postShow($request, $project, PostType::Changelog, $slug, 'docs/changelog/show');
    }

    private function postIndex(
        Request $request,
        string $project,
        PostType $type,
        string $component,
        string $heading,
    ): Response|RedirectResponse|HttpResponse {
        $model = $this->resolver->fromRequest($request, $project);
        abort_unless($model instanceof Project, 404);

        if ($gate = $this->guard($request, $model)) {
            return $gate;
        }

        $workspace = $model->workspace()->first();
        $posts = $this->publishedPosts($model, $type)
            ->map(fn (Post $post): array => $this->serializePublicPost($model, $post))
            ->values()
            ->all();

        return Inertia::render($component, array_merge($this->shellProps($model, $workspace), [
            'heading' => $heading,
            'posts' => $posts,
        ]));
    }

    private function postShow(
        Request $request,
        string $project,
        PostType $type,
        string $slug,
        string $component,
    ): Response|RedirectResponse|HttpResponse {
        $model = $this->resolver->fromRequest($request, $project);
        abort_unless($model instanceof Project, 404);

        if ($gate = $this->guard($request, $model)) {
            return $gate;
        }

        $workspace = $model->workspace()->first();
        $post = Post::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $model->id)
            ->where('type', $type)
            ->where('status', PostStatus::Published)
            ->where('slug', $slug)
            ->firstOrFail();

        return Inertia::render($component, array_merge($this->shellProps($model, $workspace), [
            'post' => [
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'html' => (string) $post->html,
                'published_at' => $post->published_at?->toIso8601String(),
                'type' => $post->type->value,
            ],
            'listUrl' => $model->docsBasePath().'/'.($type === PostType::Announcement ? 'announcements' : 'changelog'),
        ]));
    }

    public function search(Request $request, string $project): \Illuminate\Http\JsonResponse
    {
        $model = $this->resolver->fromRequest($request, $project);
        abort_unless($model instanceof Project, 404);

        if ($gate = $this->guard($request, $model)) {
            abort(403);
        }

        $q = trim((string) $request->string('q'));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $versionId = $request->integer('version_id') ?: null;
        $languageId = $request->integer('language_id') ?: null;

        $results = $this->search
            ->publishedForProject($model->id, $q, $versionId, $languageId)
            ->orderByRaw('CASE WHEN title LIKE ? THEN 0 ELSE 1 END', ['%'.$q.'%'])
            ->orderBy('title')
            ->limit(12)
            ->get(['id', 'title', 'slug', 'published_markdown', 'published_html', 'documentation_version_id', 'language_id'])
            ->map(fn (Page $page): array => [
                'title' => $page->title,
                'slug' => $page->slug,
                'excerpt' => $this->search->excerpt($page, $q),
            ])
            ->values();

        return response()->json($results);
    }

    public function ask(Request $request, string $project): \Illuminate\Http\JsonResponse
    {
        $model = $this->resolver->fromRequest($request, $project);
        abort_unless($model instanceof Project, 404);

        if ($gate = $this->guard($request, $model)) {
            abort(403);
        }

        if (! DocsAskService::isConfigured()) {
            return response()->json([
                'message' => 'AI Ask is not configured. Enable AI in Platform → Settings.',
            ], 503);
        }

        $data = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $result = $this->docsAsk->ask($model, $data['question']);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        }

        return response()->json($result);
    }

    public function unlock(Request $request, string $project): RedirectResponse
    {
        $model = $this->resolver->fromRequest($request, $project);
        abort_unless($model instanceof Project, 404);

        $request->validate(['password' => ['required', 'string']]);

        abort_unless(Hash::check($request->string('password')->toString(), (string) $model->password), 403);

        $request->session()->put('docs.unlocked.'.$model->id, true);

        return back();
    }

    public function feedback(Request $request, string $project): RedirectResponse
    {
        $model = $this->resolver->fromRequest($request, $project);
        abort_unless($model instanceof Project, 404);

        if ($gate = $this->guard($request, $model)) {
            abort(403);
        }

        $data = $request->validate([
            'page_id' => [
                'required',
                'integer',
                Rule::exists('pages', 'id')
                    ->where('project_id', $model->id)
                    ->where('status', PageStatus::Published->value),
            ],
            'helpful' => ['required', 'boolean'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['helpful'] = filter_var($request->input('helpful'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

        $page = Page::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $model->id)
            ->findOrFail($data['page_id']);

        Feedback::query()->create([
            'workspace_id' => $model->workspace_id,
            'project_id' => $model->id,
            'page_id' => $page->id,
            'helpful' => $data['helpful'],
            'comment' => $data['comment'] ?? null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Thanks for your feedback!'),
        ]);

        $version = DocumentationVersion::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->findOrFail($page->documentation_version_id);
        $language = Language::query()->findOrFail($page->language_id);

        return redirect($model->docsBasePath().'/'.$version->slug.'/'.$language->code.'/'.$page->slug);
    }

    public function sitemap(Request $request, string $project): HttpResponse
    {
        $model = $this->resolver->fromRequest($request, $project);
        abort_unless($model instanceof Project && $model->visibility === ProjectVisibility::Public, 404);

        $pages = Page::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $model->id)
            ->where('status', PageStatus::Published)
            ->get();

        $urls = $pages->map(function (Page $page) use ($model): string {
            $version = DocumentationVersion::query()->withoutGlobalScope(WorkspaceScope::class)->find($page->documentation_version_id);
            $language = Language::query()->find($page->language_id);

            return '<url><loc>'.e($this->docUrl($model, $version?->slug ?? 'latest', $language?->code ?? 'en', $page->slug)).'</loc></url>';
        });

        $postUrls = Post::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $model->id)
            ->where('status', PostStatus::Published)
            ->get()
            ->map(function (Post $post) use ($model): string {
                $segment = $post->type === PostType::Announcement ? 'announcements' : 'changelog';

                return '<url><loc>'.e(url($model->docsBasePath().'/'.$segment.'/'.$post->slug)).'</loc></url>';
            });

        $extra = collect([
            '<url><loc>'.e(url($model->docsBasePath().'/directory')).'</loc></url>',
            '<url><loc>'.e(url($model->docsBasePath().'/announcements')).'</loc></url>',
            '<url><loc>'.e(url($model->docsBasePath().'/changelog')).'</loc></url>',
        ]);

        $body = $urls->concat($extra)->concat($postUrls)->implode('');

        return response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$body.'</urlset>', 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    public function robots(Request $request, string $project): HttpResponse
    {
        $model = $this->resolver->fromRequest($request, $project);
        abort_unless($model instanceof Project, 404);

        $allow = $model->visibility === ProjectVisibility::Public ? 'Allow: /' : 'Disallow: /';

        return response("User-agent: *\n{$allow}\nSitemap: ".url($model->docsBasePath().'/sitemap.xml')."\n", 200, [
            'Content-Type' => 'text/plain',
        ]);
    }

    private function guard(Request $request, Project $project): Response|RedirectResponse|null
    {
        if ($project->visibility === ProjectVisibility::Public) {
            return null;
        }

        if ($project->visibility === ProjectVisibility::Private) {
            $user = $request->user();

            if ($user?->belongsToWorkspace($project->workspace_id)) {
                return null;
            }

            abort(403);
        }

        if ($request->session()->get('docs.unlocked.'.$project->id)) {
            return null;
        }

        return Inertia::render('docs/password', [
            'project' => [
                'name' => $project->name,
                'slug' => $project->slug,
                'pathKey' => $project->publicPathKey(),
            ],
        ]);
    }

    private function version(Project $project, ?string $slug): DocumentationVersion
    {
        $found = DocumentationVersion::query()
            ->withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->when(filled($slug), fn ($query) => $query->where('slug', $slug))
            ->first();

        if ($found instanceof DocumentationVersion) {
            return $found;
        }

        return DocumentationVersion::query()
            ->withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->orderByDesc('is_default')
            ->firstOrFail();
    }

    private function language(Project $project, ?string $code): Language
    {
        if ($code) {
            return Language::query()->where('code', $code)->firstOrFail();
        }

        $default = ProjectLanguage::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $project->id)
            ->where('is_default', true)
            ->first();

        return Language::query()->findOrFail($default?->language_id ?? Language::query()->where('code', 'en')->value('id'));
    }

    private function recordView(Request $request, Project $project, Page $page): void
    {
        PageView::query()->create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'page_id' => $page->id,
            'path' => $request->path(),
            'referrer' => substr((string) $request->headers->get('referer'), 0, 255),
            'visitor_hash' => hash('sha256', $request->ip().'|'.(string) $request->userAgent()),
            'viewed_at' => now(),
        ]);
    }

    private function docUrl(Project $project, string $version, string $locale, string $slug): string
    {
        return $project->publicUrl().'/'.$version.'/'.$locale.'/'.$slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function shellProps(Project $model, mixed $workspace): array
    {
        $base = $model->docsBasePath();

        return [
            'project' => [
                'name' => $model->name,
                'slug' => $model->slug,
                'pathKey' => $model->publicPathKey(),
                'primary_color' => $model->primary_color,
                'accent_color' => $model->accent_color,
                'font_family' => $model->font_family,
                'heading_font' => $model->heading_font,
                'logo_url' => $model->brandingAssetUrl($model->logo_path),
                'favicon_url' => $model->brandingAssetUrl($model->favicon_path),
                'og_image_url' => $model->brandingAssetAbsoluteUrl($model->og_image_path),
                'github_edit_url' => $model->github_edit_url,
            ],
            'template' => $model->docs_template->value,
            'showPoweredBy' => $workspace ? ! $this->plans->hasFeature($workspace, 'advanced_branding') : true,
            'headerLinks' => $this->normalizeHeaderLinks($model->header_links),
            'docsHomeUrl' => $this->docsHomeUrl($model),
            'docsPagesBasePath' => $this->docsPagesBasePath($model),
            'directoryUrl' => $base.'/directory',
            'announcementsUrl' => $base.'/announcements',
            'changelogUrl' => $base.'/changelog',
            'searchUrl' => $base.'/search',
            'askUrl' => $base.'/ask',
            'aiAskEnabled' => DocsAskService::isConfigured(),
        ];
    }

    private function docsPagesBasePath(Project $model): string
    {
        $version = DocumentationVersion::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $model->id)
            ->orderByDesc('is_default')
            ->first();

        $language = $this->language($model, null);

        if ($version) {
            return $model->docsBasePath().'/'.$version->slug.'/'.$language->code;
        }

        return $model->docsBasePath();
    }

    private function docsHomeUrl(Project $model): string
    {
        $version = DocumentationVersion::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $model->id)
            ->orderByDesc('is_default')
            ->first();

        $language = $this->language($model, null);
        $firstPage = $this->publishedPages($model)->first();

        if ($version && $firstPage) {
            return $model->docsBasePath().'/'.$version->slug.'/'.$language->code.'/'.$firstPage->slug;
        }

        return $model->docsBasePath();
    }

    /**
     * @return Collection<int, Page>
     */
    private function publishedPages(Project $model): Collection
    {
        $version = DocumentationVersion::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $model->id)
            ->orderByDesc('is_default')
            ->first();

        $language = $this->language($model, null);

        if (! $version) {
            return collect();
        }

        return Page::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $model->id)
            ->where('documentation_version_id', $version->id)
            ->where('language_id', $language->id)
            ->where('status', PageStatus::Published)
            ->where(fn ($query) => $query->where('hidden', false)->orWhereNull('hidden'))
            ->orderBy('position')
            ->get();
    }

    /**
     * @return Collection<int, Post>
     */
    private function publishedPosts(Project $model, PostType $type): Collection
    {
        return Post::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('project_id', $model->id)
            ->where('type', $type)
            ->where('status', PostStatus::Published)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array{title: string, slug: string, excerpt: string|null, url: string, published_at: string|null}
     */
    private function serializePublicPost(Project $model, Post $post): array
    {
        $segment = $post->type === PostType::Announcement ? 'announcements' : 'changelog';

        return [
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'url' => $model->docsBasePath().'/'.$segment.'/'.$post->slug,
            'published_at' => $post->published_at?->toIso8601String(),
        ];
    }

    private function pagePublicUrl(Project $model, Page $page): string
    {
        $version = DocumentationVersion::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->find($page->documentation_version_id);
        $language = Language::query()->find($page->language_id);

        return $model->docsBasePath().'/'.($version?->slug ?? 'latest').'/'.($language?->code ?? 'en').'/'.$page->slug;
    }

    /**
     * @param  array<int, array{label?: string, url?: string}>|null  $links
     * @return array<int, array{label: string, url: string}>
     */
    private function normalizeHeaderLinks(?array $links): array
    {
        if (! is_array($links)) {
            return [];
        }

        return collect($links)
            ->filter(fn (mixed $link): bool => is_array($link) && filled($link['label'] ?? null) && filled($link['url'] ?? null))
            ->map(fn (array $link): array => [
                'label' => (string) $link['label'],
                'url' => (string) $link['url'],
            ])
            ->values()
            ->all();
    }
}
