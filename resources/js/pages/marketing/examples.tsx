import { Link } from '@inertiajs/react';
import { ExternalLink, FolderTree, Languages, LayoutTemplate, Megaphone, Sparkles } from 'lucide-react';
import { MarketingHead } from '@/components/marketing/marketing-head';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useAppName } from '@/lib/app-branding';
import { register } from '@/routes';

type Demo = {
    id?: string;
    name: string;
    subdomain: string;
    layout: string;
    url: string;
};

type ExamplesCopy = {
    eyebrow: string;
    heading: string;
    intro: string;
    demos_heading: string;
    demos_description: string;
};

type Props = {
    demos: Demo[];
    publicContent?: {
        examples: ExamplesCopy;
    };
};

function templateLabel(layout: string): string {
    return layout === 'gitbook' ? 'Guide' : layout;
}

const templates = [
    {
        title: 'API reference',
        description: 'Structured endpoints, code samples with copy buttons, nested pages, and version switchers.',
        layout: 'classic',
    },
    {
        title: 'Product guides',
        description: 'Onboarding flows, how-to articles, directory hub, and searchable help centers.',
        layout: 'gitbook',
    },
    {
        title: 'Developer portal',
        description: 'Password-protected or private docs with workspace member access and branded navigation.',
        layout: 'classic',
    },
];

const showcase = [
    {
        icon: LayoutTemplate,
        title: 'Classic & Guide',
        description: 'Two premium public layouts with TOC, search, dark mode, and mobile nav.',
    },
    {
        icon: Languages,
        title: 'Versions & locales',
        description: 'Switch documentation versions and languages without rebuilding a static site.',
    },
    {
        icon: Megaphone,
        title: 'Announcements & changelog',
        description: 'Ship product updates next to your docs, linked from the footer and directory.',
    },
    {
        icon: FolderTree,
        title: 'Directory hub',
        description: 'A public hub that aggregates published pages, announcements, and changelog posts.',
    },
];

export default function MarketingExamples({ demos, publicContent }: Props) {
    const appName = useAppName();
    const copy = publicContent?.examples;

    return (
        <>
            <MarketingHead
                title={copy?.eyebrow || 'Examples'}
                description={copy?.intro || `See live demo documentation sites and preview the Classic and Guide templates in ${appName}.`}
                path="/examples"
            />

            <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                <div className="max-w-2xl">
                    <p className="text-sm font-medium text-primary">{copy?.eyebrow || 'Examples'}</p>
                    <h1 className="mt-2 text-4xl font-semibold tracking-tight">
                        {copy?.heading || 'Docs that feel premium out of the box'}
                    </h1>
                    <p className="mt-4 text-lg text-muted-foreground">
                        {copy?.intro ||
                            `${appName} ships readable typography, nested navigation, branded themes, search, and optional Ask AI — so your public site looks polished on day one.`}
                    </p>
                </div>

                <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {showcase.map((item) => {
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

                {demos.length > 0 ? (
                    <div className="mt-12">
                        <div className="flex items-center gap-2">
                            <Sparkles className="size-4 text-primary" />
                            <h2 className="text-lg font-semibold">{copy?.demos_heading || 'Live demo sites'}</h2>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {copy?.demos_description || 'Published documentation from seeded workspaces on this instance.'}
                        </p>
                        <div className="mt-4 grid gap-4 md:grid-cols-3">
                            {demos.map((demo) => (
                                <Card key={demo.id || demo.url} className="shadow-sm transition-shadow hover:shadow-md">
                                    <CardHeader>
                                        <div className="flex items-center justify-between gap-2">
                                            <CardTitle className="text-base">{demo.name}</CardTitle>
                                            <Badge variant="outline" className="capitalize">
                                                {templateLabel(demo.layout)}
                                            </Badge>
                                        </div>
                                        <CardDescription>{demo.subdomain}</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <Button asChild variant="default" className="w-full">
                                            <a href={demo.url} target="_blank" rel="noreferrer">
                                                View live docs
                                                <ExternalLink className="size-4" />
                                            </a>
                                        </Button>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </div>
                ) : (
                    <Card className="mt-12 border-dashed shadow-sm">
                        <CardContent className="flex flex-col items-start gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="font-medium">No live demos yet</p>
                                <p className="text-sm text-muted-foreground">
                                    No live demos are listed right now. Publish a docs site or add a custom demo URL in
                                    platform settings.
                                </p>
                            </div>
                            <Button asChild>
                                <Link href={register()}>Create workspace</Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                <div className="mt-12">
                    <div className="flex items-center gap-2">
                        <LayoutTemplate className="size-4 text-primary" />
                        <h2 className="text-lg font-semibold">Start from a template</h2>
                    </div>
                    <div className="mt-4 grid gap-4 md:grid-cols-3">
                        {templates.map((example) => (
                            <Card key={example.title} className="shadow-sm">
                                <CardHeader>
                                    <div className="flex items-center justify-between gap-2">
                                        <CardTitle>{example.title}</CardTitle>
                                        <Badge variant="secondary" className="capitalize">
                                            {templateLabel(example.layout)}
                                        </Badge>
                                    </div>
                                    <CardDescription>{example.description}</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Button asChild variant="outline" className="w-full">
                                        <Link href={register()}>Start your own</Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </div>

                <div className="mt-12 overflow-hidden rounded-2xl border bg-muted/30 p-6 sm:p-8">
                    <p className="text-sm font-medium text-muted-foreground">Classic template preview</p>
                    <div className="mt-4 grid gap-4 lg:grid-cols-[220px_1fr]">
                        <div className="rounded-xl border bg-background p-4 text-sm shadow-sm">
                            <p className="font-medium">Getting started</p>
                            <p className="mt-2 text-muted-foreground">Installation</p>
                            <p className="pl-3 text-muted-foreground">Prerequisites</p>
                            <p className="text-muted-foreground">Authentication</p>
                            <p className="text-muted-foreground">Webhooks</p>
                            <p className="mt-4 font-medium">Updates</p>
                            <p className="mt-2 text-muted-foreground">Announcements</p>
                            <p className="text-muted-foreground">Changelog</p>
                        </div>
                        <div className="rounded-xl border bg-background p-6 shadow-sm">
                            <h2 className="text-2xl font-semibold">Authentication</h2>
                            <p className="mt-4 text-muted-foreground">
                                Use bearer tokens in the Authorization header for all API requests. Nested pages, search,
                                and Ask AI help readers find the right guide quickly.
                            </p>
                            <pre className="mt-4 overflow-auto rounded-lg bg-muted p-4 text-xs">
                                curl -H &quot;Authorization: Bearer ...&quot;
                            </pre>
                        </div>
                    </div>
                </div>
            </section>
        </>
    );
}
