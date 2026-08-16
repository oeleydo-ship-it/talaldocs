<?php

namespace App\Support;

use App\Models\Plan;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

class StripeCheckout
{
    public function isConfigured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    public function canCheckout(Plan $plan): bool
    {
        return $this->isConfigured() && filled($plan->stripe_price_id);
    }

    /**
     * @return array{url: string}|null
     */
    public function createSession(Workspace $workspace, Plan $plan, string $successUrl, string $cancelUrl): ?array
    {
        if (! $this->canCheckout($plan)) {
            return null;
        }

        $payload = [
            'mode' => 'subscription',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $workspace->id,
            'line_items' => [[
                'price' => $plan->stripe_price_id,
                'quantity' => 1,
            ]],
            'metadata' => [
                'workspace_id' => (string) $workspace->id,
                'plan_id' => (string) $plan->id,
            ],
        ];

        if (filled($workspace->stripe_id)) {
            $payload['customer'] = $workspace->stripe_id;
        }

        $response = Http::withToken((string) config('services.stripe.secret'))
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', $payload);

        if (! $response->successful()) {
            return null;
        }

        $url = $response->json('url');

        return is_string($url) ? ['url' => $url] : null;
    }

    public function portalUrl(Workspace $workspace, string $returnUrl): ?string
    {
        if (! $this->isConfigured() || ! filled($workspace->stripe_id)) {
            return null;
        }

        $response = Http::withToken((string) config('services.stripe.secret'))
            ->asForm()
            ->post('https://api.stripe.com/v1/billing_portal/sessions', [
                'customer' => $workspace->stripe_id,
                'return_url' => $returnUrl,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $url = $response->json('url');

        return is_string($url) ? $url : null;
    }
}
