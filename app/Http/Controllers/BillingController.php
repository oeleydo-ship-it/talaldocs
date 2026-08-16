<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesWorkspaceProject;
use App\Models\Plan;
use App\Support\Audit;
use App\Support\PlanBlueprint;
use App\Support\PlanEnforcer;
use App\Support\StripeCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    use ResolvesWorkspaceProject;

    public function __construct(
        private Audit $audit,
        private StripeCheckout $stripe,
        private PlanEnforcer $enforcer,
    ) {}

    public function show(Request $request): Response
    {
        $workspace = $this->workspace($request);
        $this->authorize('update', $workspace);

        return Inertia::render('billing/show', [
            'currentPlan' => $workspace->plan,
            'plans' => Plan::query()->where('is_active', true)->orderBy('price_cents')->get(),
            'stripeConfigured' => $this->stripe->isConfigured(),
            'stripePortalAvailable' => $this->stripe->portalUrl($workspace, route('billing.show')) !== null,
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request);
        $this->authorize('update', $workspace);

        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $plan = Plan::query()->findOrFail($data['plan_id']);

        if ($plan->price_cents > 0 && $this->stripe->isConfigured()) {
            if (! $this->stripe->canCheckout($plan)) {
                return back()->withErrors([
                    'plan_id' => 'This plan requires a Stripe Price ID. Configure it in Platform → Plans.',
                ]);
            }

            $session = $this->stripe->createSession(
                $workspace,
                $plan,
                route('billing.show').'?checkout=success',
                route('billing.show').'?checkout=cancelled',
            );

            if ($session === null) {
                return back()->withErrors([
                    'plan_id' => 'Could not start Stripe Checkout. Verify Platform → Settings → Payment and the Stripe Price ID.',
                ]);
            }

            $this->audit->record($workspace->id, 'billing.checkout_started', $request->user(), $workspace, [
                'plan' => $plan->slug,
            ]);

            return redirect()->away($session['url']);
        }

        if ($this->stripe->canCheckout($plan)) {
            $session = $this->stripe->createSession(
                $workspace,
                $plan,
                route('billing.show').'?checkout=success',
                route('billing.show').'?checkout=cancelled',
            );

            if ($session !== null) {
                $this->audit->record($workspace->id, 'billing.checkout_started', $request->user(), $workspace, [
                    'plan' => $plan->slug,
                ]);

                return redirect()->away($session['url']);
            }
        }

        $workspace->forceFill(['plan_id' => $plan->id])->save();
        $this->enforcer->afterPlanChange($workspace->fresh(['plan']));
        $this->audit->record($workspace->id, 'billing.plan_changed', $request->user(), $workspace, [
            'plan' => $plan->slug,
            'mode' => $this->stripe->isConfigured() ? 'local-fallback' : 'local',
        ]);

        return back()->with(
            'status',
            $plan->price_cents > 0
                ? 'Configure Stripe in Platform → Settings → Payment and add a Stripe Price ID for paid plans.'
                : 'Plan updated.',
        );
    }

    public function portal(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request);
        $this->authorize('update', $workspace);

        $url = $this->stripe->portalUrl($workspace, route('billing.show'));

        abort_unless($url !== null, 422, 'Stripe customer portal is not available for this workspace.');

        return redirect()->away($url);
    }
}
