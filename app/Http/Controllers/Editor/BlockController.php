<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Concerns\ResolvesWorkspaceProject;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Support\BlockContentValidator;
use App\Support\Markdown;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BlockController extends Controller
{
    use ResolvesWorkspaceProject;

    public function index(Request $request): Response
    {
        $workspace = $this->workspace($request);

        return Inertia::render('blocks/index', [
            'blocks' => Block::query()
                ->where('workspace_id', $workspace->id)
                ->orderBy('name')
                ->get()
                ->map(fn (Block $block): array => [
                    'id' => $block->id,
                    'name' => $block->name,
                    'slug' => $block->slug,
                    'markdown' => $block->markdown,
                    'preview_html' => $block->html,
                    'shortcode' => '{{block:'.$block->slug.'}}',
                ]),
            'canEdit' => $request->user()?->roleInWorkspace($workspace->id)?->canEditContent() ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request);
        abort_unless($request->user()?->roleInWorkspace($workspace->id)?->canEditContent() ?? false, 403);

        $data = $request->validate(BlockContentValidator::rules());

        Block::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'markdown' => $data['markdown'],
            'html' => app(Markdown::class)->render($data['markdown'], $workspace->id),
        ]);

        $response = back();

        if (BlockContentValidator::looksLikeApiDump($data['markdown'])) {
            $response = $response->with(
                'warning',
                'This content looks like pasted JSON from an API response. Blocks work best with human-readable Markdown.',
            );
        }

        return $response;
    }

    public function update(Request $request, int $block): RedirectResponse
    {
        $workspace = $this->workspace($request);
        abort_unless($request->user()?->roleInWorkspace($workspace->id)?->canEditContent() ?? false, 403);

        $record = Block::query()->where('workspace_id', $workspace->id)->findOrFail($block);
        $data = $request->validate(BlockContentValidator::rules());

        $record->update([
            'name' => $data['name'],
            'markdown' => $data['markdown'],
            'html' => app(Markdown::class)->render($data['markdown'], $workspace->id),
        ]);

        $response = back();

        if (BlockContentValidator::looksLikeApiDump($data['markdown'])) {
            $response = $response->with(
                'warning',
                'This content looks like pasted JSON from an API response. Blocks work best with human-readable Markdown.',
            );
        }

        return $response;
    }

    public function destroy(Request $request, int $block): RedirectResponse
    {
        $workspace = $this->workspace($request);
        abort_unless($request->user()?->roleInWorkspace($workspace->id)?->canEditContent() ?? false, 403);
        Block::query()->where('workspace_id', $workspace->id)->findOrFail($block)->delete();

        return back();
    }
}
