<?php

namespace App\Support;

class PlanBlueprint
{
    /**
     * @var list<string>
     */
    public const LIMIT_KEYS = ['projects', 'members', 'custom_domains'];

    /**
     * @var list<string>
     */
    public const FEATURE_KEYS = [
        'custom_domain',
        'advanced_branding',
        'analytics',
        'versioning',
        'localization',
        'audit_log',
        'sso',
        'custom_css',
        'remove_branding',
        'ai_generation',
    ];

    /**
     * @param  array<string, mixed>  $limits
     * @return array<string, int|null>
     */
    public static function normalizeLimits(array $limits): array
    {
        $normalized = [];

        foreach (self::LIMIT_KEYS as $key) {
            $value = $limits[$key] ?? null;

            if ($value === null || $value === '') {
                $normalized[$key] = null;

                continue;
            }

            $normalized[$key] = max(0, (int) $value);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $features
     * @return array<string, bool>
     */
    public static function normalizeFeatures(array $features): array
    {
        $normalized = [];

        foreach (self::FEATURE_KEYS as $key) {
            $normalized[$key] = filter_var($features[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        return $normalized;
    }
}
