<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user?->currentWorkspace()->with('plan')->first();

        abort_unless($workspace !== null, 403);

        $projects = Project::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'subdomain' => $project->subdomain,
                'visibility' => $project->visibility->value,
                'public_url' => $project->publicUrl((string) config('anytdocs.domain')),
                'created_at' => $project->created_at?->toIso8601String(),
            ]);

        return Inertia::render('dashboard', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'company_name' => $workspace->company_name,
                'role' => $user?->roleInWorkspace($workspace->id)?->value,
                'plan' => $workspace->plan?->name,
            ],
            'projects' => $projects,
            'appDomain' => config('anytdocs.domain'),
        ]);
    }
}
