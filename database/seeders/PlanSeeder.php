<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'price_cents' => 0,
                'is_active' => true,
                'limits' => [
                    'projects' => 1,
                    'members' => 3,
                    'custom_domains' => 0,
                ],
                'features' => [
                    'custom_domain' => false,
                    'advanced_branding' => false,
                    'analytics' => false,
                    'versioning' => false,
                    'localization' => false,
                    'audit_log' => false,
                    'sso' => false,
                    'custom_css' => false,
                    'remove_branding' => false,
                    'ai_generation' => false,
                ],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price_cents' => 2900,
                'stripe_price_id' => env('STRIPE_PRICE_PRO'),
                'is_active' => true,
                'limits' => [
                    'projects' => 10,
                    'members' => 15,
                    'custom_domains' => 1,
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
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'price_cents' => 9900,
                'stripe_price_id' => env('STRIPE_PRICE_BUSINESS'),
                'is_active' => true,
                'limits' => [
                    'projects' => 50,
                    'members' => 50,
                    'custom_domains' => 10,
                ],
                'features' => [
                    'custom_domain' => true,
                    'advanced_branding' => true,
                    'analytics' => true,
                    'versioning' => true,
                    'localization' => true,
                    'audit_log' => true,
                    'sso' => false,
                    'custom_css' => true,
                    'remove_branding' => true,
                    'ai_generation' => true,
                ],
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price_cents' => 0,
                'is_active' => true,
                'limits' => [
                    'projects' => null,
                    'members' => null,
                    'custom_domains' => null,
                ],
                'features' => [
                    'custom_domain' => true,
                    'advanced_branding' => true,
                    'analytics' => true,
                    'versioning' => true,
                    'localization' => true,
                    'audit_log' => true,
                    'sso' => true,
                    'custom_css' => true,
                    'remove_branding' => true,
                    'ai_generation' => true,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                $plan,
            );
        }
    }
}
