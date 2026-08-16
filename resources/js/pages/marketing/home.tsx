import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    BookOpen,
    FolderTree,
    Globe,
    Languages,
    LayoutTemplate,
    Lock,
    Megaphone,
    MessageSquare,
    Palette,
    Search,
    Sparkles,
    Users,
    Wand2,
    Zap,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { MarketingFaqList } from '@/components/marketing/marketing-faq-list';
import { MarketingHead } from '@/components/marketing/marketing-head';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useAppName, usePlatformBranding } from '@/lib/app-branding';
import { marketingFaqItems } from '@/lib/marketing-faq';
import { register } from '@/routes';

type Plan = { id: number; name: string; slug: string; price_cents: number };

type Props = { plans: Plan[] };

const howItWorks = [
    {
        step: '1',
        title: 'Create your workspace',
        description: 'Sign up, verify your email, and reserve a unique project subdomain during onboarding.',
    },
    {
        step: '2',
        title: 'Write and publish',
        description:
            'Draft in Markdown with live preview, reusable blocks, nested pages, and optional AI generate or review — then publish in one click.',
    },
    {
        step: '3',
        title: 'Share beautiful docs',
        description:
            'Launch Classic or Guide templates on your subdomain, add a custom domain, and let visitors search or Ask AI.',
    },
];

const heroHighlights: { title: string; body: string; icon: LucideIcon }[] = [
    {
        title: 'Workspaces',
        body: 'Tenant isolation with roles for owners, admins, editors, and viewers — plus billing that scales with you.',
        icon: Users,
    },
    {
        title: 'Editor',
        body: 'Markdown with preview, blocks, import, revisions, and drag-and-drop nested page trees.',
        icon: BookOpen,
    },
    {
        title: 'Domains',
        body: 'Launch on your project subdomain, then verify your own hostname — Cloudflare-managed when configured.',
        icon: Globe,
    },
];

const platformFeatures: { title: string; description: string; icon: LucideIcon }[] = [
    {
        title: 'Public docs templates',
        description: 'Classic and Guide layouts with search, TOC, dark mode, and mobile navigation.',
        icon: LayoutTemplate,
    },
    {
        title: 'Versions & locales',
        description: 'Ship multiple documentation versions and languages with public switchers and hreflang.',
        icon: Languages,
    },
    {
        title: 'Announcements & changelog',
        description: 'Publish product updates alongside docs, linked from the footer and directory hub.',
        icon: Megaphone,
    },
    {
        title: 'Directory hub',
        description: 'A browseable public home for published pages, announcements, and changelog posts.',
        icon: FolderTree,
    },
    {
        title: 'Header, branding & search',
        description: 'Custom logo, colors, fonts, header links, and full-text search on published content.',
        icon: Search,
    },
    {
        title: 'Access & visibility',
        description: 'Public, private (members only), or password-protected documentation sites.',
        icon: Lock,
    },
    {
        title: 'Analytics & feedback',
        description: 'Page views, Yes/No helpfulness, recent comments, and CSV export on eligible plans.',
        icon: BarChart3,
    },
    {
        title: 'White-label options',
        description: 'Hide powered-by branding, theme docs as your product, and host on your own domain.',
        icon: Palette,
    },
];

