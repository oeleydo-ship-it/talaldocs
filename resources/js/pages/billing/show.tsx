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

type Props = {
    currentPlan: Plan | null;
    plans: Plan[];
    stripeConfigured: boolean;
    stripePortalAvailable: boolean;
};

export default function BillingShow({ currentPlan, plans, stripeConfigured, stripePortalAvailable }: Props) {
    const form = useForm({ plan_id: currentPlan?.id ?? 0 });
    const portal = useForm({});
    const pageErrors = usePage().props.errors as Record<string, string>;

    return (
        <>
            <Head title="Billing" />
            <PageContainer>
                <PageHeader
                    title="Billing"
                    description={
                        stripeConfigured
                            ? 'Paid plans open Stripe Checkout. Free plans apply immediately.'
                            : 'Stripe is not configured. Plan changes apply immediately for local testing.'
                    }
                    actions={
                        stripePortalAvailable ? (
                            <Button variant="outline" onClick={() => portal.post('/billing/portal')}>
                                <CreditCard className="size-4" />
                                Manage subscription
                            </Button>
                        ) : undefined
                    }
                />

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
                                            currentPlan?.id === plan.id || form.processing || !stripeReady
                                        }
                                        onClick={() => {
                                            form.setData('plan_id', plan.id);
                                            form.post('/billing/checkout');
                                        }}
                                    >
                                        {currentPlan?.id === plan.id
                                            ? 'Selected'
                                            : isPaid && stripeConfigured
                                              ? 'Subscribe with Stripe'
                                              : 'Choose plan'}
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
