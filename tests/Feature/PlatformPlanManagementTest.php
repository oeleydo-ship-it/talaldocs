<?php

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('platform admin can update plan quotas features and pricing', function (): void {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

    $this->actingAs($admin)
        ->post("/platform/plans/{$plan->id}", [
            'price_cents' => 3900,
            'stripe_price_id' => 'price_test_pro',
            'limits' => [
                'projects' => 12,
                'members' => 20,
                'custom_domains' => 2,
            ],
            'features' => [
                'custom_domain' => true,
                'advanced_branding' => true,
                'analytics' => true,
                'versioning' => false,
                'localization' => false,
                'audit_log' => false,
                'sso' => false,
                'custom_css' => false,
                'remove_branding' => false,
                'ai_generation' => true,
            ],
        ])
        ->assertRedirect();

    $plan->refresh();

    expect($plan->price_cents)->toBe(3900)
        ->and($plan->stripe_price_id)->toBe('price_test_pro')
        ->and($plan->limits['projects'])->toBe(12)
        ->and($plan->features['analytics'])->toBeTrue();
});

test('paid plan save requires stripe price id', function (): void {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

    $this->actingAs($admin)
        ->post("/platform/plans/{$plan->id}", [
            'price_cents' => 2900,
            'stripe_price_id' => null,
            'limits' => $plan->limits,
            'features' => $plan->features,
        ])
        ->assertSessionHasErrors('stripe_price_id');
});

test('billing checkout requires stripe for paid plans when stripe configured', function (): void {
    config(['services.stripe.secret' => 'sk_test_123']);

    $owner = User::factory()->onboarded()->create();
    $pro = Plan::query()->where('slug', 'pro')->firstOrFail();
    $pro->forceFill(['stripe_price_id' => null])->save();

    $this->actingAs($owner)
        ->post(route('billing.checkout'), ['plan_id' => $pro->id])
        ->assertSessionHasErrors('plan_id');
});

test('free plan checkout applies without stripe', function (): void {
    $owner = User::factory()->onboarded()->create();
    $free = Plan::query()->where('slug', 'free')->firstOrFail();
    $workspaceId = $owner->current_workspace_id;

    $this->actingAs($owner)
        ->post(route('billing.checkout'), ['plan_id' => $free->id])
        ->assertRedirect();

    expect(\App\Models\Workspace::query()->find($workspaceId)?->plan_id)->toBe($free->id);
});
