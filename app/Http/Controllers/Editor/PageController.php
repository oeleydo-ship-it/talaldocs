<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\AiDocumentationController;
use App\Http\Controllers\Concerns\ResolvesWorkspaceProject;
use App\Http\Controllers\Controller;
use App\Enums\PageStatus;
use App\Models\Block;
use App\Models\DocumentationVersion;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\ProjectLanguage;
use App\Support\Audit;
use App\Support\Markdown;
use App\Support\PagePublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PageController extends Controller
{
    use ResolvesWorkspaceProject;

    public function __construct(
        private PagePublisher $publisher,
        private Markdown $markdown,
        private Audit $audit,
    ) {}

    public function editor(Request $request, int $project): Response
    {
        $model = $this->project($request, $project);
        $workspace = $this->workspace($request);

        $versions = DocumentationVersion::query()
            ->where('project_id', $model->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $versionId = (int) ($request->integer('version') ?: $versions->firstWhere('is_default', true)?->id ?: $versions->first()?->id);
        $languages = ProjectLanguage::query()
            ->with('language')
            ->where('project_id', $model->id)
            ->get();

        if ($languages->isEmpty()) {
            $english = Language::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true]);
            ProjectLanguage::query()->create([
                'workspace_id' => $workspace->id,
                'project_id' => $model->id,
                'language_id' => $english->id,
                'is_default' => true,
            ]);
            $languages = ProjectLanguage::query()->with('language')->where('project_id', $model->id)->get();
        }

        $languageId = (int) ($request->integer('language') ?: $languages->firstWhere('is_default', true)?->language_id ?: $languages->first()?->language_id);

        $pages = Page::query()
            ->where('project_id', $model->id)
            ->where('documentation_version_id', $versionId)
            ->where('language_id', $languageId)
            ->orderBy('position')
            ->get();

        $archivedPages = Page::query()
            ->onlyTrashed()
            ->where('project_id', $model->id)
            ->where('documentation_version_id', $versionId)
            ->where('language_id', $languageId)
            ->orderByDesc('deleted_at')
            ->get(['id', 'title', 'slug', 'deleted_at']);

        $selected = $request->integer('page')
            ? $pages->firstWhere('id', $request->integer('page'))
            : $pages->first();

        $revisions = $selected
            ? PageRevision::query()->with('user:id,name')->where('page_id', $selected->id)->latest('id')->limit(20)->get()
            : collect();

        $blocks = Block::query()->where('workspace_id', $workspace->id)->orderBy('name')->get();

        return Inertia::render('editor/show', [
            'project' => $this->projectPayload($model),
            'versions' => $versions,
            'languages' => $languages->map(fn (ProjectLanguage $row): array => [
                'id' => $row->language_id,
                'code' => $row->language?->code,
                'name' => $row->language?->name,
                'is_default' => $row->is_default,
            ]),
            'currentVersionId' => $versionId,
            'currentLanguageId' => $languageId,
            'pages' => $pages->map(fn (Page $page): array => $this->pageSummary($page)),
            'page' => $selected ? $this->pageDetail($selected) : null,
            'revisions' => $revisions->map(fn (PageRevision $revision): array => [
                'id' => $revision->id,
                'event' => $revision->event,
                'created_at' => $revision->created_at?->toIso8601String(),
                'user' => $revision->user?->name,
                'markdown' => $revision->markdown,
            ]),
            'archivedPages' => $archivedPages->map(fn (Page $page): array => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'deleted_at' => $page->deleted_at?->toIso8601String(),
            ]),
            'blocks' => $blocks,
            'canEdit' => $request->user()?->roleInWorkspace($workspace->id)?->canEditContent() ?? false,
            'previewHtml' => $selected
                ? $this->markdown->injectHeadingIds($this->markdown->render((string) $selected->markdown, $workspace->id))
                : null,
            'ai' => AiDocumentationController::aiMetaForProject($model),
        ]);
    }

    public function store(Request $request, int $project): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'documentation_version_id' => ['required', 'integer'],
            'language_id' => ['required', 'integer'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $slug = Str::slug($data['title']);
        $base = $slug === '' ? 'page' : $slug;
        $slug = $base;
        $i = 1;

        while (Page::query()
            ->where('documentation_version_id', $data['documentation_version_id'])
            ->where('language_id', $data['language_id'])
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        $position = (int) Page::query()
            ->where('documentation_version_id', $data['documentation_version_id'])
            ->where('language_id', $data['language_id'])
            ->max('position') + 1;

        $parentId = $data['parent_id'] ?? null;

        if ($parentId !== null) {
            $parentExists = Page::query()
                ->where('project_id', $model->id)
                ->where('documentation_version_id', $data['documentation_version_id'])
                ->where('language_id', $data['language_id'])
                ->where('id', $parentId)
                ->exists();

            if (! $parentExists) {
                $parentId = null;
            }
        }

        $page = Page::query()->create([
            'workspace_id' => $model->workspace_id,
            'project_id' => $model->id,
            'documentation_version_id' => $data['documentation_version_id'],
            'language_id' => $data['language_id'],
            'parent_id' => $parentId,
            'title' => $data['title'],
            'slug' => $slug,
            'markdown' => '# '.$data['title']."\n\nStart writing…",
            'html' => $this->markdown->render('# '.$data['title']."\n\nStart writing…", $model->workspace_id),
            'position' => $position,
        ]);

        $this->audit->record($model->workspace_id, 'page.created', $request->user(), $page);

        return redirect()->route('projects.editor', [
            'project' => $model->id,
            'version' => $page->documentation_version_id,
            'language' => $page->language_id,
            'page' => $page->id,
        ]);
    }

    public function update(Request $request, int $project, int $page): HttpResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);

        $record = Page::query()->where('project_id', $model->id)->findOrFail($page);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'subtitle' => ['nullable', 'string', 'max:280'],
            'slug' => ['required', 'string', 'max:180', 'alpha_dash'],
            'markdown' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer'],
            'position' => ['nullable', 'integer'],
            'hidden' => ['sometimes', 'boolean'],
            'revision' => ['sometimes', 'boolean'],
            'autosave' => ['sometimes', 'boolean'],
        ]);

        $this->publisher->saveDraft(
            $record,
            $data['title'],
            $data['slug'],
            (string) ($data['markdown'] ?? ''),
            $request->user(),
            (bool) ($data['revision'] ?? false),
            $data['subtitle'] ?? null,
        );

        if (array_key_exists('parent_id', $data)) {
            $parentId = $data['parent_id'];

            if ($parentId !== null && $this->parentWouldCycle($record, (int) $parentId)) {
                $parentId = $record->parent_id;
            }

            $record->forceFill(['parent_id' => $parentId])->save();
        }

        if (array_key_exists('position', $data) && $data['position'] !== null) {
            $record->forceFill(['position' => $data['position']])->save();
        }

        if (array_key_exists('hidden', $data)) {
            $record->forceFill(['hidden' => $data['hidden']])->save();
        }

        if ($request->boolean('autosave')) {
            return response()->json([
                'saved_at' => now()->toIso8601String(),
                'html' => $record->fresh()?->html,
            ]);
        }

        return back();
    }

    public function publish(Request $request, int $project, int $page): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);
        $record = Page::query()->where('project_id', $model->id)->findOrFail($page);
        $this->publisher->publish($record, $request->user());
        $this->audit->record($model->workspace_id, 'page.published', $request->user(), $record);

        return back();
    }

    public function unpublish(Request $request, int $project, int $page): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);
        $record = Page::query()->where('project_id', $model->id)->findOrFail($page);
        $this->publisher->unpublish($record, $request->user());
        $this->audit->record($model->workspace_id, 'page.unpublished', $request->user(), $record);

        return back();
    }

    public function duplicate(Request $request, int $project, int $page): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);

        $record = Page::query()->where('project_id', $model->id)->findOrFail($page);

        $baseSlug = $record->slug.'-copy';
        $slug = $baseSlug;
        $i = 1;

        while (Page::query()
            ->where('documentation_version_id', $record->documentation_version_id)
            ->where('language_id', $record->language_id)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $baseSlug.'-'.$i++;
        }

        $position = (int) Page::query()
            ->where('documentation_version_id', $record->documentation_version_id)
            ->where('language_id', $record->language_id)
            ->max('position') + 1;

        $copy = Page::query()->create([
            'workspace_id' => $record->workspace_id,
            'project_id' => $record->project_id,
            'documentation_version_id' => $record->documentation_version_id,
            'language_id' => $record->language_id,
            'parent_id' => $record->parent_id,
            'title' => $record->title.' (copy)',
            'slug' => $slug,
            'markdown' => $record->markdown,
            'html' => $record->html,
            'status' => PageStatus::Draft,
            'position' => $position,
            'hidden' => $record->hidden,
        ]);

        $this->audit->record($model->workspace_id, 'page.duplicated', $request->user(), $copy);

        return redirect()->route('projects.editor', [
            'project' => $model->id,
            'version' => $copy->documentation_version_id,
            'language' => $copy->language_id,
            'page' => $copy->id,
        ]);
    }

    public function restore(Request $request, int $project, int $page, int $revision): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);
        $record = Page::query()->where('project_id', $model->id)->findOrFail($page);
        $rev = PageRevision::query()->where('page_id', $record->id)->findOrFail($revision);
        $this->publisher->restoreRevision($record, $rev, $request->user());

        return back();
    }

    public function showRevision(Request $request, int $project, int $page, int $revision): \Illuminate\Http\JsonResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);
        $record = Page::query()->where('project_id', $model->id)->findOrFail($page);
        $rev = PageRevision::query()->where('page_id', $record->id)->findOrFail($revision);

        return response()->json([
            'id' => $rev->id,
            'event' => $rev->event,
            'markdown' => $rev->markdown,
            'current_markdown' => $record->markdown,
            'created_at' => $rev->created_at?->toIso8601String(),
            'user' => $rev->user?->name,
        ]);
    }

    public function restoreArchive(Request $request, int $project, int $page): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);

        $record = Page::query()
            ->onlyTrashed()
            ->where('project_id', $model->id)
            ->findOrFail($page);

        $record->restore();
        $this->audit->record($model->workspace_id, 'page.restored', $request->user(), $record);

        return redirect()->route('projects.editor', [
            'project' => $model->id,
            'version' => $record->documentation_version_id,
            'language' => $record->language_id,
            'page' => $record->id,
        ]);
    }

    public function destroy(Request $request, int $project, int $page): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);
        $record = Page::query()->where('project_id', $model->id)->findOrFail($page);
        $record->delete();
        $this->audit->record($model->workspace_id, 'page.archived', $request->user(), $record);

        return redirect()->route('projects.editor', [
            'project' => $model->id,
            'version' => $record->documentation_version_id,
            'language' => $record->language_id,
        ]);
    }

    public function reorder(Request $request, int $project): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*.id' => ['required', 'integer'],
            'order.*.position' => ['required', 'integer'],
            'order.*.parent_id' => ['nullable', 'integer'],
        ]);

        foreach ($data['order'] as $row) {
            Page::query()
                ->where('project_id', $model->id)
                ->where('id', $row['id'])
                ->update([
                    'position' => $row['position'],
                    'parent_id' => $row['parent_id'] ?? null,
                ]);
        }

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function projectPayload(\App\Models\Project $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'slug' => $model->slug,
            'subdomain' => $model->subdomain,
            'visibility' => $model->visibility->value,
            'public_url' => $model->publicUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pageSummary(Page $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'parent_id' => $page->parent_id,
            'position' => $page->position,
            'status' => $page->status->value,
            'hidden' => $page->hidden,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pageDetail(Page $page): array
    {
        return [
            ...$this->pageSummary($page),
            'subtitle' => $page->subtitle,
            'markdown' => $page->markdown,
            'html' => $page->html,
            'published_markdown' => $page->published_markdown,
            'published_at' => $page->published_at?->toIso8601String(),
            'updated_at' => $page->updated_at?->toIso8601String(),
        ];
    }

    private function parentWouldCycle(Page $page, int $parentId): bool
    {
        if ($parentId === $page->id) {
            return true;
        }

        $cursor = Page::query()->where('project_id', $page->project_id)->find($parentId);
        $guard = 0;

        while ($cursor instanceof Page && $guard++ < 50) {
            if ($cursor->id === $page->id) {
                return true;
            }

            if ($cursor->parent_id === null) {
                return false;
            }

            $cursor = Page::query()->where('project_id', $page->project_id)->find($cursor->parent_id);
        }

        return false;
    }
}
