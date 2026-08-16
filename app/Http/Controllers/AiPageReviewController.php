<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesWorkspaceProject;
use App\Models\Page;
use App\Services\AiPageReviewer;
use App\Support\Audit;
use App\Support\PagePublisher;
use App\Support\PlanGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class AiPageReviewController extends Controller
{
    use ResolvesWorkspaceProject;

    public function __construct(
        private AiPageReviewer $reviewer,
        private PagePublisher $publisher,
        private PlanGate $plans,
        private Audit $audit,
    ) {}

    public function review(Request $request, int $project, int $page): JsonResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);

        if (! AiPageReviewer::isConfigured()) {
            return response()->json([
                'message' => 'AI is not configured. Enable AI in Platform → Settings.',
            ], 503);
        }

        $this->plans->assertCanUseAiGeneration($this->workspace($request));

        $data = $request->validate([
            'intent' => ['required', Rule::in(array_keys(AiPageReviewer::INTENTS))],
        ]);

        $record = Page::query()->where('project_id', $model->id)->findOrFail($page);
        $markdown = trim((string) $record->markdown);

        if ($markdown === '') {
            return response()->json([
                'message' => 'This page has no content to review yet.',
            ], 422);
        }

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
            $proposal = $this->reviewer->review([
                'project' => $model->name,
                'title' => $record->title,
                'markdown' => $markdown,
                'siblings' => array_values(array_map(fn (mixed $title): string => (string) $title, $siblings)),
            ], $data['intent']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => Str::limit($exception->getMessage(), 1000),
            ], 502);
        }

        return response()->json([
            'intent' => $data['intent'],
            'current_markdown' => $record->markdown,
            'proposal' => $proposal,
        ]);
    }

    public function apply(Request $request, int $project, int $page): JsonResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);

        $record = Page::query()->where('project_id', $model->id)->findOrFail($page);

        $data = $request->validate([
            'markdown' => ['required', 'string'],
            'intent' => ['sometimes', Rule::in(array_keys(AiPageReviewer::INTENTS))],
        ]);

        $this->publisher->recordRevision($record, $request->user(), 'ai_review');

        $updated = $this->publisher->saveDraft(
            $record,
            $record->title,
            $record->slug,
            $data['markdown'],
            $request->user(),
            false,
            $record->subtitle,
        );

        $this->audit->record($model->workspace_id, 'page.ai_reviewed', $request->user(), $updated, [
            'intent' => $data['intent'] ?? null,
        ]);

        return response()->json([
            'saved_at' => now()->toIso8601String(),
            'markdown' => $updated->markdown,
            'html' => $updated->html,
        ]);
    }
}
