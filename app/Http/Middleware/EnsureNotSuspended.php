<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->suspended_at !== null) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'This account has been suspended.');
        }

        if ($user?->current_workspace_id) {
            $workspace = $user->currentWorkspace;

            if ($workspace?->suspended_at !== null) {
                abort(403, 'This workspace has been suspended.');
            }
        }

        return $next($request);
    }
}
