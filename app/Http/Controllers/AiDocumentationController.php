<?php

namespace App\Http\Controllers;

use App\Enums\AiGenerationJobStatus;
use App\Http\Controllers\Concerns\ResolvesWorkspaceProject;
use App\Models\AiGenerationJob;
use App\Models\Project;
use App\Services\AiDocumentationGenerator;
use App\Support\AiGenerationJobDispatcher;
use App\Support\PlanGate;
use App\Support\UrlSafetyValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiDocumentationController extends Controller
{
    use ResolvesWorkspaceProject;

    public function __construct(
        private PlanGate $plans,
        private UrlSafetyValidator $urlSafety,
    ) {}

    public function generate(Request $request, int $project): JsonResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);

        if (! AiDocumentationGenerator::isConfigured()) {
            return response()->json([
                'message' => 'AI is not configured. Enable AI in Platform → Settings.',
            ], 503);
        }

        $workspace = $this->workspace($request);
        $this->plans->assertCanUseAiGeneration($workspace);

        $data = $request->validate([
            'url' => ['required', 'string', 'url', 'max:2048'],
            'product_description' => ['nullable', 'string', 'max:2000'],
            'publish_immediately' => ['sometimes', 'boolean'],
        ]);

        $this->urlSafety->assertSafe($data['url']);

        $job = AiGenerationJob::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $model->id,
            'user_id' => $request->user()?->id,
            'source_url' => $data['url'],
            'product_description' => $data['product_description'] ?? null,
            'publish_immediately' => (bool) ($data['publish_immediately'] ?? false),
            'status' => AiGenerationJobStatus::Pending,
        ]);

        AiGenerationJobDispatcher::dispatch($job->id);

        return response()->json([
            'job' => $this->jobPayload($job->fresh()),
            'remaining' => $this->plans->aiGenerationsRemaining($workspace),
            'requires_queue_worker' => AiGenerationJobDispatcher::requiresQueueWorker(),
        ], 202);
    }

    public function show(Request $request, int $project, int $job): JsonResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('view', $model);

        $record = AiGenerationJob::query()
            ->withoutGlobalScope(\App\Models\Scopes\WorkspaceScope::class)
            ->where('project_id', $model->id)
            ->where('workspace_id', $model->workspace_id)
            ->findOrFail($job);

        $record = $record->fresh();

        return response()->json([
            'job' => $this->jobPayload($record),
            'remaining' => $this->plans->aiGenerationsRemaining($this->workspace($request)),
            'requires_queue_worker' => AiGenerationJobDispatcher::requiresQueueWorker(),
            'pending_stale' => $record->status === AiGenerationJobStatus::Pending
                && $record->created_at !== null
                && $record->created_at->lt(now()->subSeconds(30)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function aiMetaForProject(Project $project): array
    {
        $workspace = $project->workspace()->with('plan')->first();
        $plans = app(PlanGate::class);

        return [
            'configured' => AiDocumentationGenerator::isConfigured(),
            'remaining' => $workspace ? $plans->aiGenerationsRemaining($workspace) : null,
            'unlimited' => $workspace ? $plans->hasFeature($workspace, 'ai_generation') : false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jobPayload(AiGenerationJob $job): array
    {
        return [
            'id' => $job->id,
            'status' => $job->status->value,
            'source_url' => $job->source_url,
            'error' => $job->error,
            'result_summary' => $job->result_summary,
            'created_at' => $job->created_at?->toIso8601String(),
            'updated_at' => $job->updated_at?->toIso8601String(),
        ];
    }
}
