<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Support\OAuthProviders;
use App\Support\PlatformConfig;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $workspace = null;
        $workspaces = [];

        if ($request->user()) {
            $workspaces = $request->user()
                ->workspaces()
                ->orderBy('name')
                ->get(['workspaces.id', 'workspaces.name', 'workspaces.slug'])
                ->map(fn (Workspace $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'slug' => $item->slug,
                ])
                ->values()
                ->all();

            if ($request->user()->current_workspace_id) {
                $workspace = Workspace::query()->find($request->user()->current_workspace_id);
            }
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'platformBranding' => PlatformConfig::brandingForFrontend(),
            'auth' => [
                'user' => $request->user(),
            ],
            'workspace' => $workspace ? [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
            ] : null,
            'workspaces' => $workspaces,
            'isPlatformAdmin' => (bool) $request->user()?->is_platform_admin,
            'isImpersonating' => $request->session()->has('platform.impersonator_id'),
            'oauthProviders' => OAuthProviders::enabled(),
            'appDomain' => config('anytdocs.domain'),
            'csrf' => csrf_token(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'warning' => fn () => $request->session()->get('warning'),
                'status' => fn () => $request->session()->get('status'),
                'import' => fn () => $request->session()->get('import'),
            ],
        ];
    }
}
