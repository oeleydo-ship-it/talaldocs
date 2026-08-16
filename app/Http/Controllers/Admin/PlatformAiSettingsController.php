<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Support\PlatformAiConfig;
use App\Support\PlatformAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Throwable;

class PlatformAiSettingsController extends Controller
{
    public function __construct(private PlatformAudit $audit) {}

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ai_enabled' => ['required', 'boolean'],
            'ai_provider' => ['required', Rule::in(array_keys(PlatformAiConfig::PROVIDERS))],
            'ai_api_key' => ['nullable', 'string', 'max:500'],
            'ai_base_url' => ['nullable', 'string', 'max:500'],
            'ai_model' => ['required', 'string', 'max:100'],
        ]);

        $settings = PlatformSetting::instance();

        $settings->ai_enabled = $data['ai_enabled'];
        $settings->ai_provider = $data['ai_provider'];
        $settings->ai_model = $data['ai_model'];
        $settings->ai_base_url = filled($data['ai_base_url'] ?? null)
            ? rtrim($data['ai_base_url'], '/')
            : null;

        if (filled($data['ai_api_key'] ?? null)) {
            $settings->ai_api_key = $data['ai_api_key'];
        }

        $settings->save();
        PlatformAiConfig::apply();

        $this->audit->record($request->user(), 'platform.ai_settings_updated', null, [
            'ai_enabled' => $settings->ai_enabled,
            'ai_provider' => $settings->ai_provider,
            'ai_model' => $settings->ai_model,
            'api_key_changed' => filled($data['ai_api_key'] ?? null),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('AI settings saved.'),
        ]);

        return redirect()->route('platform.dashboard', ['tab' => 'settings', 'section' => 'ai']);
    }

    public function test(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ai_provider' => ['required', Rule::in(array_keys(PlatformAiConfig::PROVIDERS))],
            'ai_api_key' => ['nullable', 'string', 'max:500'],
            'ai_base_url' => ['nullable', 'string', 'max:500'],
            'ai_model' => ['required', 'string', 'max:100'],
        ]);

        $settings = PlatformSetting::instance();
        $apiKey = filled($data['ai_api_key'] ?? null)
            ? $data['ai_api_key']
            : (string) ($settings->ai_api_key ?? config('ai.api_key'));

        try {
            PlatformAiConfig::testConnection(
                $data['ai_provider'],
                $data['ai_model'],
                $apiKey,
                $data['ai_base_url'] ?? null,
            );
        } catch (Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Connection successful.',
        ]);
    }
}
