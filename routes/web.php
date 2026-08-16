<?php

use App\Http\Controllers\Admin\PlatformAdminController;
use App\Http\Controllers\Admin\PlatformAiSettingsController;
use App\Http\Controllers\Admin\PlatformCloudflareSettingsController;
use App\Http\Controllers\Admin\PlatformSettingsSectionsController;
use App\Http\Controllers\AiDocumentationController;
use App\Http\Controllers\AiPageGenerateController;
use App\Http\Controllers\AiPageReviewController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Editor\BlockController;
use App\Http\Controllers\Editor\MarkdownImportController;
use App\Http\Controllers\Editor\PageController;
use App\Http\Controllers\Editor\UploadController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectSettingsController;
use App\Http\Controllers\PublicDocsController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\EnsureNotSuspended;
use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\RedirectIfOnboarded;
use Illuminate\Support\Facades\Route;

Route::post('stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::get('/', [MarketingController::class, 'home'])->name('home');
Route::get('features', [MarketingController::class, 'features'])->name('marketing.features');
Route::get('pricing', [MarketingController::class, 'pricing'])->name('marketing.pricing');
Route::get('examples', [MarketingController::class, 'examples'])->name('marketing.examples');
Route::get('faq', [MarketingController::class, 'faq'])->name('marketing.faq');
Route::get('privacy', [MarketingController::class, 'privacy'])->name('marketing.privacy');
Route::get('terms', [MarketingController::class, 'terms'])->name('marketing.terms');
Route::get('contact', [MarketingController::class, 'contact'])->name('marketing.contact');
Route::post('contact', [MarketingController::class, 'submitContact'])
    ->middleware('throttle:contact')
    ->name('marketing.contact.submit');

Route::get('docs/{project}/sitemap.xml', [PublicDocsController::class, 'sitemap'])->name('docs.sitemap');
Route::get('docs/{project}/robots.txt', [PublicDocsController::class, 'robots'])->name('docs.robots');
Route::get('docs/{project}/search', [PublicDocsController::class, 'search'])->name('docs.search');
Route::post('docs/{project}/ask', [PublicDocsController::class, 'ask'])
    ->middleware('throttle:docs-ask')
    ->name('docs.ask');
Route::post('docs/{project}/unlock', [PublicDocsController::class, 'unlock'])->name('docs.unlock');
Route::post('docs/{project}/feedback', [PublicDocsController::class, 'feedback'])->name('docs.feedback');
Route::get('docs/{project}/directory', [PublicDocsController::class, 'directory'])->name('docs.directory');
Route::get('docs/{project}/announcements', [PublicDocsController::class, 'announcements'])->name('docs.announcements');
Route::get('docs/{project}/announcements/{slug}', [PublicDocsController::class, 'announcement'])->name('docs.announcements.show');
Route::get('docs/{project}/changelog', [PublicDocsController::class, 'changelog'])->name('docs.changelog');
Route::get('docs/{project}/changelog/{slug}', [PublicDocsController::class, 'changelogShow'])->name('docs.changelog.show');
Route::get('docs/{project}/{docVersion?}/{docLocale?}/{docSlug?}', [PublicDocsController::class, 'show'])
    ->where('docVersion', '^(?!sitemap\.xml|robots\.txt|search|ask|directory|announcements|changelog)[^/]+$')
    ->name('docs.show');

Route::middleware('guest')->group(function () {
    Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->whereIn('provider', ['github', 'google'])
        ->name('oauth.redirect');
    Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->whereIn('provider', ['github', 'google'])
        ->name('oauth.callback');
});

Route::get('invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');

Route::middleware('auth')->group(function () {
    Route::post('invitations/{token}/accept', [MemberController::class, 'accept'])->name('invitations.accept');
});

Route::middleware(['auth', 'verified', EnsureNotSuspended::class])->group(function () {
    Route::get('onboarding/subdomain-availability', [OnboardingController::class, 'subdomainAvailability'])
        ->middleware('throttle:subdomain-availability')
        ->name('onboarding.subdomain');

    Route::middleware(RedirectIfOnboarded::class)->group(function () {
        Route::get('onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
        Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
    });

    Route::middleware(EnsureOnboarded::class)->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
        Route::get('projects/{project}/editor', [PageController::class, 'editor'])->name('projects.editor');
        Route::post('projects/{project}/pages', [PageController::class, 'store'])->name('pages.store');
        Route::patch('projects/{project}/pages/{page}', [PageController::class, 'update'])->name('pages.update');
        Route::post('projects/{project}/pages/{page}/publish', [PageController::class, 'publish'])->name('pages.publish');
        Route::post('projects/{project}/pages/{page}/unpublish', [PageController::class, 'unpublish'])->name('pages.unpublish');
        Route::post('projects/{project}/pages/{page}/duplicate', [PageController::class, 'duplicate'])->name('pages.duplicate');
        Route::post('projects/{project}/pages/{page}/revisions/{revision}', [PageController::class, 'restore'])->name('pages.restore');
        Route::get('projects/{project}/pages/{page}/revisions/{revision}', [PageController::class, 'showRevision'])->name('pages.revisions.show');
        Route::post('projects/{project}/pages/{page}/archive-restore', [PageController::class, 'restoreArchive'])->name('pages.archive-restore');
        Route::delete('projects/{project}/pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
        Route::post('projects/{project}/uploads', [UploadController::class, 'store'])->name('pages.uploads');
        Route::post('projects/{project}/import', [MarkdownImportController::class, 'store'])->name('pages.import');
        Route::post('projects/{project}/pages/reorder', [PageController::class, 'reorder'])->name('pages.reorder');
        Route::post('projects/{project}/ai/generate', [AiDocumentationController::class, 'generate'])
            ->middleware('throttle:ai-generation')
            ->name('projects.ai.generate');
        Route::get('projects/{project}/ai/jobs/{job}', [AiDocumentationController::class, 'show'])
            ->name('projects.ai.show');
        Route::post('projects/{project}/pages/{page}/ai/review', [AiPageReviewController::class, 'review'])
            ->middleware('throttle:ai-generation')
            ->name('pages.ai.review');
        Route::post('projects/{project}/pages/{page}/ai/apply', [AiPageReviewController::class, 'apply'])
            ->name('pages.ai.apply');
        Route::post('projects/{project}/pages/{page}/ai/generate', [AiPageGenerateController::class, 'generate'])
            ->middleware('throttle:ai-generation')
            ->name('pages.ai.generate');
        Route::post('projects/{project}/pages/{page}/ai/apply-generation', [AiPageGenerateController::class, 'apply'])
            ->name('pages.ai.apply-generation');

        Route::get('projects/{project}/settings', [ProjectSettingsController::class, 'show'])->name('projects.settings');
        Route::post('projects/{project}/visibility', [ProjectSettingsController::class, 'visibility'])->name('projects.visibility');
        Route::post('projects/{project}/branding', [ProjectSettingsController::class, 'branding'])->name('projects.branding');
        Route::post('projects/{project}/header-links', [ProjectSettingsController::class, 'headerLinks'])->name('projects.header-links');
        Route::post('projects/{project}/ai-index', [ProjectSettingsController::class, 'reindexAiKnowledge'])->name('projects.ai-index');
        Route::post('projects/{project}/domains', [ProjectSettingsController::class, 'storeDomain'])->name('projects.domains.store');
        Route::post('projects/{project}/domains/{domain}/verify', [ProjectSettingsController::class, 'verifyDomain'])->name('projects.domains.verify');
        Route::post('projects/{project}/domains/{domain}/primary', [ProjectSettingsController::class, 'primaryDomain'])->name('projects.domains.primary');
        Route::delete('projects/{project}/domains/{domain}', [ProjectSettingsController::class, 'destroyDomain'])->name('projects.domains.destroy');
        Route::post('projects/{project}/versions', [ProjectSettingsController::class, 'storeVersion'])->name('projects.versions.store');
        Route::post('projects/{project}/versions/{version}/default', [ProjectSettingsController::class, 'defaultVersion'])->name('projects.versions.default');
        Route::post('projects/{project}/languages', [ProjectSettingsController::class, 'storeLanguage'])->name('projects.languages.store');
        Route::post('projects/{project}/languages/{language}/default', [ProjectSettingsController::class, 'defaultLanguage'])->name('projects.languages.default');

        Route::get('projects/{project}/posts', [PostController::class, 'index'])->name('projects.posts.index');
        Route::post('projects/{project}/posts', [PostController::class, 'store'])->name('projects.posts.store');
        Route::patch('projects/{project}/posts/{post}', [PostController::class, 'update'])->name('projects.posts.update');
        Route::post('projects/{project}/posts/{post}/publish', [PostController::class, 'publish'])->name('projects.posts.publish');
        Route::delete('projects/{project}/posts/{post}', [PostController::class, 'destroy'])->name('projects.posts.destroy');

        Route::get('blocks', [BlockController::class, 'index'])->name('blocks.index');
        Route::post('blocks', [BlockController::class, 'store'])->name('blocks.store');
        Route::patch('blocks/{block}', [BlockController::class, 'update'])->name('blocks.update');
        Route::delete('blocks/{block}', [BlockController::class, 'destroy'])->name('blocks.destroy');

        Route::post('workspace/switch/{workspace}', [WorkspaceController::class, 'switch'])->name('workspace.switch');

        Route::get('members', [MemberController::class, 'index'])->name('members.index');
        Route::post('members/invitations', [MemberController::class, 'invite'])->name('members.invite');
        Route::post('members/invitations/{invitation}/resend', [MemberController::class, 'resendInvitation'])->name('members.invitations.resend');
        Route::delete('members/invitations/{invitation}', [MemberController::class, 'cancelInvitation'])->name('members.invitations.cancel');
        Route::patch('members/{member}', [MemberController::class, 'updateRole'])->name('members.update');
        Route::delete('members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');

        Route::get('billing', [BillingController::class, 'show'])->name('billing.show');
        Route::post('billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
        Route::post('billing/portal', [BillingController::class, 'portal'])->name('billing.portal');

        Route::get('analytics', AnalyticsController::class)->name('analytics.show');
        Route::get('analytics/export', [AnalyticsController::class, 'export'])->name('analytics.export');
        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
    });
});

Route::middleware(['auth', 'verified', 'platform'])->prefix('platform')->group(function () {
    Route::get('/', [PlatformAdminController::class, 'dashboard'])->name('platform.dashboard');
    Route::get('settings', [PlatformAdminController::class, 'settings'])->name('platform.settings');
    Route::post('settings', [PlatformAdminController::class, 'updateSettings'])->name('platform.settings.update');
    Route::post('settings/general', [PlatformSettingsSectionsController::class, 'updateGeneral'])->name('platform.settings.general.update');
    Route::post('settings/smtp', [PlatformSettingsSectionsController::class, 'updateSmtp'])->name('platform.settings.smtp.update');
    Route::post('settings/smtp/test', [PlatformSettingsSectionsController::class, 'testSmtp'])->name('platform.settings.smtp.test');
    Route::post('settings/payment', [PlatformSettingsSectionsController::class, 'updatePayment'])->name('platform.settings.payment.update');
    Route::post('settings/ai', [PlatformAiSettingsController::class, 'update'])->name('platform.settings.ai.update');
    Route::post('settings/ai/test', [PlatformAiSettingsController::class, 'test'])->name('platform.settings.ai.test');
    Route::post('settings/cloudflare', [PlatformCloudflareSettingsController::class, 'update'])->name('platform.settings.cloudflare.update');
    Route::post('settings/cloudflare/test', [PlatformCloudflareSettingsController::class, 'test'])->name('platform.settings.cloudflare.test');
    Route::post('system/clear-cache', [PlatformAdminController::class, 'clearCache'])->name('platform.system.clear-cache');
    Route::post('workspaces/{workspace}/plan', [PlatformAdminController::class, 'updateWorkspacePlan'])->name('platform.workspaces.plan');
    Route::post('workspaces/{workspace}/suspend', [PlatformAdminController::class, 'suspendWorkspace'])->name('platform.workspaces.suspend');
    Route::post('workspaces/{workspace}/restore', [PlatformAdminController::class, 'restoreWorkspace'])->name('platform.workspaces.restore');
    Route::post('plans/{plan}', [PlatformAdminController::class, 'updatePlan'])->name('platform.plans.update');
    Route::post('users/{user}/admin', [PlatformAdminController::class, 'toggleAdmin'])->name('platform.users.toggle');
    Route::post('users/{user}/verify-email', [PlatformAdminController::class, 'verifyUserEmail'])->name('platform.users.verify-email');
    Route::post('users/{user}/suspend', [PlatformAdminController::class, 'suspendUser'])->name('platform.users.suspend');
    Route::post('users/{user}/restore', [PlatformAdminController::class, 'restoreUser'])->name('platform.users.restore');
    Route::post('users/{user}/impersonate', [PlatformAdminController::class, 'impersonate'])->name('platform.users.impersonate');
    Route::post('domains/{domain}/retry', [PlatformAdminController::class, 'retryDomain'])->name('platform.domains.retry');
    Route::delete('domains/{domain}', [PlatformAdminController::class, 'disconnectDomain'])->name('platform.domains.destroy');
    Route::post('reports/{report}/resolve', [PlatformAdminController::class, 'resolveReport'])->name('platform.reports.resolve');
    Route::post('impersonation/stop', [PlatformAdminController::class, 'stopImpersonating'])->name('platform.impersonation.stop');
});

Route::redirect('/admin', '/platform')->middleware(['auth', 'verified', 'platform']);

require __DIR__.'/settings.php';
