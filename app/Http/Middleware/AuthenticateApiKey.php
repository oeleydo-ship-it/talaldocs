<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\Scopes\WorkspaceScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extension-point middleware for future API routes.
 *
 * Expects X-Anytdocs-Key header. When valid, sets anytdocs.api_workspace_id on the request.
 */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainKey = (string) $request->header('X-Anytdocs-Key', '');

        if ($plainKey === '') {
            return response()->json(['message' => 'API key required.'], 401);
        }

        $prefix = substr($plainKey, 0, 12);
        $record = ApiKey::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('key_prefix', $prefix)
            ->first();

        if ($record === null || ! $record->matches($plainKey)) {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        if ($record->expires_at !== null && $record->expires_at->isPast()) {
            return response()->json(['message' => 'API key expired.'], 401);
        }

        $record->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('anytdocs.api_workspace_id', $record->workspace_id);

        return $next($request);
    }
}
