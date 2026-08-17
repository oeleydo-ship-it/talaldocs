<?php

namespace App\Support;

use App\Models\Plan;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
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
            'customer.subscription.created',
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

        $status = $this->statusFromCheckoutSession($session);

        if ($status !== null) {
            $workspace->forceFill(['subscription_status' => $status])->save();
        }

        $trialEnd = $this->timestampFrom($session['trial_end'] ?? null)
            ?? $this->timestampFrom($session['subscription_details']['trial_end'] ?? null);

        if ($trialEnd !== null) {
            $workspace->forceFill(['trial_ends_at' => $trialEnd])->save();
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

        if (! is_string($customerId)) {
            return;
        }

        $workspace = Workspace::query()->where('stripe_id', $customerId)->first();

        if ($workspace === null) {
            return;
        }

        $this->syncSubscription($workspace, $subscription);

        $priceId = $subscription['items']['data'][0]['price']['id'] ?? null;
        $plan = is_string($priceId)
            ? Plan::query()->where('stripe_price_id', $priceId)->first()
            : null;

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

        $workspace->forceFill(['subscription_status' => 'canceled'])->save();

        $this->applyPlan($workspace, $freePlan->id);
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function syncSubscription(Workspace $workspace, array $subscription): void
    {
        $status = $subscription['status'] ?? null;
        $trialEnd = $this->timestampFrom($subscription['trial_end'] ?? null);

        $workspace->forceFill([
            'subscription_status' => is_string($status) && filled($status) ? $status : $workspace->subscription_status,
            'trial_ends_at' => $trialEnd ?? $workspace->trial_ends_at,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function statusFromCheckoutSession(array $session): ?string
    {
        $subscription = $session['subscription'] ?? null;

        if (is_array($subscription) && is_string($subscription['status'] ?? null)) {
            return $subscription['status'];
        }

        if (($session['payment_status'] ?? null) === 'no_payment_required'
            || $this->timestampFrom($session['trial_end'] ?? null) !== null
            || $this->timestampFrom($session['subscription_details']['trial_end'] ?? null) !== null) {
            return 'trialing';
        }

        if (is_string($subscription) && filled($subscription)) {
            return 'active';
        }

        return null;
    }

    private function timestampFrom(mixed $value): ?Carbon
    {
        if (! is_numeric($value)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $value);
    }

    private function applyPlan(Workspace $workspace, int $planId): void
    {
        $workspace->forceFill(['plan_id' => $planId])->save();
        $this->enforcer->afterPlanChange($workspace->fresh(['plan']));
    }
}
