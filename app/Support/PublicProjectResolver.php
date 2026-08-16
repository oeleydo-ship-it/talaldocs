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
        $host = strtolower((string) $request->getHost());
        $platform = strtolower((string) config('anytdocs.domain'));

        $custom = CustomDomain::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('hostname', $host)
            ->where('status', DomainStatus::Active)
            ->first();

        if ($custom !== null) {
            return Project::query()->withoutGlobalScope(WorkspaceScope::class)->find($custom->project_id);
        }

        if (str_ends_with($host, '.'.$platform)) {
            $subdomain = Str::before($host, '.'.$platform);

            return Project::query()
                ->withoutGlobalScope(WorkspaceScope::class)
                ->where('subdomain', $subdomain)
                ->first();
        }

        if ($pathKey !== null) {
            return Project::query()
                ->withoutGlobalScope(WorkspaceScope::class)
                ->where('subdomain', $pathKey)
                ->first();
        }

        return null;
    }
}
