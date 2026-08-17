<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $app_name
 * @property string|null $logo_path
 * @property string|null $favicon_path
 * @property string|null $support_email
 * @property string|null $tagline
 * @property string|null $app_domain
 * @property bool $ai_enabled
 * @property string $ai_provider
 * @property string|null $ai_api_key
 * @property string|null $ai_base_url
 * @property string $ai_model
 * @property string $mail_mailer
 * @property string|null $mail_host
 * @property int|null $mail_port
 * @property string|null $mail_username
 * @property string|null $mail_password
 * @property string|null $mail_encryption
 * @property string|null $mail_from_address
 * @property string|null $mail_from_name
 * @property bool $stripe_enabled
 * @property string|null $stripe_key
 * @property string|null $stripe_secret
 * @property string|null $stripe_webhook_secret
 * @property bool $billing_enforced
 * @property int $trial_days
 * @property bool $trial_requires_card
 * @property bool $cloudflare_enabled
 * @property string|null $cloudflare_api_token
 * @property string|null $cloudflare_zone_id
 * @property string|null $cloudflare_account_id
 * @property string|null $cloudflare_fallback_origin
 * @property bool $cloudflare_auto_subdomains
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Hidden(['ai_api_key', 'mail_password', 'stripe_secret', 'stripe_webhook_secret', 'cloudflare_api_token'])]
class PlatformSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'app_name',
        'logo_path',
        'favicon_path',
        'support_email',
        'tagline',
        'app_domain',
        'ai_enabled',
        'ai_provider',
        'ai_api_key',
        'ai_base_url',
        'ai_model',
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
        'stripe_enabled',
        'stripe_key',
        'stripe_secret',
        'stripe_webhook_secret',
        'billing_enforced',
        'trial_days',
        'trial_requires_card',
        'cloudflare_enabled',
        'cloudflare_api_token',
        'cloudflare_zone_id',
        'cloudflare_account_id',
        'cloudflare_fallback_origin',
        'cloudflare_auto_subdomains',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'app_name' => 'Docs',
        'ai_enabled' => true,
        'ai_provider' => 'openai',
        'ai_model' => 'gpt-4o-mini',
        'mail_mailer' => 'smtp',
        'stripe_enabled' => false,
        'billing_enforced' => true,
        'trial_days' => 14,
        'trial_requires_card' => true,
        'cloudflare_enabled' => false,
        'cloudflare_auto_subdomains' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ai_enabled' => 'boolean',
            'stripe_enabled' => 'boolean',
            'billing_enforced' => 'boolean',
            'trial_days' => 'integer',
            'trial_requires_card' => 'boolean',
            'cloudflare_enabled' => 'boolean',
            'cloudflare_auto_subdomains' => 'boolean',
            'mail_port' => 'integer',
            'ai_api_key' => 'encrypted',
            'mail_password' => 'encrypted',
            'stripe_secret' => 'encrypted',
            'stripe_webhook_secret' => 'encrypted',
            'cloudflare_api_token' => 'encrypted',
        ];
    }

    public static function instance(): self
    {
        /** @var self $settings */
        $settings = static::query()->firstOrCreate(['id' => 1]);

        return $settings;
    }

    public function assetUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public static function maskedSecret(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return '••••'.substr((string) $value, -4);
    }
}
