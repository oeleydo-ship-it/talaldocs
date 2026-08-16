<?php

namespace App\Support;

use App\Enums\AiGenerationJobStatus;
use App\Models\AiGenerationJob;
use App\Models\CustomDomain;
use App\Models\DocumentationVersion;
use App\Models\Project;
use App\Models\ProjectLanguage;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Validation\ValidationException;

class PlanGate
{
    public function limit(Workspace $workspace, string $key): ?int
    {
        $limits = $workspace->plan?->limits ?? [];
        $value = $limits[$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function hasFeature(Workspace $workspace, string $feature): bool
    {
        return (bool) ($workspace->plan?->features[$feature] ?? false);
    }

    public function canCreateProject(Workspace $workspace): bool
    {
        $limit = $this->limit($workspace, 'projects');

        if ($limit === null) {
            return true;
        }

        $count = Project::query()->withoutGlobalScopes()->where('workspace_id', $workspace->id)->count();

        return $count < $limit;
    }

    public function assertCanCreateProject(Workspace $workspace): void
    {
        if ($this->canCreateProject($workspace)) {
            return;
        }

        $limit = $this->limit($workspace, 'projects');

        throw ValidationException::withMessages([
            'plan' => 'Your plan allows '.$limit.' project(s). Upgrade to add more.',
        ]);
    }

    public function assertCanInvite(Workspace $workspace): void
    {
        $limit = $this->limit($workspace, 'members');

        if ($limit === null) {
            return;
        }

        $count = WorkspaceMember::query()->where('workspace_id', $workspace->id)->count();

        if ($count >= $limit) {
            throw ValidationException::withMessages([
                'plan' => 'Your plan allows '.$limit.' member(s). Upgrade to invite more.',
            ]);
        }
    }

    public function assertCanAddDomain(Workspace $workspace): void
    {
        if (! $this->hasFeature($workspace, 'custom_domain')) {
            throw ValidationException::withMessages([
                'plan' => 'Custom domains are not included in your plan.',
            ]);
        }

        $limit = $this->limit($workspace, 'custom_domains');

        if ($limit === null) {
            return;
        }

        $count = CustomDomain::query()->withoutGlobalScopes()->where('workspace_id', $workspace->id)->count();

        if ($count >= $limit) {
            throw ValidationException::withMessages([
                'plan' => 'Your plan allows '.$limit.' custom domain(s).',
            ]);
        }
    }

    public function assertCanAddVersion(Workspace $workspace): void
    {
        if ($this->hasFeature($workspace, 'versioning')) {
            return;
        }

        $count = DocumentationVersion::query()->withoutGlobalScopes()->where('workspace_id', $workspace->id)->count();

        if ($count >= 1) {
            throw ValidationException::withMessages([
                'plan' => 'Additional versions require a Business plan or higher.',
            ]);
        }
    }

    public function assertCanAddLanguage(Workspace $workspace, int $projectId): void
    {
        if ($this->hasFeature($workspace, 'localization')) {
            return;
        }

        $count = ProjectLanguage::query()
            ->withoutGlobalScopes()
            ->where('project_id', $projectId)
            ->count();

        if ($count >= 1) {
            throw ValidationException::withMessages([
                'plan' => 'Additional languages require a Business plan or higher.',
            ]);
        }
    }

    public function canUseAiGeneration(Workspace $workspace): bool
    {
        if ($this->hasFeature($workspace, 'ai_generation')) {
            return true;
        }

        return $this->monthlyAiGenerationCount($workspace) < (int) config('ai.free_monthly_limit', 1);
    }

    public function assertCanUseAiGeneration(Workspace $workspace): void
    {
        if ($this->canUseAiGeneration($workspace)) {
            return;
        }

        throw ValidationException::withMessages([
            'plan' => 'AI generation limit reached for this month. Upgrade to Pro for unlimited generations.',
        ]);
    }

    public function aiGenerationsRemaining(Workspace $workspace): ?int
    {
        if ($this->hasFeature($workspace, 'ai_generation')) {
            return null;
        }

        $limit = (int) config('ai.free_monthly_limit', 1);
        $used = $this->monthlyAiGenerationCount($workspace);

        return max(0, $limit - $used);
    }

    private function monthlyAiGenerationCount(Workspace $workspace): int
    {
        return AiGenerationJob::query()
            ->withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->whereIn('status', [
                AiGenerationJobStatus::Pending,
                AiGenerationJobStatus::Processing,
                AiGenerationJobStatus::Completed,
            ])
            ->count();
    }
}
