<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\Workspace;
use App\Policies\ProjectPolicy;
use App\Policies\WorkspacePolicy;
use App\Support\DisabledInertiaSsrGateway;
use App\Support\PlatformAiConfig;
use App\Support\PlatformConfig;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Ssr\Gateway as InertiaSsrGateway;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Never call the Node SSR server unless explicitly opted in. A hanging
        // HTTP call to 127.0.0.1:13714 becomes Cloudflare 502 on Inertia pages.
        if (! filter_var(env('INERTIA_SSR_ENABLED', false), FILTER_VALIDATE_BOOL)) {
            $this->app->bind(InertiaSsrGateway::class, DisabledInertiaSsrGateway::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        config(['inertia.ssr.enabled' => false]);

        if (filter_var(env('INERTIA_SSR_ENABLED', false), FILTER_VALIDATE_BOOL)) {
            config(['inertia.ssr.enabled' => true]);
        }

        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configurePlatformAi();
        $this->configurePlatformSettings();
        $this->configureViews();
    }

    protected function configureViews(): void
    {
        View::composer('app', function ($view): void {
            $view->with('platformBranding', PlatformConfig::brandingForFrontend());
        });
    }

    protected function configurePlatformSettings(): void
    {
        try {
            PlatformConfig::apply();
        } catch (Throwable $e) {
            if (! PlatformConfig::isDatabaseUnavailable($e)) {
                throw $e;
            }
        }
    }

    protected function configurePlatformAi(): void
    {
        try {
            PlatformAiConfig::apply();
        } catch (Throwable $e) {
            if (! PlatformConfig::isDatabaseUnavailable($e)) {
                throw $e;
            }
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureAuthorization(): void
    {
        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('onboarding', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('subdomain-check', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('subdomain-availability', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('oauth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('ai-generation', fn (Request $request) => Limit::perMinute(5)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('docs-ask', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('contact', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('install', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }
}
