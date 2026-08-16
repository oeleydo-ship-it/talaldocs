<?php

namespace App\Support;

use App\Enums\AiProvider;
use App\Models\PlatformSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class PlatformAiConfig
{
    /**
     * @var array<string, array{
     *     label: string,
     *     base_url: string,
     *     base_urls?: array<string, string>,
     *     models: list<string>,
     *     default_model: string
     * }>
     */
    public const PROVIDERS = [
        'openai' => [
            'label' => 'OpenAI',
            'base_url' => 'https://api.openai.com/v1',
            'models' => ['gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo', 'gpt-3.5-turbo'],
            'default_model' => 'gpt-4o-mini',
        ],
        'kimi' => [
            'label' => 'Kimi (Moonshot)',
            'base_url' => 'https://api.moonshot.ai/v1',
            'base_urls' => [
                'https://api.moonshot.ai/v1' => 'International (platform.kimi.ai)',
                'https://api.moonshot.cn/v1' => 'China (moonshot.cn)',
            ],
            'models' => [
                'kimi-k3',
                'kimi-k2.7-code',
                'kimi-k2.7-code-highspeed',
                'kimi-k2.6',
                'kimi-k2.5',
            ],
            'default_model' => 'kimi-k3',
        ],
        'custom' => [
            'label' => 'Custom',
            'base_url' => '',
            'models' => [],
            'default_model' => '',
        ],
    ];

    public static function apply(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        $settings = PlatformSetting::instance();

        config([
            'ai.enabled' => $settings->ai_enabled,
            'ai.provider' => $settings->ai_provider,
        ]);

        if (filled($settings->ai_api_key)) {
            config(['ai.api_key' => $settings->ai_api_key]);
        }

        if (filled($settings->ai_model)) {
            config(['ai.model' => $settings->ai_model]);
        }

        $baseUrl = self::resolveBaseUrl(
            $settings->ai_provider,
            $settings->ai_base_url,
        );

        if ($baseUrl !== '') {
            config(['ai.base_url' => $baseUrl]);
        }
    }

    public static function isConfigured(): bool
    {
        if (! (bool) config('ai.enabled', true)) {
            return false;
        }

        return filled(config('ai.api_key'));
    }

    public static function resolveBaseUrl(string $provider, ?string $override = null): string
    {
        if (filled($override)) {
            return rtrim($override, '/');
        }

        $presetUrl = self::PROVIDERS[$provider]['base_url'] ?? '';

        if ($presetUrl !== '') {
            return rtrim($presetUrl, '/');
        }

        return AiProvider::tryFrom($provider)?->defaultBaseUrl() ?? '';
    }

    public static function defaultModelForProvider(string $provider): string
    {
        return (string) (self::PROVIDERS[$provider]['default_model'] ?? 'gpt-4o-mini');
    }

    public static function maskedApiKey(?string $apiKey): ?string
    {
        if (! filled($apiKey)) {
            return null;
        }

        $apiKey = (string) $apiKey;

        if (strlen($apiKey) <= 4) {
            return '••••';
        }

        return '••••'.substr($apiKey, -4);
    }

    /**
     * @return array{
     *     ai_enabled: bool,
     *     ai_provider: string,
     *     ai_base_url: string,
     *     ai_model: string,
     *     ai_api_key_masked: string|null,
     *     ai_api_key_set: bool,
     *     providers: array<string, array{
     *         label: string,
     *         base_url: string,
     *         base_urls?: array<string, string>,
     *         models: list<string>,
     *         default_model: string
     *     }>
     * }
     */
    public static function toPublicArray(): array
    {
        if (! Schema::hasTable('platform_settings')) {
            return self::publicArrayFromConfig();
        }

        $settings = PlatformSetting::instance();
        $apiKey = filled($settings->ai_api_key)
            ? $settings->ai_api_key
            : (string) config('ai.api_key');

        return [
            'ai_enabled' => $settings->ai_enabled,
            'ai_provider' => $settings->ai_provider,
            'ai_base_url' => self::resolveBaseUrl(
                $settings->ai_provider,
                $settings->ai_base_url,
            ),
            'ai_model' => $settings->ai_model,
            'ai_api_key_masked' => self::maskedApiKey($apiKey),
            'ai_api_key_set' => filled($apiKey),
            'providers' => self::PROVIDERS,
        ];
    }

    /**
     * @return array{
     *     ai_enabled: bool,
     *     ai_provider: string,
     *     ai_base_url: string,
     *     ai_model: string,
     *     ai_api_key_masked: string|null,
     *     ai_api_key_set: bool,
     *     providers: array<string, array{
     *         label: string,
     *         base_url: string,
     *         base_urls?: array<string, string>,
     *         models: list<string>,
     *         default_model: string
     *     }>
     * }
     */
    private static function publicArrayFromConfig(): array
    {
        $provider = (string) config('ai.provider', 'openai');
        $apiKey = (string) config('ai.api_key');

        return [
            'ai_enabled' => (bool) config('ai.enabled', true),
            'ai_provider' => $provider,
            'ai_base_url' => (string) config('ai.base_url'),
            'ai_model' => (string) config('ai.model'),
            'ai_api_key_masked' => self::maskedApiKey($apiKey),
            'ai_api_key_set' => filled($apiKey),
            'providers' => self::PROVIDERS,
        ];
    }

    public static function requiresFixedTemperature(?string $provider = null, ?string $model = null): bool
    {
        $provider ??= (string) config('ai.provider', 'openai');
        $model ??= (string) config('ai.model', '');

        if ($provider === 'kimi') {
            return true;
        }

        return str_starts_with(strtolower($model), 'kimi-k');
    }

    public static function temperatureForProvider(
        ?string $provider = null,
        ?string $model = null,
        float $default = 0.7,
    ): float {
        if (self::requiresFixedTemperature($provider, $model)) {
            return 1.0;
        }

        return $default;
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function chatCompletionParams(array $messages, array $options = []): array
    {
        $model = (string) ($options['model'] ?? config('ai.model'));
        $provider = (string) ($options['provider'] ?? config('ai.provider', 'openai'));
        $temperature = (float) ($options['temperature'] ?? 0.7);

        unset($options['model'], $options['provider'], $options['temperature']);

        return array_merge([
            'model' => $model,
            'messages' => $messages,
            'temperature' => self::temperatureForProvider($provider, $model, $temperature),
        ], $options);
    }

    public static function testConnection(
        string $provider,
        string $model,
        string $apiKey,
        ?string $baseUrl = null,
    ): void {
        if ($apiKey === '') {
            throw new RuntimeException('API key is required to test the connection.');
        }

        $resolvedBaseUrl = self::resolveBaseUrl($provider, $baseUrl);

        if ($resolvedBaseUrl === '') {
            throw new RuntimeException('Base URL is required for this provider.');
        }

        if ($model === '') {
            throw new RuntimeException('Model is required to test the connection.');
        }

        try {
            $response = Http::timeout(20)
                ->withToken($apiKey)
                ->acceptJson()
                ->post($resolvedBaseUrl.'/chat/completions', self::chatCompletionParams(
                    messages: [
                        ['role' => 'user', 'content' => 'Reply with exactly: ok'],
                    ],
                    options: [
                        'model' => $model,
                        'provider' => $provider,
                        'max_tokens' => 5,
                    ],
                ));
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'Could not reach AI provider at '.$resolvedBaseUrl.': '.$exception->getMessage(),
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(self::formatApiError($response));
        }
    }

    public static function formatApiError(Response $response): string
    {
        $status = $response->status();
        $statusLabel = match ($status) {
            401 => '401 Unauthorized',
            403 => '403 Forbidden',
            404 => '404 Not Found',
            429 => '429 Too Many Requests',
            default => 'HTTP '.$status,
        };

        $message = data_get($response->json(), 'error.message');
        $type = data_get($response->json(), 'error.type');

        if (is_string($message) && $message !== '') {
            if (is_string($type) && $type !== '') {
                return "{$statusLabel} ({$type}): {$message}";
            }

            return "{$statusLabel}: {$message}";
        }

        $body = trim($response->body());

        if ($body !== '' && ! str_starts_with($body, '<')) {
            return "{$statusLabel}: ".Str::limit($body, 200);
        }

        return "{$statusLabel}: AI provider request failed.";
    }

    public static function providerEnum(string $provider): AiProvider
    {
        return AiProvider::from($provider);
    }
}
