<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesWorkspaceProject;
use App\Models\AuditLog;
use App\Support\PlanGate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    use ResolvesWorkspaceProject;

    public function __construct(private PlanGate $planGate) {}

    public function index(Request $request): Response
    {
        $workspace = $this->workspace($request);
        $this->authorize('update', $workspace);

        abort_unless($this->planGate->hasFeature($workspace, 'audit_log'), 403);

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->where('workspace_id', $workspace->id)
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'user' => $log->user?->name,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('audit/index', [
            'logs' => $logs,
        ]);
    }
}
