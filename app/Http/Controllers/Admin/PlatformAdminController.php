<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DomainStatus;
use App\Http\Controllers\Controller;
use App\Jobs\VerifyCustomDomainJob;
use App\Models\AiGenerationJob;
use App\Models\ContentReport;
use App\Models\CustomDomain;
use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Models\Project;
use App\Models\Scopes\WorkspaceScope;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AiDocumentationGenerator;
use App\Services\CloudflareService;
use App\Support\PlanBlueprint;
use App\Support\PlatformAiConfig;
use App\Support\PlatformAudit;
use App\Support\PlatformCloudflareConfig;
use App\Support\PlatformConfig;
use App\Support\PlatformPublicContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlatformAdminController extends Controller
{
    public function __construct(
        private PlatformAudit $audit,
        private CloudflareService $cloudflare,
    ) {}

    public function dashboard(Request $request): Response
    {
        $tab = (string) $request->string('tab', 'overview');
        $settingsSection = (string) $request->string('section', 'general');
        $search = trim((string) $request->string('q'));

        return Inertia::render('platform/dashboard', [
            'tab' => $tab,
            'settingsSection' => $settingsSection,
            'search' => $search,
            'stats' => [
                'users' => User::query()->count(),
                'workspaces' => Workspace::query()->count(),
                'projects' => Project::query()->withoutGlobalScope(WorkspaceScope::class)->count(),
                'domains' => CustomDomain::query()->withoutGlobalScope(WorkspaceScope::class)->count(),
                'subscriptions' => Workspace::query()->whereNotNull('stripe_id')->count(),
                'open_reports' => ContentReport::query()->where('status', 'open')->count(),
                'failed_domains' => CustomDomain::query()->withoutGlobalScope(WorkspaceScope::class)->where('status', DomainStatus::Failed)->count(),
                'failed_jobs' => (int) DB::table('failed_jobs')->count(),
                'pending_jobs' => (int) DB::table('jobs')->count(),
                'ai_configured' => AiDocumentationGenerator::isConfigured(),
                'ai_generations_total' => AiGenerationJob::query()->withoutGlobalScope(WorkspaceScope::class)->count(),
                'ai_generations_this_month' => AiGenerationJob::query()
                    ->withoutGlobalScope(WorkspaceScope::class)
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count(),
            ],
            'platformSettings' => [
                'app_domain' => (string) config('anytdocs.domain'),
                'app_url' => (string) config('app.url'),
                'reserved_subdomains' => (array) config('anytdocs.reserved_subdomains', []),
                'maintenance_mode' => (bool) Cache::get('platform.maintenance_mode', false),
                'mail_configured' => PlatformConfig::mailConfigured(),
                'stripe_configured' => PlatformConfig::stripeConfigured(),
            ],
            'generalSettings' => PlatformConfig::generalSettings(),
            'smtpSettings' => PlatformConfig::smtpSettings(),
            'paymentSettings' => PlatformConfig::paymentSettings(),
            'aiSettings' => PlatformAiConfig::toPublicArray(),
            'cloudflareSettings' => PlatformCloudflareConfig::toPublicArray(),
            'publicContent' => PlatformPublicContent::forAdmin(),
            'recentActivity' => PlatformAuditLog::query()
                ->with('admin:id,name')
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(fn (PlatformAuditLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'admin' => $log->admin?->name,
                    'metadata' => $log->metadata,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
            'workspaces' => Workspace::query()
                ->with('plan:id,name')
                ->withCount('projects')
                ->when($search !== '' && $tab === 'workspaces', fn ($query) => $query
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%'))
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (Workspace $workspace): array => [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'slug' => $workspace->slug,
                    'plan' => $workspace->plan?->name,
                    'plan_id' => $workspace->plan_id,
                    'projects_count' => $workspace->projects_count,
                    'suspended_at' => $workspace->suspended_at?->toIso8601String(),
                    'stripe_id' => filled($workspace->stripe_id),
                    'created_at' => $workspace->created_at?->toIso8601String(),
                ]),
            'users' => User::query()
                ->withCount('workspaces')
                ->when($search !== '' && $tab === 'users', fn ($query) => $query
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%'))
                ->latest('id')
                ->limit(50)
                ->get(['id', 'name', 'email', 'is_platform_admin', 'suspended_at', 'email_verified_at', 'created_at', 'current_workspace_id'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_platform_admin' => $user->is_platform_admin,
                    'suspended_at' => $user->suspended_at?->toIso8601String(),
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'created_at' => $user->created_at?->toIso8601String(),
                    'workspaces_count' => $user->workspaces_count,
                ]),
            'plans' => Plan::query()->orderBy('price_cents')->get(),
            'allDomains' => CustomDomain::query()
                ->withoutGlobalScope(WorkspaceScope::class)
                ->with('project:id,name,slug')
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (CustomDomain $domain): array => [
                    'id' => $domain->id,
                    'hostname' => $domain->hostname,
                    'status' => $domain->status->value,
                    'is_primary' => $domain->is_primary,
                    'project' => $domain->project?->name,
                    'project_slug' => $domain->project?->slug,
                    'verified_at' => $domain->verified_at?->toIso8601String(),
                    'error_message' => $domain->error_message,
                    'ssl_status' => $domain->ssl_status,
                    'ssl_ready' => $domain->sslReady(),
                    'last_checked_at' => $domain->last_checked_at?->toIso8601String(),
                ]),
            'failedDomains' => CustomDomain::query()
                ->withoutGlobalScope(WorkspaceScope::class)
                ->with('project:id,name,slug')
                ->where('status', DomainStatus::Failed)
                ->latest('id')
                ->limit(25)
                ->get()
                ->map(fn (CustomDomain $domain): array => [
                    'id' => $domain->id,
                    'hostname' => $domain->hostname,
                    'project' => $domain->project?->name,
                    'error_message' => $domain->error_message,
                    'last_checked_at' => $domain->last_checked_at?->toIso8601String(),
                ]),
            'reports' => ContentReport::query()
                ->with(['project:id,name,slug', 'page:id,title,slug'])
                ->latest('id')
                ->limit(25)
                ->get()
                ->map(fn (ContentReport $report): array => [
                    'id' => $report->id,
                    'reason' => $report->reason,
                    'details' => $report->details,
                    'status' => $report->status,
                    'project' => $report->project?->name,
                    'page' => $report->page?->title,
                    'created_at' => $report->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function settings(Request $request): Response
    {
        $request->query->set('tab', 'settings');

        return $this->dashboard($request);
    }

    public function updateWorkspacePlan(Request $request, int $workspace): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $model = Workspace::query()->findOrFail($workspace);
        $model->update(['plan_id' => $data['plan_id']]);
        $this->audit->record($request->user(), 'workspace.plan_updated', $model, ['plan_id' => $data['plan_id']]);

        return back();
    }

    public function updatePlan(Request $request, int $plan): RedirectResponse
    {
        $data = $request->validate([
            'price_cents' => ['required', 'integer', 'min:0'],
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
            'limits' => ['required', 'array'],
            'limits.projects' => ['nullable', 'integer', 'min:0'],
            'limits.members' => ['nullable', 'integer', 'min:0'],
            'limits.custom_domains' => ['nullable', 'integer', 'min:0'],
            'features' => ['required', 'array'],
            'features.*' => ['boolean'],
        ]);

        $model = Plan::query()->findOrFail($plan);

        $stripePriceId = filled($data['stripe_price_id'] ?? null) ? trim($data['stripe_price_id']) : null;

        if ($data['price_cents'] > 0 && filled($stripePriceId) === false) {
            return back()->withErrors([
                'stripe_price_id' => 'Paid plans require a Stripe Price ID when Stripe is used for billing.',
            ]);
        }

        $model->forceFill([
            'price_cents' => $data['price_cents'],
            'stripe_price_id' => $stripePriceId,
            'limits' => PlanBlueprint::normalizeLimits($data['limits']),
            'features' => PlanBlueprint::normalizeFeatures($data['features']),
        ])->save();

        $this->audit->record($request->user(), 'plan.updated', $model);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Plan saved.'),
        ]);

        return back();
    }

    public function toggleAdmin(Request $request, int $user): RedirectResponse
    {
        abort_if($request->user()?->id === $user, 422);

        $model = User::query()->findOrFail($user);
        $model->forceFill(['is_platform_admin' => ! $model->is_platform_admin])->save();
        $this->audit->record($request->user(), 'user.admin_toggled', $model);

        return back();
    }

    public function verifyUserEmail(Request $request, int $user): RedirectResponse
    {
        $model = User::query()->findOrFail($user);
        $model->forceFill(['email_verified_at' => now()])->save();
        $this->audit->record($request->user(), 'user.email_verified', $model);

        return back();
    }

    public function retryDomain(Request $request, int $domain): RedirectResponse
    {
        $model = CustomDomain::query()->withoutGlobalScope(WorkspaceScope::class)->findOrFail($domain);
        $model->forceFill([
            'status' => DomainStatus::Pending,
            'error_message' => null,
        ])->save();

        VerifyCustomDomainJob::dispatch($model->id);
        $this->audit->record($request->user(), 'domain.retry', $model);

        return back();
    }

    public function disconnectDomain(Request $request, int $domain): RedirectResponse
    {
        $model = CustomDomain::query()->withoutGlobalScope(WorkspaceScope::class)->findOrFail($domain);
        $this->cloudflare->deleteCustomHostname($model);
        $this->audit->record($request->user(), 'domain.disconnected', $model);
        $model->delete();

        return back();
    }

    public function clearCache(Request $request): RedirectResponse
    {
        Artisan::call('cache:clear');
        $this->audit->record($request->user(), 'system.cache_cleared');

        return back();
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'maintenance_mode' => ['required', 'boolean'],
        ]);

        Cache::forever('platform.maintenance_mode', $data['maintenance_mode']);
        $this->audit->record($request->user(), 'platform.settings_updated', null, [
            'maintenance_mode' => $data['maintenance_mode'],
        ]);

        return redirect()->route('platform.dashboard', ['tab' => 'settings', 'section' => 'platform']);
    }

    private function mailConfigured(): bool
    {
        return PlatformConfig::mailConfigured();
    }

    public function suspendUser(Request $request, int $user): RedirectResponse
    {
        abort_if($request->user()?->id === $user, 422);

        $model = User::query()->findOrFail($user);
        $model->forceFill(['suspended_at' => now()])->save();
        $this->audit->record($request->user(), 'user.suspended', $model);

        return back();
    }

    public function restoreUser(Request $request, int $user): RedirectResponse
    {
        $model = User::query()->findOrFail($user);
        $model->forceFill(['suspended_at' => null])->save();
        $this->audit->record($request->user(), 'user.restored', $model);

        return back();
    }

    public function suspendWorkspace(Request $request, int $workspace): RedirectResponse
    {
        $model = Workspace::query()->findOrFail($workspace);
        $model->forceFill(['suspended_at' => now()])->save();
        $this->audit->record($request->user(), 'workspace.suspended', $model);

        return back();
    }

    public function restoreWorkspace(Request $request, int $workspace): RedirectResponse
    {
        $model = Workspace::query()->findOrFail($workspace);
        $model->forceFill(['suspended_at' => null])->save();
        $this->audit->record($request->user(), 'workspace.restored', $model);

        return back();
    }

    public function resolveReport(Request $request, int $report): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:resolved,dismissed'],
        ]);

        $model = ContentReport::query()->findOrFail($report);
        $model->forceFill([
            'status' => $data['status'],
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ])->save();

        $this->audit->record($request->user(), 'report.reviewed', $model, ['status' => $data['status']]);

        return back();
    }

    public function impersonate(Request $request, int $user): RedirectResponse
    {
        abort_if($request->user()?->id === $user, 422);

        $target = User::query()->findOrFail($user);
        abort_if($target->is_platform_admin, 403);

        $this->audit->record($request->user(), 'user.impersonated', $target);

        $request->session()->put('platform.impersonator_id', $request->user()?->id);
        Auth::login($target);

        return redirect()->route('dashboard');
    }

    public function stopImpersonating(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull('platform.impersonator_id');

        if ($adminId) {
            $admin = User::query()->find($adminId);

            if ($admin?->is_platform_admin) {
                Auth::login($admin);

                return redirect()->route('platform.dashboard');
            }
        }

        return redirect()->route('platform.dashboard');
    }
}
