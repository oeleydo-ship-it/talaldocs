<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Support\PlatformAudit;
use App\Support\PlatformConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Throwable;

class PlatformSettingsSectionsController extends Controller
{
    public function __construct(private PlatformAudit $audit) {}

    public function updateGeneral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:80'],
            'app_domain' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/i'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,ico,svg', 'max:512'],
        ]);

        $settings = PlatformSetting::instance();

        foreach (['logo' => 'logo_path', 'favicon' => 'favicon_path'] as $file => $column) {
            if ($request->hasFile($file)) {
                if (filled($settings->{$column})) {
                    Storage::disk('public')->delete($settings->{$column});
                }

                $settings->{$column} = $request->file($file)->store('platform/branding', 'public');
            }
        }

        $appDomain = filled($data['app_domain'] ?? null)
            ? strtolower(trim($data['app_domain']))
            : null;

        $settings->fill([
            'app_name' => $data['app_name'],
            'app_domain' => $appDomain,
            'support_email' => $data['support_email'] ?? null,
            'tagline' => $data['tagline'] ?? null,
        ])->save();

        PlatformConfig::apply();

        $this->audit->record($request->user(), 'platform.general_settings_updated', null, [
            'app_name' => $settings->app_name,
            'app_domain' => $settings->app_domain,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('General settings saved.'),
        ]);

        return redirect()->route('platform.dashboard', ['tab' => 'settings', 'section' => 'general']);
    }

    public function updateSmtp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_mailer' => ['required', Rule::in(['smtp', 'log'])],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:500'],
            'mail_encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
        ]);

        $settings = PlatformSetting::instance();

        $settings->fill([
            'mail_mailer' => $data['mail_mailer'],
            'mail_host' => filled($data['mail_host'] ?? null) ? $data['mail_host'] : null,
            'mail_port' => $data['mail_port'] ?? null,
            'mail_username' => $data['mail_username'] ?? null,
            'mail_encryption' => filled($data['mail_encryption'] ?? null) ? $data['mail_encryption'] : null,
            'mail_from_address' => $data['mail_from_address'] ?? null,
            'mail_from_name' => $data['mail_from_name'] ?? null,
        ]);

        if (filled($data['mail_password'] ?? null)) {
            $settings->mail_password = $data['mail_password'];
        }

        $settings->save();
        PlatformConfig::apply();

        $this->audit->record($request->user(), 'platform.smtp_settings_updated', null, [
            'mail_host' => $settings->mail_host,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('SMTP settings saved.'),
        ]);

        return redirect()->route('platform.dashboard', ['tab' => 'settings', 'section' => 'smtp']);
    }

    public function testSmtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'email', 'max:255'],
        ]);

        PlatformConfig::apply();

        try {
            PlatformConfig::sendTestMail($data['to']);
        } catch (Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Test email sent.',
        ]);
    }

    public function updatePayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'stripe_enabled' => ['required', 'boolean'],
            'stripe_key' => ['nullable', 'string', 'max:255'],
            'stripe_secret' => ['nullable', 'string', 'max:500'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:500'],
            'billing_enforced' => ['required', 'boolean'],
            'trial_days' => ['required', 'integer', Rule::in([0, 7, 14, 30])],
            'trial_requires_card' => ['required', 'boolean'],
        ]);

        $settings = PlatformSetting::instance();

        $settings->stripe_enabled = $data['stripe_enabled'];
        $settings->billing_enforced = $data['billing_enforced'];
        $settings->trial_days = $data['trial_days'];
        $settings->trial_requires_card = $data['trial_requires_card'];
        $settings->stripe_key = filled($data['stripe_key'] ?? null) ? $data['stripe_key'] : null;

        if (filled($data['stripe_secret'] ?? null)) {
            $settings->stripe_secret = $data['stripe_secret'];
        }

        if (filled($data['stripe_webhook_secret'] ?? null)) {
            $settings->stripe_webhook_secret = $data['stripe_webhook_secret'];
        }

        $settings->save();
        PlatformConfig::apply();

        $this->audit->record($request->user(), 'platform.payment_settings_updated', null, [
            'stripe_enabled' => $settings->stripe_enabled,
            'billing_enforced' => $settings->billing_enforced,
            'trial_days' => $settings->trial_days,
            'trial_requires_card' => $settings->trial_requires_card,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Payment settings saved.'),
        ]);

        return redirect()->route('platform.dashboard', ['tab' => 'settings', 'section' => 'payment']);
    }
}
