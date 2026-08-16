<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Schema;

class PlatformCloudflareConfig
{
    public static function isConfigured(): bool
    {
        if (! Schema::hasTable('platform_settings')) {
            return false;
        }

        $settings = PlatformSetting::instance();

        return $settings->cloudflare_enabled
            && filled($settings->cloudflare_api_token)
            && filled($settings->cloudflare_zone_id);
    }

    public static function apiToken(): ?string
    {
        if (! Schema::hasTable('platform_settings')) {
            return null;
        }

        return PlatformSetting::instance()->cloudflare_api_token;
    }

    public static function zoneId(): ?string
    {
        if (! Schema::hasTable('platform_settings')) {
            return null;
        }

        return PlatformSetting::instance()->cloudflare_zone_id;
    }

    public static function accountId(): ?string
    {
        if (! Schema::hasTable('platform_settings')) {
            return null;
        }

        return PlatformSetting::instance()->cloudflare_account_id;
    }

    public static function fallbackOrigin(): string
    {
        $platform = strtolower((string) config('anytdocs.domain'));

        if (! Schema::hasTable('platform_settings')) {
            return $platform;
        }

        $settings = PlatformSetting::instance();
        $fallback = trim((string) ($settings->cloudflare_fallback_origin ?? ''));

        if (self::isConfigured() && filled($fallback)) {
            return strtolower(rtrim($fallback, '.'));
        }

        return $platform;
    }

    public static function autoProvisionSubdomains(): bool
    {
        if (! self::isConfigured()) {
            return false;
        }

        return (bool) PlatformSetting::instance()->cloudflare_auto_subdomains;
    }

    /**
     * @return array<string, mixed>
     */
    public static function toPublicArray(): array
    {
        if (! Schema::hasTable('platform_settings')) {
            return self::emptyPublicArray();
        }

        $settings = PlatformSetting::instance();

        return [
            'cloudflare_enabled' => (bool) $settings->cloudflare_enabled,
            'cloudflare_zone_id' => $settings->cloudflare_zone_id,
            'cloudflare_account_id' => $settings->cloudflare_account_id,
            'cloudflare_fallback_origin' => $settings->cloudflare_fallback_origin,
            'cloudflare_auto_subdomains' => (bool) $settings->cloudflare_auto_subdomains,
            'cloudflare_api_token_set' => filled($settings->cloudflare_api_token),
            'cloudflare_api_token_masked' => PlatformSetting::maskedSecret($settings->cloudflare_api_token),
            'configured' => self::isConfigured(),
            'app_domain' => (string) config('anytdocs.domain'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyPublicArray(): array
    {
        return [
            'cloudflare_enabled' => false,
            'cloudflare_zone_id' => null,
            'cloudflare_account_id' => null,
            'cloudflare_fallback_origin' => null,
            'cloudflare_auto_subdomains' => false,
            'cloudflare_api_token_set' => false,
            'cloudflare_api_token_masked' => null,
            'configured' => false,
            'app_domain' => (string) config('anytdocs.domain'),
        ];
    }
}
