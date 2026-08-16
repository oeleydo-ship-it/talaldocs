<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Http\Controllers\Concerns\ResolvesWorkspaceProject;
use App\Models\Post;
use App\Support\Audit;
use App\Support\Markdown;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    use ResolvesWorkspaceProject;

    public function __construct(
        private Markdown $markdown,
        private Audit $audit,
    ) {}

    public function index(Request $request, int $project): Response
    {
        $model = $this->project($request, $project);

        $posts = Post::query()
            ->where('project_id', $model->id)
            ->orderByDesc('published_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Post $post): array => $this->serialize($post));

        return Inertia::render('projects/posts', [
            'project' => [
                'id' => $model->id,
                'name' => $model->name,
                'public_url' => $model->publicUrl(),
                'docs_base_path' => $model->docsBasePath(),
            ],
            'posts' => $posts,
            'canEdit' => $request->user()?->roleInWorkspace($model->workspace_id)?->canEditContent() ?? false,
        ]);
    }

    public function store(Request $request, int $project): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);

        $data = $this->validated($request, $model);

        $post = Post::query()->create([
            'workspace_id' => $model->workspace_id,
            'project_id' => $model->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'excerpt' => $data['excerpt'] ?? null,
            'markdown' => $data['markdown'] ?? null,
            'html' => $this->markdown->render($data['markdown'] ?? '', $model->workspace_id),
            'status' => PostStatus::Draft,
            'published_at' => null,
            'position' => (int) Post::query()->where('project_id', $model->id)->where('type', $data['type'])->max('position') + 1,
        ]);

        if (! empty($data['publish'])) {
            $this->publishPost($post);
        }

        $this->audit->record($model->workspace_id, 'post.created', $request->user(), $post);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Post created.'),
        ]);

        return redirect()->route('projects.posts.index', $model);
    }

    public function update(Request $request, int $project, int $post): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);

        $record = Post::query()
            ->where('project_id', $model->id)
            ->findOrFail($post);

        $data = $this->validated($request, $model, $record);

        $record->update([
            'type' => $data['type'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'excerpt' => $data['excerpt'] ?? null,
            'markdown' => $data['markdown'] ?? null,
            'html' => $this->markdown->render($data['markdown'] ?? '', $model->workspace_id),
        ]);

        if (array_key_exists('publish', $data)) {
            if ($data['publish']) {
                $this->publishPost($record);
            } else {
                $this->unpublishPost($record);
            }
        }

        $this->audit->record($model->workspace_id, 'post.updated', $request->user(), $record);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Post updated.'),
        ]);

        return redirect()->route('projects.posts.index', $model);
    }

    public function publish(Request $request, int $project, int $post): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);

        $record = Post::query()
            ->where('project_id', $model->id)
            ->findOrFail($post);

        $publish = filter_var($request->input('publish', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $publish ??= true;

        if ($publish) {
            $this->publishPost($record);
            $this->audit->record($model->workspace_id, 'post.published', $request->user(), $record);
            $message = __('Post published.');
        } else {
            $this->unpublishPost($record);
            $this->audit->record($model->workspace_id, 'post.unpublished', $request->user(), $record);
            $message = __('Post unpublished.');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);

        return back();
    }

    public function destroy(Request $request, int $project, int $post): RedirectResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);

        $record = Post::query()
            ->where('project_id', $model->id)
            ->findOrFail($post);

        $this->audit->record($model->workspace_id, 'post.deleted', $request->user(), $record);
        $record->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Post deleted.'),
        ]);

        return redirect()->route('projects.posts.index', $model);
    }

    /**
     * @return array{type: string, title: string, slug: string, excerpt?: string|null, markdown?: string|null, publish?: bool}
     */
    private function validated(Request $request, \App\Models\Project $project, ?Post $existing = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(PostType::class)],
            'title' => ['required', 'string', 'max:180'],
            'slug' => [
                'nullable',
                'string',
                'max:180',
                'alpha_dash',
                Rule::unique('posts', 'slug')
                    ->where('project_id', $project->id)
                    ->where('type', $request->input('type'))
                    ->ignore($existing?->id)
                    ->whereNull('deleted_at'),
            ],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'markdown' => ['nullable', 'string', 'max:100000'],
            'publish' => ['sometimes', 'boolean'],
        ]);

        $slug = filled($data['slug'] ?? null)
            ? Str::slug((string) $data['slug'])
            : Str::slug($data['title']);

        if ($slug === '') {
            $slug = 'post';
        }

        $base = $slug;
        $i = 2;
        while (
            Post::query()
                ->where('project_id', $project->id)
                ->where('type', $data['type'])
                ->where('slug', $slug)
                ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        $data['slug'] = $slug;
        $data['publish'] = array_key_exists('publish', $data)
            ? (bool) $data['publish']
            : null;

        if ($data['publish'] === null) {
            unset($data['publish']);
        }

        return $data;
    }

    private function publishPost(Post $post): void
    {
        $post->forceFill([
            'status' => PostStatus::Published,
            'published_at' => $post->published_at ?? now(),
        ])->save();
    }

    private function unpublishPost(Post $post): void
    {
        $post->forceFill([
            'status' => PostStatus::Draft,
            'published_at' => null,
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Post $post): array
    {
        return [
            'id' => $post->id,
            'type' => $post->type->value,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'markdown' => $post->markdown,
            'status' => $post->status->value,
            'published_at' => $post->published_at?->toIso8601String(),
            'updated_at' => $post->updated_at?->toIso8601String(),
        ];
    }
}
