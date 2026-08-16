<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function switch(Request $request, Workspace $workspace): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->belongsToWorkspace($workspace->id), 403);

        $this->authorize('view', $workspace);

        $user->forceFill(['current_workspace_id' => $workspace->id])->save();

        return redirect()->route('dashboard');
    }
}
