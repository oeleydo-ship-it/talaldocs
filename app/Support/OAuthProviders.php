<?php

namespace App\Support;

class OAuthProviders
{
    /**
     * @return list<string>
     */
    public static function enabled(): array
    {
        $providers = [];

        foreach (['github', 'google'] as $provider) {
            if (filled(config("services.{$provider}.client_id")) && filled(config("services.{$provider}.client_secret"))) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    public static function isEnabled(string $provider): bool
    {
        return in_array($provider, self::enabled(), true);
    }
}
