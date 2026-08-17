<?php

use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Workspace;
use App\Support\BillingAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function enableBilling(array $overrides = []): PlatformSetting
{
    $settings = PlatformSetting::instance();
    $settings->fill(array_merge([
        'stripe_enabled' => true,
        'stripe_key' => 'pk_test_123',
        'stripe_secret' => 'sk_test_456',
        'billing_enforced' => true,
        'trial_days' => 14,
        'trial_requires_card' => false,
    ], $overrides))->save();

    return $settings->fresh();
}

it('allows access during an active trial when billing is enforced', function (): void {
    enableBilling();

    $user = User::factory()->onboarded()->create();
    $workspace = Workspace::query()->findOrFail($user->current_workspace_id);
    $workspace->forceFill([
        'trial_ends_at' => now()->addDays(10),
        'subscription_status' => null,
    ])->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('blocks the dashboard after the trial expires without a subscription', function (): void {
    enableBilling();

    $user = User::factory()->onboarded()->create();
    $workspace = Workspace::query()->findOrFail($user->current_workspace_id);
    $workspace->forceFill([
        'trial_ends_at' => now()->subDay(),
        'subscription_status' => null,
    ])->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('billing.show'));

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertRedirect(route('billing.show'));

    $this->actingAs($user)
        ->get(route('billing.show'))
        ->assertOk();
});

it('allows access when the workspace has an active stripe subscription', function (): void {
    enableBilling(['trial_requires_card' => true]);

    $user = User::factory()->onboarded()->create();
    $workspace = Workspace::query()->findOrFail($user->current_workspace_id);
    $workspace->forceFill([
        'trial_ends_at' => null,
        'subscription_status' => 'trialing',
        'stripe_id' => 'cus_test',
    ])->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('skips the subscription gate when stripe is not configured', function (): void {
    $user = User::factory()->onboarded()->create();
    $workspace = Workspace::query()->findOrFail($user->current_workspace_id);
    $workspace->forceFill([
        'trial_ends_at' => now()->subDay(),
        'subscription_status' => null,
    ])->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('skips the subscription gate when billing is not enforced', function (): void {
    enableBilling(['billing_enforced' => false]);

    $user = User::factory()->onboarded()->create();
    $workspace = Workspace::query()->findOrFail($user->current_workspace_id);
    $workspace->forceFill([
        'trial_ends_at' => now()->subDay(),
        'subscription_status' => null,
    ])->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('stores trial days and require-card settings from the payment tab', function (): void {
    $admin = User::factory()->create(['is_platform_admin' => true]);

    $this->actingAs($admin)
        ->post('/platform/settings/payment', [
            'stripe_enabled' => true,
            'stripe_key' => 'pk_test_abc',
            'stripe_secret' => 'sk_test_abc',
            'billing_enforced' => true,
            'trial_days' => 0,
            'trial_requires_card' => false,
        ])
        ->assertRedirect(route('platform.dashboard', ['tab' => 'settings', 'section' => 'payment']));

    $settings = PlatformSetting::instance()->fresh();

    expect($settings->trial_days)->toBe(0)
        ->and($settings->trial_requires_card)->toBeFalse()
        ->and($settings->billing_enforced)->toBeTrue();
});

it('starts a local trial at onboarding when a card is not required', function (): void {
    enableBilling(['trial_requires_card' => false, 'trial_days' => 7]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.store'), [
            'name' => 'Ada Lovelace',
            'company_name' => 'Analytical Engines',
            'workspace_name' => 'Ada Docs',
            'subdomain' => 'ada-trial',
        ])
        ->assertRedirect(route('dashboard'));

    $workspace = Workspace::query()->findOrFail($user->fresh()->current_workspace_id);

    expect($workspace->trial_ends_at)->not->toBeNull()
        ->and($workspace->trial_ends_at?->isFuture())->toBeTrue();

    $this->actingAs($user->fresh())
        ->get(route('dashboard'))
        ->assertOk();
});

it('does not start a local trial when a card is required', function (): void {
    enableBilling(['trial_requires_card' => true]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.store'), [
            'name' => 'Ada Lovelace',
            'company_name' => 'Analytical Engines',
            'workspace_name' => 'Ada Docs',
            'subdomain' => 'ada-card',
        ])
        ->assertRedirect(route('dashboard'));

    $workspace = Workspace::query()->findOrFail($user->fresh()->current_workspace_id);

    expect($workspace->trial_ends_at)->toBeNull();

    $this->actingAs($user->fresh())
        ->get(route('dashboard'))
        ->assertRedirect(route('billing.show'));
});

it('lets platform admins open the platform dashboard when the workspace trial has expired', function (): void {
    enableBilling();

    $admin = User::factory()->onboarded()->create(['is_platform_admin' => true]);
    $workspace = Workspace::query()->findOrFail($admin->current_workspace_id);
    $workspace->forceFill([
        'trial_ends_at' => now()->subDay(),
        'subscription_status' => null,
    ])->save();

    $this->actingAs($admin)
        ->get(route('platform.dashboard'))
        ->assertOk();
});

it('sends stripe checkout with trial days and always collects a card when required', function (): void {
    enableBilling(['trial_requires_card' => true, 'trial_days' => 14]);
    config(['services.stripe.secret' => 'sk_test_456']);

    $user = User::factory()->onboarded()->create();
    $workspace = Workspace::query()->findOrFail($user->current_workspace_id);
    $workspace->forceFill(['trial_ends_at' => null, 'subscription_status' => null])->save();

    $pro = Plan::query()->where('slug', 'pro')->firstOrFail();
    $pro->forceFill(['stripe_price_id' => 'price_pro_test'])->save();

    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions' => Http::response([
            'url' => 'https://checkout.stripe.com/c/test_session',
        ], 200),
    ]);

    $this->actingAs($user)
        ->post(route('billing.checkout'), ['plan_id' => $pro->id])
        ->assertRedirect('https://checkout.stripe.com/c/test_session');

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), 'checkout/sessions')) {
            return false;
        }

        $body = $request->data();

        return ($body['payment_method_collection'] ?? null) === 'always'
            && (int) ($body['subscription_data']['trial_period_days'] ?? 0) === 14
            && ($body['mode'] ?? null) === 'subscription';
    });
});

