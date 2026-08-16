<?php

namespace App\Support;

use App\Models\Plan;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;

class StripeWebhookHandler
{
    public function __construct(private PlanEnforcer $enforcer) {}

    public function verifySignature(string $payload, ?string $signatureHeader, ?string $secret): bool
    {
        if (! filled($secret) || ! filled($signatureHeader)) {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signatureHeader) as $element) {
            [$key, $value] = array_pad(explode('=', trim($element), 2), 2, null);

            if ($key === 't') {
                $timestamp = $value;
            }

            if ($key === 'v1' && is_string($value)) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, (string) $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function handle(array $event): void
    {
        $type = (string) ($event['type'] ?? '');

        match ($type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event['data']['object'] ?? []),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event['data']['object'] ?? []),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event['data']['object'] ?? []),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function handleCheckoutCompleted(array $session): void
    {
        $workspaceId = (int) ($session['metadata']['workspace_id'] ?? $session['client_reference_id'] ?? 0);
        $planId = (int) ($session['metadata']['plan_id'] ?? 0);
        $customerId = $session['customer'] ?? null;

        $workspace = Workspace::query()->find($workspaceId);

        if ($workspace === null) {
            Log::warning('Stripe checkout completed for unknown workspace', ['workspace_id' => $workspaceId]);

            return;
        }

        if (is_string($customerId) && filled($customerId)) {
            $workspace->forceFill(['stripe_id' => $customerId])->save();
        }

        if ($planId > 0) {
            $this->applyPlan($workspace, $planId);
        }
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function handleSubscriptionUpdated(array $subscription): void
    {
        $customerId = $subscription['customer'] ?? null;
        $priceId = $subscription['items']['data'][0]['price']['id'] ?? null;

        if (! is_string($customerId) || ! is_string($priceId)) {
            return;
        }

        $workspace = Workspace::query()->where('stripe_id', $customerId)->first();

        if ($workspace === null) {
            return;
        }

        $plan = Plan::query()->where('stripe_price_id', $priceId)->first();

        if ($plan !== null) {
            $this->applyPlan($workspace, $plan->id);
        }
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function handleSubscriptionDeleted(array $subscription): void
    {
        $customerId = $subscription['customer'] ?? null;

        if (! is_string($customerId)) {
            return;
        }

        $workspace = Workspace::query()->where('stripe_id', $customerId)->first();
        $freePlan = Plan::query()->where('slug', 'free')->first();

        if ($workspace === null || $freePlan === null) {
            return;
        }

        $this->applyPlan($workspace, $freePlan->id);
    }

    private function applyPlan(Workspace $workspace, int $planId): void
    {
        $workspace->forceFill(['plan_id' => $planId])->save();
        $this->enforcer->afterPlanChange($workspace->fresh(['plan']));
    }
}
