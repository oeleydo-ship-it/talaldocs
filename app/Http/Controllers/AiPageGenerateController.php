<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesWorkspaceProject;
use App\Models\Page;
use App\Services\AiPageGenerator;
use App\Support\Audit;
use App\Support\Markdown;
use App\Support\PagePublisher;
use App\Support\PlanGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class AiPageGenerateController extends Controller
{
    use ResolvesWorkspaceProject;

    public function __construct(
        private AiPageGenerator $generator,
        private PagePublisher $publisher,
        private PlanGate $plans,
        private Audit $audit,
        private Markdown $markdown,
    ) {}

    public function generate(Request $request, int $project, int $page): JsonResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);

        if (! AiPageGenerator::isConfigured()) {
            return response()->json([
                'message' => 'AI is not configured. Enable AI in Platform → Settings.',
            ], 503);
        }

        $this->plans->assertCanUseAiGeneration($this->workspace($request));

        $data = $request->validate([
            'description' => ['required', 'string', 'min:10', 'max:4000'],
            'audience' => ['sometimes', Rule::in(array_keys(AiPageGenerator::AUDIENCES))],
            'tone' => ['sometimes', Rule::in(array_keys(AiPageGenerator::TONES))],
        ]);

        $record = Page::query()->where('project_id', $model->id)->findOrFail($page);
        $markdown = trim((string) $record->markdown);

        $siblings = Page::query()
            ->where('project_id', $model->id)
            ->where('documentation_version_id', $record->documentation_version_id)
            ->where('language_id', $record->language_id)
            ->whereKeyNot($record->id)
            ->orderBy('position')
            ->limit(30)
            ->pluck('title')
            ->all();

        try {
            $proposal = $this->generator->generate([
                'project' => $model->name,
                'title' => $record->title,
                'slug' => $record->slug,
                'markdown' => $record->markdown ?? '',
                'siblings' => array_values(array_map(fn (mixed $title): string => (string) $title, $siblings)),
                'description' => trim($data['description']),
                'suggest_metadata' => $this->shouldSuggestMetadata($record),
            ], $data['audience'] ?? 'developers', $data['tone'] ?? 'technical');
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => Str::limit($exception->getMessage(), 1000),
            ], 502);
        }

        return response()->json([
            'current_markdown' => $record->markdown,
            'proposal' => $proposal,
            'html' => $this->markdown->render($proposal['markdown'], $record->workspace_id),
        ]);
    }

    public function apply(Request $request, int $project, int $page): JsonResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);

        $record = Page::query()->where('project_id', $model->id)->findOrFail($page);

        $data = $request->validate([
            'markdown' => ['required', 'string'],
            'title' => ['sometimes', 'nullable', 'string', 'max:180'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:280'],
        ]);

        $this->publisher->recordRevision($record, $request->user(), 'ai_generate');

        $title = array_key_exists('title', $data) && filled($data['title'])
            ? (string) $data['title']
            : $record->title;

        $subtitle = array_key_exists('subtitle', $data)
            ? ($data['subtitle'] !== null && $data['subtitle'] !== '' ? (string) $data['subtitle'] : null)
            : $record->subtitle;

        $updated = $this->publisher->saveDraft(
            $record,
            $title,
            $record->slug,
            $data['markdown'],
            $request->user(),
            false,
            $subtitle,
        );

        $this->audit->record($model->workspace_id, 'page.ai_generated', $request->user(), $updated, [
            'source' => 'page_editor',
        ]);

        return response()->json([
            'saved_at' => now()->toIso8601String(),
            'markdown' => $updated->markdown,
            'html' => $updated->html,
            'title' => $updated->title,
            'subtitle' => $updated->subtitle,
        ]);
    }

    private function shouldSuggestMetadata(Page $page): bool
    {
        if (trim((string) $page->markdown) !== '') {
            return false;
        }

        $title = trim((string) $page->title);

        if ($title === '') {
            return true;
        }

        return in_array(strtolower($title), ['new page', 'untitled', 'untitled page'], true);
    }
}
