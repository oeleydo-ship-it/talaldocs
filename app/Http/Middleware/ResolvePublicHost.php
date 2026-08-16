<?php

namespace App\Http\Middleware;

use App\Enums\DomainStatus;
use App\Models\Project;
use App\Support\PublicProjectResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves documentation projects from custom/subdomain hosts.
 *
 * On localhost (php artisan serve), host-based paths are not rewritten — use
 * /docs/{subdomain}/... instead. See docs/custom-domains.md.
 */
class ResolvePublicHost
{
    public function __construct(private PublicProjectResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isPlatformAppRequest($request)) {
            return $next($request);
        }

        $project = $this->resolver->fromRequest($request, null);

        if (! $project instanceof Project) {
            return $next($request);
        }

        $request->attributes->set('anytdocs.public_project_id', $project->id);

        if ($redirect = $this->redirectToPrimaryDomain($request, $project)) {
            return $redirect;
        }

        if ($this->shouldRewriteDocsPath($request)) {
            $path = trim($request->path(), '/');
            $suffix = $path === '' ? '' : '/'.$path;
            $request->server->set(
                'REQUEST_URI',
                $project->docsBasePath().$suffix.($request->getQueryString() ? '?'.$request->getQueryString() : ''),
            );
        }

        return $next($request);
    }

    private function isPlatformAppRequest(Request $request): bool
    {
        $host = strtolower((string) $request->getHost());

        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            return true;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($appHost) && $host === strtolower($appHost)) {
            return true;
        }

        return $request->is(
            'platform/*',
            'dashboard',
            'dashboard/*',
            'projects/*',
            'billing/*',
            'analytics*',
            'members*',
            'blocks*',
            'audit*',
            'settings/*',
            'onboarding*',
            'auth/*',
            'login',
            'register',
            'up',
        );
    }

    private function shouldRewriteDocsPath(Request $request): bool
    {
        if ($request->is('docs/*')) {
            return false;
        }

        $host = strtolower((string) $request->getHost());
        $platform = strtolower((string) config('anytdocs.domain'));

        return str_ends_with($host, '.'.$platform) || $this->resolver->fromRequest($request, null) !== null;
    }

    private function redirectToPrimaryDomain(Request $request, Project $project): ?Response
    {
        if ($this->isLocalHost($request)) {
            return null;
        }

        $host = strtolower((string) $request->getHost());
        $platform = strtolower((string) config('anytdocs.domain'));

        if (! str_ends_with($host, '.'.$platform)) {
            return null;
        }

        $primary = $project->customDomains()
            ->where('is_primary', true)
            ->whereIn('status', [DomainStatus::Active])
            ->first();

        if ($primary === null || strtolower($primary->hostname) === $host) {
            return null;
        }

        $target = 'https://'.$primary->hostname.$request->getRequestUri();

        return redirect()->away($target, 301);
    }

    private function isLocalHost(Request $request): bool
    {
        $host = strtolower((string) $request->getHost());
        $appUrl = (string) config('app.url');

        return in_array($host, ['localhost', '127.0.0.1'], true)
            || str_contains($appUrl, 'localhost')
            || str_contains($appUrl, '127.0.0.1');
    }
}
