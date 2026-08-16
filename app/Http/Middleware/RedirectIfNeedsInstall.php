<?php

namespace App\Http\Middleware;

use App\Support\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfNeedsInstall
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Installer::enabled() || ! Installer::needsInstall()) {
            return $next($request);
        }

        if ($request->routeIs('install.*') || $request->is('up') || $request->is('stripe/webhook') || $request->is('__diag')) {
            return $next($request);
        }

        return redirect()->route('install.show');
    }
}
