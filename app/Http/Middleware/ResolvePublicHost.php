<?php

namespace App\Http\Middleware;

use App\Enums\DomainStatus;
use App\Models\CustomDomain;
use App\Models\Project;
use App\Support\PublicProjectResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves documentation projects from custom/subdomain hosts.
 *
 * Verified custom hosts and tenant subdomains rewrite bare paths to
 * /docs/{subdomain}/... before routing so `/` serves public docs.
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

        $pending = $this->pendingCustomDomain($request);

        if ($pending instanceof CustomDomain && ! $this->isAssetPath($request)) {
            return $this->domainStatusResponse($pending);
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
            $suffix = $path === '' || $path === '/' ? '' : '/'.$path;
            $request = $this->rewriteRequestUri($request, $project->docsBasePath().$suffix);
        }

        return $next($request);
    }

    private function isPlatformAppRequest(Request $request): bool
    {
        if ($this->resolver->isPlatformHost($request)) {
            return true;
        }

        return $request->is(
            'platform',
            'platform/*',
            'dashboard',
            'dashboard/*',
            'projects',
            'projects/*',
            'billing',
            'billing/*',
            'analytics',
            'analytics*',
            'members',
            'members*',
            'blocks',
            'blocks*',
            'audit',
            'audit*',
            'settings',
            'settings/*',
            'onboarding',
            'onboarding*',
            'auth/*',
            'login',
            'register',
            'install',
            'install/*',
            'up',
        );
    }

    private function pendingCustomDomain(Request $request): ?CustomDomain
    {
        $domain = $this->resolver->customDomainFromRequest($request);

        if ($domain === null || $domain->status === DomainStatus::Active) {
            return null;
        }

        return $domain;
    }

    private function domainStatusResponse(CustomDomain $domain): Response
    {
        $failed = $domain->status === DomainStatus::Failed;

        return response()->view('docs.domain-status', [
            'hostname' => $domain->hostname,
            'status' => $domain->status->value,
            'appName' => (string) config('app.name'),
            'errorMessage' => $domain->error_message,
        ], $failed ? 404 : 503);
    }

    private function shouldRewriteDocsPath(Request $request): bool
    {
        return ! $this->isAssetPath($request) && ! $request->is('docs', 'docs/*');
    }

    private function isAssetPath(Request $request): bool
    {
        return $request->is(
            'build/*',
            'storage/*',
            'vendor/*',
            'hot',
            'favicon.ico',
            'favicon.png',
            '.well-known/*',
        );
    }

    private function rewriteRequestUri(Request $request, string $path): Request
    {
        $queryString = $request->getQueryString();
        $uri = $path.($queryString ? '?'.$queryString : '');

        $server = $request->server->all();
        $server['REQUEST_URI'] = $uri;
        $server['PATH_INFO'] = $path;

        $rewritten = $request->duplicate(
            null,
            null,
            $request->attributes->all(),
            null,
            null,
            $server,
        );

        app()->instance('request', $rewritten);

        return $rewritten;
    }

    private function redirectToPrimaryDomain(Request $request, Project $project): ?Response
    {
        if ($this->isLocalHost($request)) {
            return null;
        }

        $host = $this->resolver->host($request);
        $platform = strtolower((string) config('anytdocs.domain'));

        if ($platform === '' || ! str_ends_with($host, '.'.$platform) || str_starts_with($host, 'fallback.')) {
            return null;
        }

        $primary = $project->customDomains()
            ->where('is_primary', true)
            ->where('status', DomainStatus::Active)
            ->first();

        if ($primary === null || strtolower($primary->hostname) === $host) {
            return null;
        }

        $target = 'https://'.$primary->hostname.$request->getRequestUri();

        return redirect()->away($target, 301);
    }

    private function isLocalHost(Request $request): bool
    {
        $host = $this->resolver->rawHost($request);
        $appUrl = (string) config('app.url');

        return in_array($host, ['localhost', '127.0.0.1'], true)
            || str_contains($appUrl, 'localhost')
            || str_contains($appUrl, '127.0.0.1');
    }
}
