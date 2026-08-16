import { Link } from '@inertiajs/react';
import { Check, Minus } from 'lucide-react';
import { MarketingHead } from '@/components/marketing/marketing-head';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAppName } from '@/lib/app-branding';
import { formatPlanFeature, ORDERED_PLAN_FEATURES } from '@/lib/plan-features';
import { register } from '@/routes';
import { cn } from '@/lib/utils';

type Plan = {
    id: number;
    name: string;
    slug: string;
    price_cents: number;
    limits: Record<string, number | null>;
    features: Record<string, boolean>;
};

type Props = { plans: Plan[] };

function formatLimit(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return 'Unlimited';
    }

    return String(value);
}

function formatPrice(plan: Plan): string {
    if (plan.slug === 'enterprise') {
        return 'Contact sales';
    }

    if (plan.price_cents === 0) {
        return 'Free';
    }

    return `$${(plan.price_cents / 100).toFixed(0)}/month`;
}

export default function MarketingPricing({ plans }: Props) {
    const appName = useAppName();

    return (
        <>
            <MarketingHead
                title="Pricing"
                description={`Simple ${appName} plans with server-enforced limits. Upgrade for custom domains, AI, analytics, versioning, localization, and white-label branding.`}
                path="/pricing"
            />

            <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                <div className="mx-auto max-w-2xl text-center">
                    <p className="text-sm font-medium text-primary">Pricing</p>
                    <h1 className="mt-2 text-4xl font-semibold tracking-tight">Simple plans that scale with you</h1>
                    <p className="mt-4 text-muted-foreground">
                        Limits are enforced on the server. Upgrade when you need custom domains, AI documentation,
                        analytics, versioning, localization, audit logs, or white-label branding.
                    </p>
                </div>

                <div className="mt-12 grid gap-4 lg:grid-cols-4">
                    {plans.map((plan) => (
                        <Card
                            key={plan.id}
                            className={cn(
                                plan.slug === 'pro' && 'border-primary shadow-md ring-1 ring-primary/20',
                                plan.slug !== 'pro' && 'shadow-sm',
                            )}
                        >
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle>{plan.name}</CardTitle>
                                    {plan.slug === 'pro' && <Badge>Popular</Badge>}
                                </div>
                                <CardDescription>{formatPrice(plan)}</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4 text-sm">
                                <p>Projects: {formatLimit(plan.limits.projects)}</p>
                                <p>Members: {formatLimit(plan.limits.members)}</p>
                                <p>Custom domains: {formatLimit(plan.limits.custom_domains)}</p>
                                <ul className="space-y-2">
                                    {Object.entries(plan.features)
                                        .filter(([, enabled]) => enabled)
                                        .slice(0, 4)
                                        .map(([feature]) => (
                                            <li key={feature} className="flex items-center gap-2">
                                                <Check className="size-4 text-primary" />
                                                {formatPlanFeature(feature)}
                                            </li>
                                        ))}
                                </ul>
                                <Button
                                    asChild
                                    className="w-full"
                                    variant={plan.slug === 'pro' ? 'default' : 'outline'}
                                >
                                    {plan.slug === 'enterprise' ? (
                                        <Link href="/contact">Contact sales</Link>
                                    ) : (
                                        <Link href={register()}>Get started</Link>
                                    )}
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="mt-16">
                    <h2 className="text-2xl font-semibold tracking-tight">Compare all features</h2>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Every capability below is enforced by your workspace plan on the server.
                    </p>
                    <div className="mt-6 overflow-hidden rounded-xl border bg-card shadow-sm">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[220px]">Feature</TableHead>
                                    {plans.map((plan) => (
                                        <TableHead key={plan.id} className="text-center">
                                            {plan.name}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {ORDERED_PLAN_FEATURES.map((featureKey) => (
                                    <TableRow key={featureKey}>
                                        <TableCell className="font-medium">{formatPlanFeature(featureKey)}</TableCell>
                                        {plans.map((plan) => (
                                            <TableCell key={`${plan.id}-${featureKey}`} className="text-center">
                                                {plan.features[featureKey] ? (
                                                    <Check className="mx-auto size-4 text-primary" aria-label="Included" />
                                                ) : (
                                                    <Minus className="mx-auto size-4 text-muted-foreground/50" aria-label="Not included" />
                                                )}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <Card className="mt-12 border-primary/20 bg-primary/5">
                    <CardContent className="flex flex-col items-start justify-between gap-4 p-6 sm:flex-row sm:items-center">
                        <div>
                            <p className="font-medium">Need SSO, custom limits, or a security review?</p>
                            <p className="text-sm text-muted-foreground">
                                Enterprise includes SAML, dedicated support, and tailored workspace limits.
                            </p>
                        </div>
                        <Button asChild>
                            <Link href="/contact">Talk to sales</Link>
                        </Button>
                    </CardContent>
                </Card>
            </section>
        </>
    );
}
