<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Support\PlatformAudit;
use App\Support\PlatformCloudflareConfig;
use App\Services\CloudflareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlatformCloudflareSettingsController extends Controller
{
    public function __construct(
        private PlatformAudit $audit,
        private CloudflareService $cloudflare,
    ) {}

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cloudflare_enabled' => ['required', 'boolean'],
            'cloudflare_zone_id' => ['nullable', 'string', 'max:64'],
            'cloudflare_account_id' => ['nullable', 'string', 'max:64'],
            'cloudflare_fallback_origin' => ['nullable', 'string', 'max:180'],
            'cloudflare_auto_subdomains' => ['required', 'boolean'],
            'cloudflare_api_token' => ['nullable', 'string', 'max:500'],
        ]);

        $settings = PlatformSetting::instance();

        $settings->fill([
            'cloudflare_enabled' => $data['cloudflare_enabled'],
            'cloudflare_zone_id' => filled($data['cloudflare_zone_id'] ?? null) ? $data['cloudflare_zone_id'] : null,
            'cloudflare_account_id' => filled($data['cloudflare_account_id'] ?? null) ? $data['cloudflare_account_id'] : null,
            'cloudflare_fallback_origin' => filled($data['cloudflare_fallback_origin'] ?? null)
                ? strtolower(rtrim($data['cloudflare_fallback_origin'], '.'))
                : null,
            'cloudflare_auto_subdomains' => $data['cloudflare_auto_subdomains'],
        ]);

        if (filled($data['cloudflare_api_token'] ?? null)) {
            $settings->cloudflare_api_token = $data['cloudflare_api_token'];
        }

        $settings->save();

        $this->audit->record($request->user(), 'platform.cloudflare_settings_updated', null, [
            'cloudflare_enabled' => $settings->cloudflare_enabled,
            'cloudflare_zone_id' => $settings->cloudflare_zone_id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Cloudflare settings saved.'),
        ]);

        return redirect()->route('platform.dashboard', ['tab' => 'settings', 'section' => 'dns']);
    }

    public function test(Request $request): JsonResponse
    {
        $result = $this->cloudflare->testConnection();

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
