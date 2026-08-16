<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\CustomDomain;
use App\Models\DocumentationVersion;
use App\Models\Project;
use App\Models\ProjectLanguage;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class PlanEnforcer
{
    public function afterPlanChange(Workspace $workspace): void
    {
        $limits = $workspace->plan?->limits ?? [];
        $violations = [];

        $projectLimit = isset($limits['projects']) ? (int) $limits['projects'] : null;

        if ($projectLimit !== null) {
            $count = Project::query()->withoutGlobalScopes()->where('workspace_id', $workspace->id)->count();

            if ($count > $projectLimit) {
                $violations[] = "projects: {$count}/{$projectLimit}";
            }
        }

        $memberLimit = isset($limits['members']) ? (int) $limits['members'] : null;

        if ($memberLimit !== null) {
            $count = WorkspaceMember::query()->where('workspace_id', $workspace->id)->count();

            if ($count > $memberLimit) {
                $violations[] = "members: {$count}/{$memberLimit}";
            }
        }

        $domainLimit = isset($limits['custom_domains']) ? (int) $limits['custom_domains'] : null;

        if ($domainLimit !== null) {
            $count = CustomDomain::query()->withoutGlobalScopes()->where('workspace_id', $workspace->id)->count();

            if ($count > $domainLimit) {
                $violations[] = "custom_domains: {$count}/{$domainLimit}";
            }
        }

        if (! ($workspace->plan?->features['versioning'] ?? false)) {
            $versionCount = DocumentationVersion::query()->withoutGlobalScopes()->where('workspace_id', $workspace->id)->count();

            if ($versionCount > 1) {
                $violations[] = "versions: {$versionCount}/1";
            }
        }

        if (! ($workspace->plan?->features['localization'] ?? false)) {
            $languageCount = ProjectLanguage::query()->withoutGlobalScopes()->where('workspace_id', $workspace->id)->count();

            if ($languageCount > 1) {
                $violations[] = "languages: {$languageCount}/1 per project limit";
            }
        }

        AuditLog::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => null,
            'action' => 'billing.plan_applied',
            'subject_type' => Workspace::class,
            'subject_id' => $workspace->id,
            'metadata' => [
                'plan' => $workspace->plan?->slug,
                'over_limit' => $violations,
            ],
        ]);
    }
}
