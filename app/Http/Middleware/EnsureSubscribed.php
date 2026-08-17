<?php

namespace App\Http\Middleware;

use App\Support\BillingAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscribed
{
    public function __construct(private BillingAccess $billing) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->billing->billingEnforced()) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $workspace = $user->currentWorkspace;

        if ($workspace !== null && ! $this->billing->workspaceHasAccess($workspace)) {
            $this->billing->startLocalTrialIfEligible($workspace);
            $workspace->refresh();
        }

        if ($this->billing->workspaceHasAccess($workspace)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(402, $this->billing->blockedMessage($workspace));
        }

        return redirect()
            ->route('billing.show')
            ->with('warning', $this->billing->blockedMessage($workspace));
    }
}
