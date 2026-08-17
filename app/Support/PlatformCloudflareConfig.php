<?php

namespace App\Support;

use App\Models\PlatformSetting;

class PlatformCloudflareConfig
{
    public static function apply(): void
    {
        $fallback = self::configuredFallbackOrigin();

        if ($fallback !== null) {
            config([
                'anytdocs.cname_target' => $fallback,
                'anytdocs.cloudflare.fallback_origin' => $fallback,
            ]);
        }
    }

    public static function isConfigured(): bool
    {
        return filled(self::apiToken()) && filled(self::zoneId()) && self::enabled();
    }

    public static function enabled(): bool
    {
        if (PlatformConfig::tableAvailable()) {
            $settings = PlatformSetting::instance();

            if ($settings->cloudflare_enabled) {
                return true;
            }
        }

        return (bool) config('anytdocs.cloudflare.enabled');
    }

    public static function apiToken(): ?string
    {
        if (PlatformConfig::tableAvailable()) {
            $token = PlatformSetting::instance()->cloudflare_api_token;

            if (filled($token)) {
                return $token;
            }
        }

        $fromConfig = config('anytdocs.cloudflare.api_token');

        return filled($fromConfig) ? (string) $fromConfig : null;
    }

    public static function zoneId(): ?string
    {
        if (PlatformConfig::tableAvailable()) {
            $zoneId = PlatformSetting::instance()->cloudflare_zone_id;

            if (filled($zoneId)) {
                return $zoneId;
            }
        }

        $fromConfig = config('anytdocs.cloudflare.zone_id');

        return filled($fromConfig) ? (string) $fromConfig : null;
    }

    public static function accountId(): ?string
    {
        if (PlatformConfig::tableAvailable()) {
            $accountId = PlatformSetting::instance()->cloudflare_account_id;

            if (filled($accountId)) {
                return $accountId;
            }
        }

        $fromConfig = config('anytdocs.cloudflare.account_id');

        return filled($fromConfig) ? (string) $fromConfig : null;
    }

    /**
     * Hostname customers should CNAME custom domains to (SSL for SaaS fallback origin).
     *
     * Computed at request time: platform setting, then env, then fallback.{app_domain}
     * for real domains. Never the project tenant hostname ({subdomain}.{app_domain}).
     */
    public static function publicCnameTarget(): string
    {
        return self::configuredFallbackOrigin() ?? self::derivedFallbackOrigin();
    }

    /**
     * Stored or env SSL for SaaS fallback origin, even if the API token is not configured locally.
     */
    public static function storedFallbackOrigin(): ?string
    {
        return self::configuredFallbackOrigin();
    }

    public static function configuredFallbackOrigin(): ?string
    {
        if (PlatformConfig::tableAvailable()) {
            $stored = self::normalizeHost(PlatformSetting::instance()->cloudflare_fallback_origin ?? null);

            if ($stored !== null) {
                return $stored;
            }
        }

        return self::normalizeHost(
            config('anytdocs.cname_target') ?: config('anytdocs.cloudflare.fallback_origin')
        );
    }

    /**
     * True when custom hostnames should CNAME to the fallback origin (not the tenant subdomain).
     */
    public static function usesCustomHostnames(): bool
    {
        if (self::configuredFallbackOrigin() !== null) {
            return true;
        }

        if (self::enabled() || self::isConfigured()) {
            return true;
        }

        return filled(config('anytdocs.cloudflare.api_token')) && filled(config('anytdocs.cloudflare.zone_id'));
    }

    public static function fallbackOrigin(): string
    {
        return self::publicCnameTarget();
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
        if (! PlatformConfig::tableAvailable()) {
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
            'app_domain' => self::appDomain(),
            'cname_target' => self::publicCnameTarget(),
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
            'app_domain' => self::appDomain(),
            'cname_target' => self::publicCnameTarget(),
        ];
    }

    private static function derivedFallbackOrigin(): string
    {
        $platform = self::appDomain();

        if ($platform === '' || $platform === 'localhost') {
            return 'fallback.talaldocs.com';
        }

        if (str_starts_with($platform, 'fallback.')) {
            return $platform;
        }

        if (str_contains($platform, '.')) {
            return 'fallback.'.$platform;
        }

        return 'fallback.talaldocs.com';
    }

    private static function appDomain(): string
    {
        return self::normalizeHost((string) config('anytdocs.domain')) ?? 'localhost';
    }

    private static function normalizeHost(mixed $host): ?string
    {
        if (! is_string($host) && ! is_numeric($host)) {
            return null;
        }

        $normalized = strtolower(rtrim(trim((string) $host), '.'));

        return $normalized !== '' ? $normalized : null;
    }
}