export default function MarketingHome({ plans }: Props) {
    const branding = usePlatformBranding();
    const appName = useAppName();
    const faqItems = marketingFaqItems(appName);
    const tagline =
        branding?.tagline ??
        `Beautiful documentation for software teams. Publish on your subdomain, bring your own domain, and ship faster with ${appName}.`;

    return (
        <>
            <MarketingHead
                title="Beautiful documentation for software teams"
                description={tagline}
                path="/"
            />

            <section className="relative overflow-hidden border-b">
                <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(37,99,235,0.14),transparent_55%)] dark:bg-[radial-gradient(circle_at_top,rgba(59,130,246,0.12),transparent_55%)]" />
                <div className="relative mx-auto flex max-w-6xl flex-col gap-8 px-4 py-20 sm:px-6 lg:py-28">
                    <div className="inline-flex w-fit items-center gap-2 rounded-full border bg-background/80 px-3 py-1 text-xs font-medium text-muted-foreground shadow-sm backdrop-blur">
                        <Sparkles className="size-3.5 text-primary" />
                        Documentation platform for modern product teams
                    </div>
                    <h1 className="max-w-4xl text-4xl font-semibold tracking-tight sm:text-6xl">
                        Publish docs your customers will actually read.
                    </h1>
                    <p className="max-w-2xl text-lg leading-relaxed text-muted-foreground">{tagline}</p>
                    <div className="flex flex-wrap gap-3">
                        <Button asChild size="lg">
                            <Link href={register()}>Create your workspace</Link>
                        </Button>
                        <Button asChild variant="outline" size="lg">
                            <Link href="/examples">See examples</Link>
                        </Button>
                    </div>
                    <div className="grid gap-4 pt-8 sm:grid-cols-3">
                        {heroHighlights.map(({ title, body, icon: Icon }) => (
                            <Card
                                key={title}
                                className="border-border/70 bg-background/80 shadow-sm backdrop-blur"
                            >
                                <CardHeader>
                                    <Icon className="mb-2 size-5 text-primary" />
                                    <CardTitle>{title}</CardTitle>
                                    <CardDescription>{body}</CardDescription>
                                </CardHeader>
                            </Card>
                        ))}
                    </div>
                </div>
            </section>

            <section className="border-b bg-muted/20">
                <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                    <div className="max-w-2xl">
                        <p className="text-sm font-medium text-primary">How it works</p>
                        <h2 className="mt-2 text-3xl font-semibold tracking-tight">From signup to live docs in minutes</h2>
                        <p className="mt-4 text-muted-foreground">
                            {appName} keeps authoring, publishing, AI assistance, and discovery in one place — no separate
                            static site generator required.
                        </p>
                    </div>
                    <div className="mt-10 grid gap-4 md:grid-cols-3">
                        {howItWorks.map((item) => (
                            <Card key={item.step} className="shadow-sm">
                                <CardHeader>
                                    <div className="mb-2 flex size-8 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">
                                        {item.step}
                                    </div>
                                    <CardTitle>{item.title}</CardTitle>
                                    <CardDescription>{item.description}</CardDescription>
                                </CardHeader>
                            </Card>
                        ))}
                    </div>
                </div>
            </section>

            <section className="border-b">
                <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div className="max-w-2xl">
                            <p className="text-sm font-medium text-primary">Platform</p>
                            <h2 className="mt-2 text-3xl font-semibold tracking-tight">
                                Everything teams need to ship docs
                            </h2>
                            <p className="mt-4 text-muted-foreground">
                                Templates, domains, versions, announcements, analytics, and white-label branding — built
                                into {appName}.
                            </p>
                        </div>
                        <Button asChild variant="outline">
                            <Link href="/features">
                                View all features
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    </div>
                    <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {platformFeatures.map(({ title, description, icon: Icon }) => (
                            <Card key={title} className="shadow-sm">
                                <CardHeader>
                                    <Icon className="mb-2 size-5 text-primary" />
                                    <CardTitle className="text-base">{title}</CardTitle>
                                    <CardDescription>{description}</CardDescription>
                                </CardHeader>
                            </Card>
                        ))}
                    </div>
                </div>
            </section>

            <section className="border-b bg-muted/20">
                <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                    <div className="grid gap-10 lg:grid-cols-2 lg:items-center">
                        <div>
                            <p className="text-sm font-medium text-primary">AI-powered docs</p>
                            <h2 className="mt-2 text-3xl font-semibold tracking-tight">Write faster. Review better. Answer smarter.</h2>
                            <p className="mt-4 text-muted-foreground">
                                Generate first drafts from prompts or an existing site, review pages for clarity and
                                structure, then let visitors Ask AI on your public docs. Published pages are indexed
                                automatically so answers stay grounded in your content.
                            </p>
                            <Button asChild className="mt-6" variant="outline">
                                <Link href="/features">
                                    Explore AI features
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
                            {[
                                {
                                    icon: Wand2,
                                    title: 'Generate',
                                    description: 'Structured Markdown drafts from a brief or website URL.',
                                },
                                {
                                    icon: Sparkles,
                                    title: 'Review',
                                    description: 'Accuracy, grammar, or structure feedback you can apply in one click.',
                                },
                                {
                                    icon: MessageSquare,
                                    title: 'Ask AI',
                                    description: 'Cited answers for visitors, grounded in published documentation.',
                                },
                            ].map((item) => {
                                const Icon = item.icon;

                                return (
                                    <Card key={item.title} className="shadow-sm">
                                        <CardHeader>
                                            <Icon className="mb-2 size-5 text-primary" />
                                            <CardTitle className="text-base">{item.title}</CardTitle>
                                            <CardDescription>{item.description}</CardDescription>
                                        </CardHeader>
                                    </Card>
                                );
                            })}
                        </div>
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                <div className="mb-8 flex items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-primary">Pricing</p>
                        <h2 className="text-3xl font-semibold tracking-tight">Start free, scale when you ship</h2>
                    </div>
                    <Button asChild variant="ghost">
                        <Link href="/pricing">
                            Compare plans
                            <ArrowRight className="size-4" />
                        </Link>
                    </Button>
                </div>
                <div className="grid gap-4 md:grid-cols-3">
                    {plans.slice(0, 3).map((plan) => (
                        <Card
                            key={plan.id}
                            className={
                                plan.slug === 'pro'
                                    ? 'border-primary shadow-md ring-1 ring-primary/20'
                                    : 'shadow-sm'
                            }
                        >
                            <CardHeader>
                                <CardTitle>{plan.name}</CardTitle>
                                <CardDescription>
                                    {plan.price_cents === 0
                                        ? 'Free forever'
                                        : `$${(plan.price_cents / 100).toFixed(0)}/month`}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button
                                    asChild
                                    className="w-full"
                                    variant={plan.slug === 'pro' ? 'default' : 'outline'}
                                >
                                    <Link href={register()}>Get started</Link>
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </section>

            <section className="border-t bg-muted/30">
                <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                    <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-sm font-medium text-primary">FAQ</p>
                            <h2 className="mt-2 text-3xl font-semibold tracking-tight">Common questions</h2>
                        </div>
                        <Button asChild variant="ghost">
                            <Link href="/faq">
                                View all FAQs
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    </div>
                    <MarketingFaqList items={faqItems.slice(0, 4)} />
                </div>
            </section>

            <section className="border-t">
                <div className="mx-auto flex max-w-6xl flex-col items-start gap-6 px-4 py-16 sm:px-6 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 className="text-2xl font-semibold tracking-tight">Ready to ship better docs?</h2>
                        <p className="mt-2 text-muted-foreground">
                            Launch your {appName} workspace in minutes. No credit card required.
                        </p>
                    </div>
                    <Button asChild size="lg">
                        <Link href={register()}>
                            <Zap className="size-4" />
                            Start writing
                        </Link>
                    </Button>
                </div>
            </section>
        </>
    );
}
