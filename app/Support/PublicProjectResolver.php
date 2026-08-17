<?php

namespace App\Support;

use App\Enums\DomainStatus;
use App\Models\CustomDomain;
use App\Models\Project;
use App\Models\Scopes\WorkspaceScope;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicProjectResolver
{
    public function fromRequest(Request $request, ?string $pathKey = null): ?Project
    {
        $custom = $this->customDomainFromRequest($request);

        if ($custom !== null && $custom->status === DomainStatus::Active) {
            return Project::query()->withoutGlobalScope(WorkspaceScope::class)->find($custom->project_id);
        }

        $host = $this->host($request);
        $platform = strtolower((string) config('anytdocs.domain'));

        if ($platform !== '' && $platform !== 'localhost' && str_ends_with($host, '.'.$platform)) {
            $subdomain = Str::before($host, '.'.$platform);

            if ($subdomain !== '' && $subdomain !== 'fallback' && $subdomain !== 'www') {
                return Project::query()
                    ->withoutGlobalScope(WorkspaceScope::class)
                    ->where('subdomain', $subdomain)
                    ->first();
            }
        }

        if ($pathKey !== null) {
            return Project::query()
                ->withoutGlobalScope(WorkspaceScope::class)
                ->where('subdomain', $pathKey)
                ->first();
        }

        return null;
    }

    public function customDomainFromRequest(Request $request): ?CustomDomain
    {
        $host = $this->host($request);

        if ($host === '') {
            return null;
        }

        return CustomDomain::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('hostname', $host)
            ->first();
    }

    public function servesProjectAtCustomHost(Request $request, Project $project): bool
    {
        $custom = $this->customDomainFromRequest($request);

        return $custom !== null
            && $custom->status === DomainStatus::Active
            && $custom->project_id === $project->id;
    }

    public function isPlatformHost(Request $request): bool
    {
        $host = $this->rawHost($request);

        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            return true;
        }

        $appHost = $this->normalizeHost((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($appHost !== null && $host === $appHost) {
            return true;
        }

        $platform = $this->normalizeHost((string) config('anytdocs.domain'));

        if ($platform !== null && $platform !== 'localhost' && ($host === $platform || $host === 'www.'.$platform)) {
            return true;
        }

        return false;
    }

    /**
     * Hostname used to resolve a docs project.
     *
     * When the request hits the SSL for SaaS fallback origin, the custom hostname
     * is taken from X-Forwarded-Host / X-Original-Host so docs.example.com can
     * still resolve if the origin Host header is fallback.{app_domain}.
     */
    public function host(Request $request): string
    {
        $raw = $this->rawHost($request);
        $fallback = $this->normalizeHost(PlatformCloudflareConfig::publicCnameTarget());

        if ($fallback !== null && $raw === $fallback) {
            $forwarded = $this->forwardedHost($request);

            if ($forwarded !== null) {
                return $forwarded;
            }
        }

        return $raw;
    }

    public function rawHost(Request $request): string
    {
        return $this->normalizeHost((string) $request->server->get('HTTP_HOST', $request->getHost())) ?? '';
    }

    private function forwardedHost(Request $request): ?string
    {
        foreach (['X-Forwarded-Host', 'X-Original-Host'] as $header) {
            $value = $request->headers->get($header);

            if (! is_string($value) || $value === '') {
                continue;
            }

            $first = trim(explode(',', $value)[0]);
            $host = $this->normalizeHost($first);

            if ($host !== null) {
                return $host;
            }
        }

        return null;
    }

    private function normalizeHost(mixed $host): ?string
    {
        if (! is_string($host) && ! is_numeric($host)) {
            return null;
        }

        $normalized = strtolower(trim((string) $host));

        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, '://')) {
            $parsed = parse_url($normalized, PHP_URL_HOST);
            $normalized = is_string($parsed) ? strtolower($parsed) : $normalized;
        }

        $normalized = rtrim(Str::before($normalized, ':'), '.');

        return $normalized !== '' ? $normalized : null;
    }
}
