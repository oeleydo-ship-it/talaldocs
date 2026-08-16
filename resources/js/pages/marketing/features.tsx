import { Link } from '@inertiajs/react';
import {
    BarChart3,
    BookOpen,
    CreditCard,
    FolderTree,
    Globe,
    Languages,
    LayoutTemplate,
    Lock,
    Megaphone,
    MessageSquare,
    Navigation,
    Palette,
    Puzzle,
    Search,
    Sparkles,
    Upload,
    Users,
    Wand2,
    ListTree,
    Zap,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { MarketingHead } from '@/components/marketing/marketing-head';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useAppName } from '@/lib/app-branding';
import {
    marketingAiFeatures,
    marketingAuthoringFeatures,
    marketingPublishingFeatures,
    marketingWorkspaceFeatures,
} from '@/lib/marketing-features';
import { register } from '@/routes';

const featureIcons: Record<string, LucideIcon> = {
    editor: Zap,
    blocks: Puzzle,
    import: Upload,
    subpages: ListTree,
    templates: LayoutTemplate,
    domains: Globe,
    versions: Languages,
    directory: FolderTree,
    posts: Megaphone,
    header: Navigation,
    generate: Wand2,
    review: Sparkles,
    ask: MessageSquare,
    team: Users,
    billing: CreditCard,
    visibility: Lock,
    search: Search,
    analytics: BarChart3,
    whitelabel: Palette,
};

const templates = [
    {
        icon: BookOpen,
        title: 'Classic template',
        description:
            'Clean sidebar navigation, in-page table of contents, inline search, and typography tuned for long-form guides.',
    },
    {
        icon: LayoutTemplate,
        title: 'Guide template',
        description:
            'Modern docs chrome with grouped sidebar sections, breadcrumbs, ⌘K search, step cards, and helpfulness feedback.',
    },
];

function FeatureGrid({
    features,
}: {
    features: { key: string; title: string; description: string }[];
}) {
    return (
        <div className="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {features.map((feature) => {
                const Icon = featureIcons[feature.key] ?? Sparkles;

                return (
                    <Card key={feature.key} className="shadow-sm transition-shadow hover:shadow-md">
                        <CardHeader>
                            <Icon className="mb-2 size-5 text-primary" />
                            <CardTitle>{feature.title}</CardTitle>
                            <CardDescription>{feature.description}</CardDescription>
                        </CardHeader>
                    </Card>
                );
            })}
        </div>
    );
}

export default function MarketingFeatures() {
    const appName = useAppName();

    return (
        <>
            <MarketingHead
                title="Features"
                description={`Authoring, publishing, AI, templates, domains, analytics, and team workflows — everything in ${appName}.`}
                path="/features"
            />

            <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                <div className="max-w-2xl">
                    <p className="text-sm font-medium text-primary">Features</p>
                    <h1 className="mt-2 text-4xl font-semibold tracking-tight">Everything you need to ship docs</h1>
                    <p className="mt-4 text-lg text-muted-foreground">
                        From first draft to custom domain — {appName} covers authoring, beautiful public sites, AI
                        assistance, announcements, analytics, and team collaboration.
                    </p>
                </div>
            </section>

            <section className="border-t">
                <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                    <div className="max-w-2xl">
                        <p className="text-sm font-medium text-primary">Authoring</p>
                        <h2 className="mt-2 text-3xl font-semibold tracking-tight">Write once, structure for scale</h2>
                        <p className="mt-4 text-muted-foreground">
                            A fast Markdown editor with nested pages, reusable blocks, and bulk import so your docs tree
                            stays organized as you grow.
                        </p>
                    </div>
                    <FeatureGrid features={marketingAuthoringFeatures} />
                </div>
            </section>

            <section className="border-t bg-muted/20">
                <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                    <div className="max-w-2xl">
                        <p className="text-sm font-medium text-primary">Publishing</p>
                        <h2 className="mt-2 text-3xl font-semibold tracking-tight">Public docs that feel finished</h2>
                        <p className="mt-4 text-muted-foreground">
                            Templates, custom domains, versions, locales, directory hub, announcements, and branded
                            navigation — without a separate static site pipeline.
                        </p>
                    </div>
                    <FeatureGrid features={marketingPublishingFeatures} />
                </div>
            </section>

            <section className="border-t">
                <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                    <div className="max-w-2xl">
                        <p className="text-sm font-medium text-primary">AI</p>
                        <h2 className="mt-2 text-3xl font-semibold tracking-tight">Documentation that writes back</h2>
                        <p className="mt-4 text-muted-foreground">
                            Optional AI features help teams draft faster, improve existing pages, and help readers find
                            answers without leaving your docs site.
                        </p>
                    </div>
                    <FeatureGrid features={marketingAiFeatures} />
                </div>
            </section>

            <section className="border-t bg-muted/20">
                <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                    <div className="max-w-2xl">
                        <p className="text-sm font-medium text-primary">Workspace</p>
                        <h2 className="mt-2 text-3xl font-semibold tracking-tight">Teams, access, and insights</h2>
                        <p className="mt-4 text-muted-foreground">
                            Invite teammates, gate visibility, measure what readers find helpful, and brand docs as your
                            own product.
                        </p>
                    </div>
                    <FeatureGrid features={marketingWorkspaceFeatures} />
                </div>
            </section>

            <section className="border-t">
                <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                    <div className="max-w-2xl">
                        <p className="text-sm font-medium text-primary">Templates</p>
                        <h2 className="mt-2 text-3xl font-semibold tracking-tight">Premium layouts out of the box</h2>
                        <p className="mt-4 text-muted-foreground">
                            Pick a public docs template per project. Switch anytime from project settings without
                            rewriting content.
                        </p>
                    </div>
                    <div className="mt-10 grid gap-4 md:grid-cols-2">
                        {templates.map((template) => {
                            const Icon = template.icon;

                            return (
                                <Card key={template.title} className="shadow-sm">
                                    <CardHeader>
                                        <Icon className="mb-2 size-5 text-primary" />
                                        <CardTitle>{template.title}</CardTitle>
                                        <CardDescription>{template.description}</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <Button asChild variant="outline">
                                            <Link href="/examples">See live examples</Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                </div>
            </section>

            <section className="border-t bg-muted/30">
                <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                    <Card className="border-primary/20 bg-primary/5">
                        <CardContent className="flex flex-col items-start justify-between gap-4 p-6 sm:flex-row sm:items-center">
                            <div>
                                <p className="font-medium">Ready to put {appName} to work?</p>
                                <p className="text-sm text-muted-foreground">
                                    Create a workspace, publish your first page, and share a polished docs site today.
                                </p>
                            </div>
                            <Button asChild>
                                <Link href={register()}>Create workspace</Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </>
    );
}
