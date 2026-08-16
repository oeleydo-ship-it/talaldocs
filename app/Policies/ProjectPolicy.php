<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $user->belongsToWorkspace($project->workspace_id);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->roleInWorkspace($project->workspace_id)?->canEditContent() ?? false;
    }

    public function manageSettings(User $user, Project $project): bool
    {
        return $user->roleInWorkspace($project->workspace_id)?->canManageSettings() ?? false;
    }
}
