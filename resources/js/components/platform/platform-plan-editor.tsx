import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    ORDERED_PLAN_FEATURES,
    ORDERED_PLAN_LIMITS,
    PLAN_FEATURE_LABELS,
    PLAN_LIMIT_LABELS,
} from '@/lib/plan-features';

type Plan = {
    id: number;
    name: string;
    slug: string;
    price_cents: number;
    stripe_price_id: string | null;
    limits: Record<string, number | null>;
    features: Record<string, boolean>;
    is_active: boolean;
};

type LimitState = Record<string, { unlimited: boolean; value: string }>;

function limitsFromPlan(limits: Record<string, number | null>): LimitState {
    const state: LimitState = {};

    for (const key of ORDERED_PLAN_LIMITS) {
        const raw = limits[key];

        state[key] = {
            unlimited: raw === null,
            value: raw === null ? '' : String(raw),
        };
    }

    return state;
}

function limitsToPayload(state: LimitState): Record<string, number | null> {
    const payload: Record<string, number | null> = {};

    for (const key of ORDERED_PLAN_LIMITS) {
        const entry = state[key];

        payload[key] = entry.unlimited ? null : Number(entry.value);
    }

    return payload;
}

export function PlatformPlanEditor({ plan, stripeConfigured }: { plan: Plan; stripeConfigured: boolean }) {
    const [priceDollars, setPriceDollars] = useState((plan.price_cents / 100).toFixed(plan.price_cents % 100 === 0 ? 0 : 2));
    const [stripePriceId, setStripePriceId] = useState(plan.stripe_price_id ?? '');
    const [limits, setLimits] = useState<LimitState>(() => limitsFromPlan(plan.limits));
    const [features, setFeatures] = useState<Record<string, boolean>>(() => {
        const initial: Record<string, boolean> = {};

        for (const key of ORDERED_PLAN_FEATURES) {
            initial[key] = Boolean(plan.features[key]);
        }

        return initial;
    });
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const priceCents = Math.round(Number(priceDollars) * 100);
    const needsStripePrice = priceCents > 0;

    const save = () => {
        setProcessing(true);
        setErrors({});

        router.post(
            `/platform/plans/${plan.id}`,
            {
                price_cents: priceCents,
                stripe_price_id: stripePriceId.trim() === '' ? null : stripePriceId.trim(),
                limits: limitsToPayload(limits),
                features,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onError: (pageErrors) => {
                    const mapped: Record<string, string> = {};

                    for (const [key, value] of Object.entries(pageErrors)) {
                        mapped[key] = String(value);
                    }

                    setErrors(mapped);
                },
            },
        );
    };

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between gap-2">
                    <CardTitle>{plan.name}</CardTitle>
                    {plan.slug === 'pro' && <Badge>Popular</Badge>}
                </div>
                <CardDescription>{plan.slug}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-5 text-sm">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor={`plan-price-${plan.id}`}>Price (USD / month)</Label>
                        <Input
                            id={`plan-price-${plan.id}`}
                            type="number"
                            min={0}
                            step="0.01"
                            value={priceDollars}
                            onChange={(event) => setPriceDollars(event.target.value)}
                        />
                        <p className="text-xs text-muted-foreground">Use 0 for free or contact-sales plans.</p>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor={`plan-stripe-${plan.id}`}>Stripe Price ID</Label>
                        <Input
                            id={`plan-stripe-${plan.id}`}
                            value={stripePriceId}
                            placeholder="price_..."
                            onChange={(event) => setStripePriceId(event.target.value)}
                        />
                        {needsStripePrice ? (
                            <p className="text-xs text-muted-foreground">
                                Required for paid plans — create in Stripe Dashboard → Products.
                            </p>
                        ) : (
                            <p className="text-xs text-muted-foreground">Optional for free plans.</p>
                        )}
                        {errors.stripe_price_id && (
                            <p className="text-xs text-destructive">{errors.stripe_price_id}</p>
                        )}
                        {stripeConfigured && needsStripePrice && stripePriceId.trim() === '' && (
                            <p className="text-xs text-amber-600 dark:text-amber-400">
                                Stripe is enabled — workspaces cannot subscribe until this is set.
                            </p>
                        )}
                    </div>
                </div>

                <div className="space-y-3">
                    <p className="font-medium">Quotas</p>
                    <div className="grid gap-3 sm:grid-cols-3">
                        {ORDERED_PLAN_LIMITS.map((key) => {
                            const entry = limits[key];

                            return (
                                <div key={key} className="rounded-lg border p-3 space-y-2">
                                    <Label>{PLAN_LIMIT_LABELS[key]}</Label>
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id={`${plan.id}-limit-${key}-unlimited`}
                                            checked={entry.unlimited}
                                            onCheckedChange={(checked) => {
                                                setLimits((prev) => ({
                                                    ...prev,
                                                    [key]: {
                                                        unlimited: checked === true,
                                                        value: checked === true ? '' : prev[key].value,
                                                    },
                                                }));
                                            }}
                                        />
                                        <label
                                            htmlFor={`${plan.id}-limit-${key}-unlimited`}
                                            className="text-xs text-muted-foreground"
                                        >
                                            Unlimited
                                        </label>
                                    </div>
                                    {!entry.unlimited && (
                                        <Input
                                            type="number"
                                            min={0}
                                            value={entry.value}
                                            onChange={(event) =>
                                                setLimits((prev) => ({
                                                    ...prev,
                                                    [key]: { ...prev[key], value: event.target.value },
                                                }))
                                            }
                                        />
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>

                <div className="space-y-3">
                    <p className="font-medium">Gated features</p>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {ORDERED_PLAN_FEATURES.map((key) => (
                            <label
                                key={key}
                                className="flex items-center gap-2 rounded-md border px-3 py-2"
                            >
                                <Checkbox
                                    checked={features[key]}
                                    onCheckedChange={(checked) =>
                                        setFeatures((prev) => ({ ...prev, [key]: checked === true }))
                                    }
                                />
                                <span>{PLAN_FEATURE_LABELS[key]}</span>
                            </label>
                        ))}
                    </div>
                </div>

                <Button size="sm" disabled={processing} onClick={save}>
                    {processing ? 'Saving…' : 'Save plan'}
                </Button>
            </CardContent>
        </Card>
    );
}
