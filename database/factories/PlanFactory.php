<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Free',
            'slug' => fake()->unique()->slug(2),
            'stripe_price_id' => null,
            'price_cents' => 0,
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
            ],
            'is_active' => true,
        ];
    }
}
