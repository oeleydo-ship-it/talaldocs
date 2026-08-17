<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PDOException;
use Throwable;

class PlatformConfig
{
    /**
     * True when platform_settings exists and the DB connection works.
     * Safe during composer package:discover / missing .env / unavailable DB.
     */
    public static function tableAvailable(): bool
    {
        try {
            return Schema::hasTable('platform_settings');
        } catch (QueryException|PDOException) {
            return false;
        } catch (Throwable $e) {
            // Missing SQLite file and similar connection failures surface as RuntimeException.
            if (self::isDatabaseUnavailable($e)) {
                return false;
            }

            throw $e;
        }
    }

    public static function isDatabaseUnavailable(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'database file')
            || str_contains($message, 'could not find driver')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'sqlstate[')
            || str_contains($message, 'no such file')
            || str_contains($message, 'access denied')
            || $e instanceof QueryException
            || $e instanceof PDOException;
    }

    public static function apply(): void
    {
        try {
            if (! self::tableAvailable()) {
                return;
            }

            $settings = PlatformSetting::instance();

            if (filled($settings->app_name)) {
                config(['app.name' => $settings->app_name]);
            }

            if (filled($settings->app_domain)) {
                config(['anytdocs.domain' => strtolower(trim($settings->app_domain))]);
            }

            $appName = (string) config('app.name', 'Docs');
            $appUrl = rtrim((string) config('app.url'), '/');
            $botSlug = preg_replace('/[^a-z0-9]+/i', '', $appName) ?: 'Docs';
            config([
                'ai.user_agent' => $botSlug.'Bot/1.0 (+'.$appUrl.')',
            ]);

            if (filled($settings->mail_host)) {
                config([
                    'mail.default' => $settings->mail_mailer ?: 'smtp',
                    'mail.mailers.smtp.host' => $settings->mail_host,
                    'mail.mailers.smtp.port' => $settings->mail_port ?? 587,
                    'mail.mailers.smtp.username' => $settings->mail_username,
                    'mail.mailers.smtp.password' => $settings->mail_password,
                    'mail.mailers.smtp.encryption' => $settings->mail_encryption ?: null,
                ]);
            }

            if (filled($settings->mail_from_address)) {
                config([
                    'mail.from.address' => $settings->mail_from_address,
                    'mail.from.name' => $settings->mail_from_name ?: $settings->app_name,
                ]);
            }

            if ($settings->stripe_enabled) {
                if (filled($settings->stripe_key)) {
                    config(['services.stripe.key' => $settings->stripe_key]);
                }

                if (filled($settings->stripe_secret)) {
                    config(['services.stripe.secret' => $settings->stripe_secret]);
                }

                if (filled($settings->stripe_webhook_secret)) {
                    config(['services.stripe.webhook_secret' => $settings->stripe_webhook_secret]);
                }
            }
        } catch (Throwable $e) {
            if (self::isDatabaseUnavailable($e)) {
                return;
            }

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function generalSettings(): array
    {
        if (! self::tableAvailable()) {
            return [
                'app_name' => (string) config('app.name', 'Docs'),
                'app_domain' => (string) config('anytdocs.domain'),
                'support_email' => null,
                'tagline' => null,
                'logo_url' => null,
                'favicon_url' => null,
                'hide_app_name_next_to_logo' => false,
            ];
        }

        $settings = PlatformSetting::instance();

        return [
            'app_name' => $settings->app_name ?: (string) config('app.name', 'Docs'),
            'app_domain' => filled($settings->app_domain)
                ? (string) $settings->app_domain
                : (string) config('anytdocs.domain'),
            'support_email' => $settings->support_email,
            'tagline' => $settings->tagline,
            'logo_url' => $settings->assetUrl($settings->logo_path),
            'favicon_url' => $settings->assetUrl($settings->favicon_path),
            'hide_app_name_next_to_logo' => (bool) $settings->hide_app_name_next_to_logo,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function smtpSettings(): array
    {
        if (! self::tableAvailable()) {
            return [
                'mail_mailer' => 'smtp',
                'mail_host' => null,
                'mail_port' => null,
                'mail_username' => null,
                'mail_encryption' => null,
                'mail_from_address' => null,
                'mail_from_name' => null,
                'mail_password_set' => false,
                'mail_password_masked' => null,
                'configured' => false,
            ];
        }

        $settings = PlatformSetting::instance();

        return [
            'mail_mailer' => $settings->mail_mailer,
            'mail_host' => $settings->mail_host,
            'mail_port' => $settings->mail_port,
            'mail_username' => $settings->mail_username,
            'mail_encryption' => $settings->mail_encryption,
            'mail_from_address' => $settings->mail_from_address,
            'mail_from_name' => $settings->mail_from_name,
            'mail_password_set' => filled($settings->mail_password),
            'mail_password_masked' => PlatformSetting::maskedSecret($settings->mail_password),
            'configured' => filled($settings->mail_host),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function paymentSettings(): array
    {
        if (! self::tableAvailable()) {
            return [
                'stripe_enabled' => false,
                'stripe_key' => null,
                'stripe_secret_set' => false,
                'stripe_secret_masked' => null,
                'stripe_webhook_secret_set' => false,
                'stripe_webhook_secret_masked' => null,
                'billing_enforced' => false,
                'trial_days' => 14,
                'trial_requires_card' => true,
                'configured' => false,
            ];
        }

        $settings = PlatformSetting::instance();

        return [
            'stripe_enabled' => $settings->stripe_enabled,
            'stripe_key' => $settings->stripe_key,
            'stripe_secret_set' => filled($settings->stripe_secret),
            'stripe_secret_masked' => PlatformSetting::maskedSecret($settings->stripe_secret),
            'stripe_webhook_secret_set' => filled($settings->stripe_webhook_secret),
            'stripe_webhook_secret_masked' => PlatformSetting::maskedSecret($settings->stripe_webhook_secret),
            'billing_enforced' => (bool) $settings->billing_enforced,
            'trial_days' => in_array((int) $settings->trial_days, [7, 14, 30, 0], true)
                ? (int) $settings->trial_days
                : 14,
            'trial_requires_card' => (bool) $settings->trial_requires_card,
            'configured' => $settings->stripe_enabled
                && filled($settings->stripe_key)
                && filled($settings->stripe_secret),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function brandingForFrontend(): array
    {
        if (! self::tableAvailable()) {
            return [
                'app_name' => (string) config('app.name', 'Docs'),
                'logo_url' => null,
                'favicon_url' => null,
                'tagline' => null,
                'hide_app_name_next_to_logo' => false,
            ];
        }

        $settings = PlatformSetting::instance();

        return [
            'app_name' => $settings->app_name ?: (string) config('app.name', 'Docs'),
            'logo_url' => $settings->assetUrl($settings->logo_path),
            'favicon_url' => $settings->assetUrl($settings->favicon_path),
            'tagline' => $settings->tagline,
            'hide_app_name_next_to_logo' => (bool) $settings->hide_app_name_next_to_logo,
        ];
    }

    public static function appName(): string
    {
        return (string) config('app.name', 'Docs');
    }

    public static function mailConfigured(): bool
    {
        if (! self::tableAvailable()) {
            return false;
        }

        $settings = PlatformSetting::instance();

        if (filled($settings->mail_host)) {
            return true;
        }

        $default = (string) config('mail.default');

        if ($default === 'log') {
            return false;
        }

        if ($default === 'smtp') {
            return filled(config('mail.mailers.smtp.host'));
        }

        return true;
    }

    public static function stripeConfigured(): bool
    {
        if (! self::tableAvailable()) {
            return filled(config('services.stripe.key')) && filled(config('services.stripe.secret'));
        }

        $settings = PlatformSetting::instance();

        if ($settings->stripe_enabled && filled($settings->stripe_key) && filled($settings->stripe_secret)) {
            return true;
        }

        return filled(config('services.stripe.key')) && filled(config('services.stripe.secret'));
    }

    public static function supportEmail(): ?string
    {
        if (! self::tableAvailable()) {
            return null;
        }

        $email = PlatformSetting::instance()->support_email;

        return filled($email) ? $email : null;
    }

    public static function sendTestMail(string $to): void
    {
        Mail::raw('This is a test email from '.config('app.name').'.', function ($message) use ($to): void {
            $message->to($to)->subject('SMTP test · '.config('app.name'));
        });
    }
}
