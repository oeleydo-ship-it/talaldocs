<?php

use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\PlatformConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('allows platform admin to save general settings with logo upload', function (): void {
    Storage::fake('public');

    $admin = User::factory()->create(['is_platform_admin' => true]);

    $this->actingAs($admin)
        ->post('/platform/settings/general', [
            'app_name' => 'Uplary Docs',
            'app_domain' => 'docs.uplary.com',
            'support_email' => 'support@uplary.com',
            'tagline' => 'Documentation platform',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'favicon' => UploadedFile::fake()->create('favicon.ico', 10, 'image/x-icon'),
        ])
        ->assertRedirect(route('platform.dashboard', ['tab' => 'settings', 'section' => 'general']));

    $settings = PlatformSetting::instance()->fresh();

    expect($settings->app_name)->toBe('Uplary Docs')
        ->and($settings->app_domain)->toBe('docs.uplary.com')
        ->and($settings->support_email)->toBe('support@uplary.com')
        ->and($settings->logo_path)->not->toBeNull()
        ->and($settings->favicon_path)->not->toBeNull();

    expect(config('app.name'))->toBe('Uplary Docs')
        ->and(config('anytdocs.domain'))->toBe('docs.uplary.com');
});

it('hides the app name next to a custom logo when the general setting is enabled', function (): void {
    Storage::fake('public');

    $admin = User::factory()->create(['is_platform_admin' => true]);

    $this->actingAs($admin)
        ->post('/platform/settings/general', [
            'app_name' => 'Talal Docs',
            'hide_app_name_next_to_logo' => true,
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])
        ->assertRedirect(route('platform.dashboard', ['tab' => 'settings', 'section' => 'general']));

    $settings = PlatformSetting::instance()->fresh();

    expect($settings->hide_app_name_next_to_logo)->toBeTrue()
        ->and($settings->logo_path)->not->toBeNull();

    $branding = PlatformConfig::brandingForFrontend();

    expect($branding['hide_app_name_next_to_logo'])->toBeTrue()
        ->and($branding['logo_url'])->not->toBeNull()
        ->and($branding['app_name'])->toBe('Talal Docs');

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('platformBranding.app_name', 'Talal Docs')
            ->where('platformBranding.hide_app_name_next_to_logo', true)
            ->whereNot('platformBranding.logo_url', null)
        );

    $this->actingAs($admin)
        ->get('/platform?tab=settings&section=general')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('generalSettings.hide_app_name_next_to_logo', true)
        );
});

it('keeps the app name next to the logo when the hide setting is off', function (): void {
    Storage::fake('public');

    $admin = User::factory()->create(['is_platform_admin' => true]);
    $settings = PlatformSetting::instance();
    $settings->forceFill([
        'app_name' => 'Talal Docs',
        'logo_path' => 'platform/branding/logo.png',
        'hide_app_name_next_to_logo' => true,
    ])->save();

    $this->actingAs($admin)
        ->post('/platform/settings/general', [
            'app_name' => 'Talal Docs',
            'hide_app_name_next_to_logo' => false,
        ])
        ->assertRedirect(route('platform.dashboard', ['tab' => 'settings', 'section' => 'general']));

    expect(PlatformSetting::instance()->fresh()->hide_app_name_next_to_logo)->toBeFalse();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('platformBranding.hide_app_name_next_to_logo', false)
        );
});

it('allows platform admin to save smtp settings', function (): void {
    $admin = User::factory()->create(['is_platform_admin' => true]);

    $this->actingAs($admin)
        ->post('/platform/settings/smtp', [
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.com',
            'mail_port' => 587,
            'mail_username' => 'mailer',
            'mail_password' => 'secret-pass',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'hello@example.com',
            'mail_from_name' => 'Docs',
        ])
        ->assertRedirect(route('platform.dashboard', ['tab' => 'settings', 'section' => 'smtp']));

    $settings = PlatformSetting::instance()->fresh();

    expect($settings->mail_host)->toBe('smtp.example.com')
        ->and($settings->mail_from_address)->toBe('hello@example.com')
        ->and($settings->mail_password)->toBe('secret-pass');
});

it('allows platform admin to save payment settings', function (): void {
    $admin = User::factory()->create(['is_platform_admin' => true]);

    $this->actingAs($admin)
        ->post('/platform/settings/payment', [
            'stripe_enabled' => true,
            'stripe_key' => 'pk_test_123',
            'stripe_secret' => 'sk_test_456',
            'stripe_webhook_secret' => 'whsec_789',
            'billing_enforced' => true,
            'trial_days' => 14,
            'trial_requires_card' => true,
        ])
        ->assertRedirect(route('platform.dashboard', ['tab' => 'settings', 'section' => 'payment']));

    $settings = PlatformSetting::instance()->fresh();

    expect($settings->stripe_enabled)->toBeTrue()
        ->and($settings->stripe_key)->toBe('pk_test_123')
        ->and($settings->stripe_secret)->toBe('sk_test_456')
        ->and($settings->stripe_webhook_secret)->toBe('whsec_789')
        ->and($settings->billing_enforced)->toBeTrue()
        ->and($settings->trial_days)->toBe(14)
        ->and($settings->trial_requires_card)->toBeTrue();
});

it('renders settings tabs on platform dashboard', function (): void {
    $admin = User::factory()->create(['is_platform_admin' => true]);

    $this->actingAs($admin)
        ->get('/platform?tab=settings&section=smtp')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/dashboard')
            ->where('tab', 'settings')
            ->where('settingsSection', 'smtp')
            ->has('generalSettings.app_name')
            ->has('generalSettings.app_domain')
            ->has('smtpSettings')
            ->has('paymentSettings')
            ->has('publicContent')
            ->has('publicContent.copy.examples')
            ->has('publicContent.demos')
        );
});
