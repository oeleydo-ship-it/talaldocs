<?php

namespace App\Support;

use App\Models\PlatformSetting;
use App\Models\Workspace;
use Carbon\CarbonInterface;

class BillingAccess
{
    /**
     * @var list<string>
     */
    public const ACTIVE_STATUSES = ['active', 'trialing'];

    /**
     * Trial length options stored in `trial_days`. `0` means one calendar month.
     *
     * @var list<int>
     */
    public const TRIAL_DAY_OPTIONS = [7, 14, 30, 0];

    public function billingEnforced(): bool
    {
        if (! PlatformConfig::stripeConfigured()) {
            return false;
        }

        if (! PlatformConfig::tableAvailable()) {
            return false;
        }

        return (bool) PlatformSetting::instance()->billing_enforced;
    }

    public function trialRequiresCard(): bool
    {
        if (! PlatformConfig::tableAvailable()) {
            return true;
        }

        return (bool) PlatformSetting::instance()->trial_requires_card;
    }

    public function trialDays(): int
    {
        if (! PlatformConfig::tableAvailable()) {
            return 14;
        }

        $days = (int) PlatformSetting::instance()->trial_days;

        return in_array($days, self::TRIAL_DAY_OPTIONS, true) ? $days : 14;
    }

    public function trialPeriodDays(?CarbonInterface $from = null): int
    {
        $from ??= now();
        $days = $this->trialDays();

        if ($days === 0) {
            return max(1, (int) $from->diffInDays($from->copy()->addMonth(), false));
        }

        return max(1, $days);
    }

    public function trialEndsAt(?CarbonInterface $from = null): CarbonInterface
    {
        $from = ($from ?? now())->copy();

        if ($this->trialDays() === 0) {
            return $from->addMonth();
        }

        return $from->addDays($this->trialDays());
    }

    public function workspaceHasAccess(?Workspace $workspace): bool
    {
        if ($workspace === null) {
            return true;
        }

        if (! $this->billingEnforced()) {
            return true;
        }

        return $this->hasActiveSubscription($workspace) || $this->onTrial($workspace);
    }

    public function hasActiveSubscription(Workspace $workspace): bool
    {
        return in_array($workspace->subscription_status, self::ACTIVE_STATUSES, true);
    }

    public function onTrial(Workspace $workspace): bool
    {
        return $workspace->trial_ends_at !== null && $workspace->trial_ends_at->isFuture();
    }

    public function startLocalTrialIfEligible(Workspace $workspace): void
    {
        if ($this->trialRequiresCard()) {
            return;
        }

        if ($workspace->trial_ends_at !== null) {
            return;
        }

        $workspace->forceFill([
            'trial_ends_at' => $this->trialEndsAt(),
        ])->save();
    }

    public function remainingTrialDays(Workspace $workspace): int
    {
        if ($this->onTrial($workspace)) {
            $seconds = $workspace->trial_ends_at->getTimestamp() - now()->getTimestamp();

            return max(1, (int) ceil($seconds / 86400));
        }

        if ($workspace->trial_ends_at === null && ! $this->hasActiveSubscription($workspace)) {
            return $this->trialPeriodDays();
        }

        return 0;
    }

    /**
     * @return array{
     *     billing_enforced: bool,
     *     requires_card: bool,
     *     active: bool,
     *     subscribed: bool,
     *     ends_at: string|null,
     *     days_remaining: int,
     *     trial_days: int,
     *     trial_label: string
     * }
     */
    public function summary(Workspace $workspace): array
    {
        $active = $this->onTrial($workspace);

        return [
            'billing_enforced' => $this->billingEnforced(),
            'requires_card' => $this->trialRequiresCard(),
            'active' => $active,
            'subscribed' => $this->hasActiveSubscription($workspace),
            'ends_at' => $workspace->trial_ends_at?->toIso8601String(),
            'days_remaining' => $active ? $this->remainingTrialDays($workspace) : 0,
            'trial_days' => $this->trialDays(),
            'trial_label' => $this->trialLabel(),
        ];
    }

    public function trialLabel(): string
    {
        return $this->trialDays() === 0 ? '1 month' : $this->trialDays().' days';
    }

    public function blockedMessage(Workspace $workspace): string
    {
        if ($this->trialRequiresCard() && $workspace->trial_ends_at === null && ! $this->hasActiveSubscription($workspace)) {
            return 'Add a payment method to start your '.$this->trialLabel().' trial.';
        }

        return 'Your trial has ended. Subscribe to keep using the app.';
    }
}