it('uses if_required card collection when starting a checkout trial without a required card', function (): void {
    enableBilling(['trial_requires_card' => false, 'trial_days' => 7]);
    config(['services.stripe.secret' => 'sk_test_456']);

    $user = User::factory()->create();
    $this->actingAs($user)->post(route('onboarding.store'), [
        'name' => 'No Card',
        'company_name' => 'Trial Co',
        'workspace_name' => 'Trial Co',
        'subdomain' => 'no-card-trial',
    ]);

    $user->refresh();
    $workspace = Workspace::query()->findOrFail($user->current_workspace_id);
    $workspace->forceFill(['trial_ends_at' => null])->save();

    $pro = Plan::query()->where('slug', 'pro')->firstOrFail();
    $pro->forceFill(['stripe_price_id' => 'price_pro_test'])->save();

    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions' => Http::response([
            'url' => 'https://checkout.stripe.com/c/test_session',
        ], 200),
    ]);

    $this->actingAs($user)
        ->post(route('billing.checkout'), ['plan_id' => $pro->id])
        ->assertRedirect('https://checkout.stripe.com/c/test_session');

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return ($body['payment_method_collection'] ?? null) === 'if_required'
            && (int) ($body['subscription_data']['trial_period_days'] ?? 0) === 7;
    });
});

it('treats one month as a calendar month for trial end dates', function (): void {
    enableBilling(['trial_days' => 0, 'trial_requires_card' => false]);

    $from = now()->startOfDay();
    $ends = app(BillingAccess::class)->trialEndsAt($from);

    expect($ends->equalTo($from->copy()->addMonth()))->toBeTrue();
});
