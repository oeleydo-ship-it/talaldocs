<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $workspaceId = $user->current_workspace_id;

        if ($workspaceId === null || ! $user->belongsToWorkspace($workspaceId)) {
            $membership = $user->memberships()->latest('id')->first();
            $workspaceId = $membership?->workspace_id;

            if ($workspaceId !== null && $user->current_workspace_id !== $workspaceId) {
                $user->forceFill(['current_workspace_id' => $workspaceId])->save();
            }
        }

        app()->instance('current.workspace_id', $workspaceId);

        if ($workspaceId !== null) {
            $workspace = Workspace::query()->find($workspaceId);

            if ($workspace !== null) {
                app()->instance(CurrentWorkspace::class, new CurrentWorkspace($workspace));
            }
        }

        return $next($request);
    }
}
