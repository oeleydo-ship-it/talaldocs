import { Head, useForm, usePage } from '@inertiajs/react';
import { CreditCard } from 'lucide-react';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { ORDERED_PLAN_FEATURES, formatPlanFeature } from '@/lib/plan-features';

type Plan = {
    id: number;
    name: string;
    slug: string;
    price_cents: number;
    stripe_price_id: string | null;
    limits: Record<string, number | null>;
    features: Record<string, boolean>;
};

type Trial = {
    billing_enforced: boolean;
    requires_card: boolean;
    active: boolean;
    subscribed: boolean;
    ends_at: string | null;
    days_remaining: number;
    trial_days: number;
    trial_label: string;
};

type Props = {
    currentPlan: Plan | null;
    plans: Plan[];
    stripeConfigured: boolean;
    stripePortalAvailable: boolean;
    trial: Trial;
    subscriptionStatus: string | null;
};

export default function BillingShow({
    currentPlan,
    plans,
    stripeConfigured,
    stripePortalAvailable,
    trial,
    subscriptionStatus,
}: Props) {
    const form = useForm({ plan_id: currentPlan?.id ?? 0 });
    const portal = useForm({});
    const page = usePage<{ flash?: { warning?: string }; errors: Record<string, string> }>();
    const pageErrors = page.props.errors;
    const warning = page.props.flash?.warning;

    const description = trial.billing_enforced
        ? trial.subscribed
            ? 'Your subscription is active. Change plans or manage billing in Stripe.'
            : trial.active
              ? `${trial.days_remaining} day${trial.days_remaining === 1 ? '' : 's'} left in your free trial. Subscribe to keep access after it ends.`
              : trial.requires_card && !trial.ends_at
                ? `Add a payment method to start your ${trial.trial_label} trial.`
                : 'Your trial has ended. Subscribe to continue using the app.'
        : stripeConfigured
          ? 'Paid plans open Stripe Checkout. Free plans apply immediately.'
          : 'Stripe is not configured. Plan changes apply immediately for local testing.';

    return (
        <>
            <Head title="Billing" />
            <PageContainer>
                <PageHeader
                    title="Billing"
                    description={description}
                    actions={
                        stripePortalAvailable ? (
                            <Button variant="outline" onClick={() => portal.post('/billing/portal')}>
                                <CreditCard className="size-4" />
                                Manage subscription
                            </Button>
                        ) : undefined
                    }
                />

                {warning && (
                    <Alert variant="destructive">
                        <AlertTitle>Subscription required</AlertTitle>
                        <AlertDescription>{warning}</AlertDescription>
                    </Alert>
                )}

                {trial.billing_enforced && trial.active && !trial.subscribed && (
                    <Alert>
                        <AlertTitle>Free trial</AlertTitle>
                        <AlertDescription>
                            {trial.days_remaining} day{trial.days_remaining === 1 ? '' : 's'} remaining
                            {trial.ends_at ? ` (ends ${new Date(trial.ends_at).toLocaleDateString()})` : ''}. Choose a
                            paid plan to subscribe before access is paused.
                        </AlertDescription>
                    </Alert>
                )}

                {trial.billing_enforced && trial.subscribed && (
                    <Alert>
                        <AlertTitle>Subscription active</AlertTitle>
                        <AlertDescription>
                            Stripe status: {subscriptionStatus ?? 'active'}. You can change plans below or manage payment
                            methods in the customer portal.
                        </AlertDescription>
                    </Alert>
                )}

                {trial.billing_enforced && !trial.active && !trial.subscribed && (
                    <Alert variant="destructive">
                        <AlertTitle>{trial.requires_card && !trial.ends_at ? 'Start your trial' : 'Trial ended'}</AlertTitle>
                        <AlertDescription>
                            {trial.requires_card && !trial.ends_at
                                ? `Subscribe below to start a ${trial.trial_label} trial. A credit card is required.`
                                : 'Subscribe to a paid plan to restore dashboard, editor, and project access.'}
                        </AlertDescription>
                    </Alert>
                )}

                {!stripeConfigured && (
                    <Alert>
                        <AlertTitle>Configure Stripe</AlertTitle>
                        <AlertDescription>
                            Platform admins can enable Stripe under Platform → Settings → Payment and add Stripe Price
                            IDs on each paid plan.
                        </AlertDescription>
                    </Alert>
                )}

                {pageErrors.plan_id && (
                    <Alert variant="destructive">
                        <AlertTitle>Checkout unavailable</AlertTitle>
                        <AlertDescription>{pageErrors.plan_id}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {plans.map((plan) => {
                        const isPaid = plan.price_cents > 0;
                        const requiresStripe = isPaid && stripeConfigured;
                        const stripeReady = !requiresStripe || Boolean(plan.stripe_price_id);
                        const cta =
                            currentPlan?.id === plan.id && trial.subscribed
                                ? 'Selected'
                                : isPaid && stripeConfigured
                                  ? trial.requires_card && !trial.subscribed && !trial.active
                                    ? 'Start trial with card'
                                    : 'Subscribe with Stripe'
                                  : 'Choose plan';

                        return (
                            <Card
                                key={plan.id}
                                className={
                                    currentPlan?.id === plan.id
                                        ? 'border-primary shadow-md ring-1 ring-primary/20'
                                        : 'shadow-sm'
                                }
                            >
                                <CardHeader>
                                    <CardTitle className="flex items-center justify-between">
                                        {plan.name}
                                        {currentPlan?.id === plan.id && <Badge>Current</Badge>}
                                    </CardTitle>
                                    <CardDescription>
                                        {plan.price_cents === 0
                                            ? plan.slug === 'enterprise'
                                                ? 'Contact sales'
                                                : 'Free'
                                            : `$${(plan.price_cents / 100).toFixed(0)}/mo`}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3 text-sm">
                                    <div className="space-y-1.5 text-muted-foreground">
                                        <p>Projects: {plan.limits.projects ?? 'Unlimited'}</p>
                                        <p>Members: {plan.limits.members ?? 'Unlimited'}</p>
                                        <p>Custom domains: {plan.limits.custom_domains ?? 'Unlimited'}</p>
                                    </div>
                                    <ul className="space-y-1 text-xs text-muted-foreground">
                                        {ORDERED_PLAN_FEATURES
                                            .filter((key) => plan.features[key])
                                            .slice(0, 5)
                                            .map((key) => (
                                                <li key={key}>✓ {formatPlanFeature(key)}</li>
                                            ))}
                                    </ul>
                                    {requiresStripe && !stripeReady && (
                                        <p className="text-xs text-amber-600 dark:text-amber-400">
                                            Stripe Price ID missing on this plan.
                                        </p>
                                    )}
                                    <Button
                                        className="w-full"
                                        variant={plan.slug === 'pro' ? 'default' : 'outline'}
                                        disabled={
                                            (currentPlan?.id === plan.id && (trial.subscribed || !trial.billing_enforced))
                                            || form.processing
                                            || !stripeReady
                                        }
                                        onClick={() => {
                                            form.setData('plan_id', plan.id);
                                            form.post('/billing/checkout');
                                        }}
                                    >
                                        {currentPlan?.id === plan.id && !trial.billing_enforced
                                            ? 'Selected'
                                            : cta}
                                    </Button>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </PageContainer>
        </>
    );
}

BillingShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Billing', href: '/billing' },
    ],
};
