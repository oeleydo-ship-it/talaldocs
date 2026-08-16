<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Http\Request;

trait ResolvesWorkspaceProject
{
    protected function workspace(Request $request): Workspace
    {
        $workspace = $request->user()?->currentWorkspace()->with('plan')->first();

        abort_unless($workspace !== null, 403);

        return $workspace;
    }

    protected function project(Request $request, int $project): Project
    {
        $workspace = $this->workspace($request);

        $model = Project::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($project);

        $this->authorize('view', $model);

        return $model;
    }
}
