<?php

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\AiDocumentationGenerator;
use App\Services\DocsAskService;
use App\Support\PlatformAiConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('non platform admins cannot update ai settings', function () {
    $user = User::factory()->onboarded()->create();

    $this->actingAs($user)
        ->post('/platform/settings/ai', [
            'ai_enabled' => true,
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4o-mini',
        ])
        ->assertForbidden();
});

test('platform admin can save ai settings', function () {
    $admin = platformAdmin();

    $this->actingAs($admin)
        ->post('/platform/settings/ai', [
            'ai_enabled' => true,
            'ai_provider' => 'kimi',
            'ai_api_key' => 'kimi-test-key-1234',
            'ai_model' => 'kimi-k3',
            'ai_base_url' => 'https://api.moonshot.ai/v1',
        ])
        ->assertRedirect();

    $settings = PlatformSetting::instance();

    expect($settings->ai_enabled)->toBeTrue()
        ->and($settings->ai_provider)->toBe('kimi')
        ->and($settings->ai_model)->toBe('kimi-k3')
        ->and($settings->ai_api_key)->toBe('kimi-test-key-1234')
        ->and($settings->ai_base_url)->toBe('https://api.moonshot.ai/v1');

    $this->assertDatabaseHas('platform_audit_logs', [
        'admin_id' => $admin->id,
        'action' => 'platform.ai_settings_updated',
    ]);
});

test('platform settings page exposes masked ai key only', function () {
    $admin = platformAdmin();

    PlatformSetting::instance()->forceFill([
        'ai_api_key' => 'sk-platform-secret-key',
        'ai_provider' => 'openai',
        'ai_model' => 'gpt-4o-mini',
    ])->save();

    $this->actingAs($admin)
        ->get('/platform/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/dashboard')
            ->where('aiSettings.ai_api_key_masked', '••••-key')
            ->where('aiSettings.ai_api_key_set', true)
            ->missing('aiSettings.ai_api_key'));
});

test('platform ai config applies database settings over env fallback', function () {
    config([
        'ai.api_key' => 'env-key',
        'ai.model' => 'gpt-4o-mini',
        'ai.base_url' => 'https://api.openai.com/v1',
        'ai.enabled' => true,
    ]);

    PlatformSetting::instance()->forceFill([
        'ai_enabled' => true,
        'ai_provider' => 'kimi',
        'ai_api_key' => 'platform-key',
        'ai_model' => 'kimi-k3',
        'ai_base_url' => null,
    ])->save();

    PlatformAiConfig::apply();

    expect(config('ai.api_key'))->toBe('platform-key')
        ->and(config('ai.model'))->toBe('kimi-k3')
        ->and(config('ai.base_url'))->toBe('https://api.moonshot.ai/v1')
        ->and(AiDocumentationGenerator::isConfigured())->toBeTrue()
        ->and(DocsAskService::isConfigured())->toBeTrue();
});

test('platform ai config applies kimi china base url override', function () {
    PlatformSetting::instance()->forceFill([
        'ai_enabled' => true,
        'ai_provider' => 'kimi',
        'ai_api_key' => 'platform-key',
        'ai_model' => 'kimi-k3',
        'ai_base_url' => 'https://api.moonshot.cn/v1',
    ])->save();

    PlatformAiConfig::apply();

    expect(config('ai.base_url'))->toBe('https://api.moonshot.cn/v1');
});

test('ai features are disabled when platform ai is turned off', function () {
    PlatformSetting::instance()->forceFill([
        'ai_enabled' => false,
        'ai_api_key' => 'platform-key',
        'ai_model' => 'gpt-4o-mini',
    ])->save();

    PlatformAiConfig::apply();

    expect(AiDocumentationGenerator::isConfigured())->toBeFalse()
        ->and(DocsAskService::isConfigured())->toBeFalse();
});

test('platform admin can test ai connection', function () {
    $admin = platformAdmin();

    PlatformSetting::instance()->forceFill([
        'ai_api_key' => 'platform-key',
        'ai_provider' => 'openai',
        'ai_model' => 'gpt-4o-mini',
    ])->save();

    Http::fake([
        'https://api.openai.com/v1/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'ok']],
            ],
        ]),
    ]);

    $this->actingAs($admin)
        ->postJson('/platform/settings/ai/test', [
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4o-mini',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);
});

test('platform admin can test kimi connection against international endpoint', function () {
    $admin = platformAdmin();

    Http::fake([
        'https://api.moonshot.ai/v1/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'ok']],
            ],
        ]),
    ]);

    $this->actingAs($admin)
        ->postJson('/platform/settings/ai/test', [
            'ai_provider' => 'kimi',
            'ai_api_key' => 'kimi-test-key',
            'ai_model' => 'kimi-k3',
            'ai_base_url' => 'https://api.moonshot.ai/v1',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.moonshot.ai/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer kimi-test-key')
        && $request['model'] === 'kimi-k3'
        && (float) $request['temperature'] === 1.0);
});

test('kimi connection test returns api error details', function () {
    $admin = platformAdmin();

    Http::fake([
        'https://api.moonshot.ai/v1/*' => Http::response([
            'error' => [
                'type' => 'invalid_authentication_error',
                'message' => 'Invalid Authentication',
            ],
        ], 401),
    ]);

    $this->actingAs($admin)
        ->postJson('/platform/settings/ai/test', [
            'ai_provider' => 'kimi',
            'ai_api_key' => 'bad-key',
            'ai_model' => 'kimi-k3',
            'ai_base_url' => 'https://api.moonshot.ai/v1',
        ])
        ->assertStatus(422)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('message', '401 Unauthorized (invalid_authentication_error): Invalid Authentication');
});

test('non platform admins cannot test ai connection', function () {
    $user = User::factory()->onboarded()->create();

    $this->actingAs($user)
        ->postJson('/platform/settings/ai/test', [
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4o-mini',
        ])
        ->assertForbidden();
});

test('kimi provider presets include current model slugs', function () {
    $kimi = PlatformAiConfig::PROVIDERS['kimi'];

    expect($kimi['base_url'])->toBe('https://api.moonshot.ai/v1')
        ->and($kimi['default_model'])->toBe('kimi-k3')
        ->and($kimi['models'])->toContain('kimi-k3')
        ->and($kimi['models'])->not->toContain('kimi-k2')
        ->and($kimi['base_urls'])->toHaveKeys([
            'https://api.moonshot.ai/v1',
            'https://api.moonshot.cn/v1',
        ]);
});

test('kimi models require temperature of one', function () {
    expect(PlatformAiConfig::temperatureForProvider('kimi', 'kimi-k3', 0.3))->toBe(1.0)
        ->and(PlatformAiConfig::temperatureForProvider('openai', 'kimi-k3', 0.3))->toBe(1.0)
        ->and(PlatformAiConfig::temperatureForProvider('openai', 'gpt-4o-mini', 0.3))->toBe(0.3);
});

test('chat completion params use provider-aware temperature', function () {
    $openAiParams = PlatformAiConfig::chatCompletionParams(
        messages: [['role' => 'user', 'content' => 'hello']],
        options: [
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'temperature' => 0.3,
        ],
    );

    $kimiParams = PlatformAiConfig::chatCompletionParams(
        messages: [['role' => 'user', 'content' => 'hello']],
        options: [
            'provider' => 'kimi',
            'model' => 'kimi-k3',
            'temperature' => 0.3,
        ],
    );

    expect($openAiParams['temperature'])->toBe(0.3)
        ->and($kimiParams['temperature'])->toBe(1.0);
});
