import { CopyButton } from '@/components/copy-button';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ExternalLink, FolderOpen, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

type Domain = {
    id: number;
    hostname: string;
    status: string;
    is_primary: boolean;
    verification_token: string;
    error_message: string | null;
    ssl_status: string | null;
    ownership_txt_name: string | null;
    ownership_txt_value: string | null;
    cname_target: string;
    cloudflare_managed: boolean;
    ssl_ready: boolean;
};

type DnsSettings = {
    cloudflare_configured: boolean;
    cname_target: string;
    tenant_subdomain_cname: string;
};

type HeaderLink = { label: string; url: string };

type Props = {
    project: {
        id: number;
        name: string;
        subdomain: string;
        visibility: string;
        has_password: boolean;
        use_path_urls: boolean;
        primary_color: string;
        accent_color: string;
        font_family: string;
        heading_font: string;
        docs_template: 'classic' | 'gitbook';
        docs_layout: 'centered' | 'wide';
        github_edit_url: string | null;
        header_links: HeaderLink[];
        logo_url: string | null;
        favicon_url: string | null;
        og_image_url: string | null;
        public_url: string;
    };
    domains: Domain[];
    versions: { id: number; name: string; slug: string; is_default: boolean }[];
    languages: { id: number; language_id: number; code: string; name: string; is_default: boolean }[];
    availableLanguages: { id: number; code: string; name: string }[];
    features: Record<string, boolean>;
    appDomain: string;
    dns: DnsSettings;
    aiIndex: {
        pages: number;
        chunks: number;
        indexed_at: string | null;
    };
};

function domainStatusLabel(status: string): string {
    switch (status) {
        case 'pending':
            return 'Pending';
        case 'verifying':
            return 'Verifying';
        case 'active':
            return 'Active';
        case 'failed':
            return 'Failed';
        default:
            return status;
    }
}
const SETTINGS_TABS = ['general', 'visibility', 'branding', 'template', 'navigation', 'domains', 'versions', 'languages'] as const;
type SettingsTab = (typeof SETTINGS_TABS)[number];
const DEFAULT_TAB: SettingsTab = 'general';

function tabFromHash(hash: string): SettingsTab {
    const value = hash.replace(/^#/, '');
    return SETTINGS_TABS.includes(value as SettingsTab) ? (value as SettingsTab) : DEFAULT_TAB;
}

function BrandingAssetPreview({ label, url }: { label: string; url: string | null }) {
    if (!url) {
        return null;
    }

    return (
        <div className="mt-2 flex items-center gap-3 rounded-md border bg-muted/20 p-2">
            <img src={url} alt="" className="size-10 object-contain" />
            <p className="text-xs text-muted-foreground">Current {label}</p>
        </div>
    );
}

function submitBranding(
    branding: ReturnType<
        typeof useForm<{
            primary_color: string;
            accent_color: string;
            font_family: string;
            heading_font: string;
            docs_template: 'classic' | 'gitbook';
            docs_layout: 'centered' | 'wide';
            github_edit_url: string;
            logo: File | null;
            favicon: File | null;
            og_image: File | null;
        }>
    >,
    projectId: number,
) {
    branding.transform((data) => {
        const payload: Record<string, unknown> = { ...data };

        if (!payload.logo) {
            delete payload.logo;
        }

        if (!payload.favicon) {
            delete payload.favicon;
        }

        if (!payload.og_image) {
            delete payload.og_image;
        }

        return payload;
    });

    branding.post(`/projects/${projectId}/branding`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            branding.setData('logo', null);
            branding.setData('favicon', null);
            branding.setData('og_image', null);
        },
    });
}

export default function ProjectSettings({
    project,
    domains,
    versions,
    languages,
    availableLanguages,
    features,
    appDomain,
    dns,
    aiIndex,
}: Props) {
    const [activeTab, setActiveTab] = useState<SettingsTab>(() => tabFromHash(window.location.hash));

    useEffect(() => {
        const onHashChange = () => setActiveTab(tabFromHash(window.location.hash));
        window.addEventListener('hashchange', onHashChange);
        return () => window.removeEventListener('hashchange', onHashChange);
    }, []);

    const handleTabChange = (value: string) => {
        const tab = value as SettingsTab;
        setActiveTab(tab);
        window.history.replaceState(null, '', `#${tab}`);
    };

    const visibility = useForm({
        visibility: project.visibility,
        password: '',
        use_path_urls: project.use_path_urls,
    });
    const general = useForm({
        name: project.name,
        subdomain: project.subdomain,
    });
    const branding = useForm({
        primary_color: project.primary_color,
        accent_color: project.accent_color,
        font_family: project.font_family,
        heading_font: project.heading_font,
        docs_template: project.docs_template,
        docs_layout: project.docs_layout,
        github_edit_url: project.github_edit_url ?? '',
        logo: null as File | null,
        favicon: null as File | null,
        og_image: null as File | null,
    });
    const domainForm = useForm({ hostname: '' });
    const versionForm = useForm({ name: '', copy_from: versions[0]?.id ?? '' });
    const languageForm = useForm({ language_id: availableLanguages[0]?.id ?? '' });
    const aiIndexForm = useForm({});
    const headerLinksForm = useForm<{ header_links: HeaderLink[] }>({
        header_links: project.header_links.length > 0 ? project.header_links : [{ label: '', url: '' }],
    });

    const updateHeaderLink = (index: number, field: keyof HeaderLink, value: string) => {
        const next = [...headerLinksForm.data.header_links];
        next[index] = { ...next[index], [field]: value };
        headerLinksForm.setData('header_links', next);
    };

    const moveHeaderLink = (index: number, direction: -1 | 1) => {
        const next = [...headerLinksForm.data.header_links];
        const target = index + direction;
        if (target < 0 || target >= next.length) {
            return;
        }
        [next[index], next[target]] = [next[target], next[index]];
        headerLinksForm.setData('header_links', next);
    };

    const removeHeaderLink = (index: number) => {
        const next = headerLinksForm.data.header_links.filter((_, itemIndex) => itemIndex !== index);
        headerLinksForm.setData('header_links', next.length > 0 ? next : [{ label: '', url: '' }]);
    };

    const addHeaderLink = () => {
        if (headerLinksForm.data.header_links.length >= 8) {
            return;
        }
        headerLinksForm.setData('header_links', [...headerLinksForm.data.header_links, { label: '', url: '' }]);
    };

    return (
        <>
            <Head title={`${project.name} settings`} />
            <PageContainer>
                <PageHeader
                    title="Project settings"
                    description={
                        <a
                            href={project.public_url}
                            target="_blank"
                            rel="noreferrer"
                            className="hover:text-foreground hover:underline"
                        >
                            {project.public_url}
                        </a>
                    }
                    actions={
                        <>
                            <Badge variant="outline" className="font-normal capitalize">
                                {project.visibility}
                            </Badge>
                            <Button asChild>
                                <Link href={`/projects/${project.id}/editor`}>
                                    <FolderOpen className="size-4" />
                                    Editor
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <a href={project.public_url} target="_blank" rel="noreferrer">
                                    <ExternalLink className="size-4" />
                                    Public site
                                </a>
                            </Button>
                        </>
                    }
                />

                <Tabs value={activeTab} onValueChange={handleTabChange} className="gap-6">
                    <TabsList className="h-auto w-full flex-wrap justify-start gap-1 bg-muted/40 p-1 sm:w-auto">
                        <TabsTrigger value="general">General</TabsTrigger>
                        <TabsTrigger value="visibility">Visibility</TabsTrigger>
                        <TabsTrigger value="branding">Branding</TabsTrigger>
                        <TabsTrigger value="template">Template</TabsTrigger>
                        <TabsTrigger value="navigation">Navigation</TabsTrigger>
                        <TabsTrigger value="domains">Domains</TabsTrigger>
                        <TabsTrigger value="versions">Versions</TabsTrigger>
                        <TabsTrigger value="languages">Languages</TabsTrigger>
                    </TabsList>

                    <TabsContent value="general">
                        <Card className="shadow-sm">
                            <CardHeader>
                                <CardTitle>General</CardTitle>
                                <CardDescription>Project name and subdomain.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form
                                    className="grid gap-3 md:grid-cols-2"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        general.patch(`/projects/${project.id}`);
                                    }}
                                >
                                    <div className="space-y-2">
                                        <Label>Name</Label>
                                        <Input
                                            value={general.data.name}
                                            onChange={(event) => general.setData('name', event.target.value)}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Shown in the public docs header. Clicking it takes visitors to your docs home
                                            page.
                                        </p>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Subdomain</Label>
                                        <Input
                                            value={general.data.subdomain}
                                            onChange={(event) => general.setData('subdomain', event.target.value)}
                                        />
                                    </div>
                                    <Button type="submit" disabled={general.processing}>
                                        {general.processing ? 'Saving…' : 'Save general'}
                                    </Button>
                                </form>
                                <div className="mt-6 flex flex-wrap gap-2 border-t pt-4">
                                    <Button variant="outline" asChild>
                                        <Link href={`/projects/${project.id}/posts`}>Manage posts →</Link>
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <a href="/members">Manage workspace members</a>
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        onClick={() => {
                                            if (confirm('Delete this project permanently?')) {
                                                router.delete(`/projects/${project.id}`);
                                            }
                                        }}
                                    >
                                        Delete project
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="visibility">
                        <Card className="shadow-sm">
                            <CardHeader>
                                <CardTitle>Visibility</CardTitle>
                                <CardDescription>Public, private (members only), or password.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form
                                    className="grid gap-3 md:grid-cols-2"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        visibility.post(`/projects/${project.id}/visibility`);
                                    }}
                                >
                                    <div className="space-y-2">
                                        <Label>Visibility</Label>
                                        <select
                                            className="h-9 w-full rounded-md border bg-transparent px-3 text-sm"
                                            value={visibility.data.visibility}
                                            onChange={(event) => visibility.setData('visibility', event.target.value)}
                                        >
                                            <option value="public">Public</option>
                                            <option value="private">Private</option>
                                            <option value="password">Password</option>
                                        </select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Password</Label>
                                        <Input
                                            type="password"
                                            value={visibility.data.password}
                                            onChange={(event) => visibility.setData('password', event.target.value)}
                                            placeholder={project.has_password ? 'Leave blank to keep' : 'Set a password'}
                                        />
                                    </div>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={visibility.data.use_path_urls}
                                            onChange={(event) =>
                                                visibility.setData('use_path_urls', event.target.checked)
                                            }
                                        />
                                        Prefer path URLs (/docs/...)
                                    </label>
                                    <Button type="submit" disabled={visibility.processing}>
                                        {visibility.processing ? 'Saving…' : 'Save visibility'}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="branding">
                        <Card className="shadow-sm">
                            <CardHeader>
                                <CardTitle>Branding</CardTitle>
                                <CardDescription>Logo, favicon, Open Graph, colors, and fonts.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form
                                    className="grid gap-3 md:grid-cols-2"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        submitBranding(branding, project.id);
                                    }}
                                >
                                    <div className="space-y-2">
                                        <Label>Primary color</Label>
                                        <Input
                                            type="color"
                                            value={branding.data.primary_color}
                                            onChange={(event) => branding.setData('primary_color', event.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Accent color</Label>
                                        <Input
                                            type="color"
                                            value={branding.data.accent_color}
                                            onChange={(event) => branding.setData('accent_color', event.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Body font</Label>
                                        <Input
                                            value={branding.data.font_family}
                                            onChange={(event) => branding.setData('font_family', event.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Heading font</Label>
                                        <Input
                                            value={branding.data.heading_font}
                                            onChange={(event) => branding.setData('heading_font', event.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Logo</Label>
                                        <Input
                                            type="file"
                                            accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
                                            onChange={(event) => branding.setData('logo', event.target.files?.[0] ?? null)}
                                        />
                                        <BrandingAssetPreview label="logo" url={project.logo_url} />
                                        {branding.errors.logo ? (
                                            <p className="text-sm text-destructive">{branding.errors.logo}</p>
                                        ) : null}
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Favicon</Label>
                                        <Input
                                            type="file"
                                            accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml,image/x-icon,.ico"
                                            onChange={(event) => branding.setData('favicon', event.target.files?.[0] ?? null)}
                                        />
                                        <BrandingAssetPreview label="favicon" url={project.favicon_url} />
                                        {branding.errors.favicon ? (
                                            <p className="text-sm text-destructive">{branding.errors.favicon}</p>
                                        ) : null}
                                    </div>
                                    <div className="space-y-2 md:col-span-2">
                                        <Label>Open Graph image</Label>
                                        <Input
                                            type="file"
                                            accept="image/png,image/jpeg,image/gif,image/webp"
                                            onChange={(event) => branding.setData('og_image', event.target.files?.[0] ?? null)}
                                        />
                                        <BrandingAssetPreview label="Open Graph image" url={project.og_image_url} />
                                        {branding.errors.og_image ? (
                                            <p className="text-sm text-destructive">{branding.errors.og_image}</p>
                                        ) : null}
                                    </div>
                                    <Button type="submit" disabled={branding.processing}>
                                        {branding.processing ? 'Saving…' : 'Save branding'}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="template" className="space-y-6">
                        <Card className="shadow-sm">
                            <CardHeader>
                                <CardTitle>Documentation template</CardTitle>
                                <CardDescription>
                                    Choose how your public docs site looks. Preview changes on your live site after saving.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <form
                                    className="grid gap-4"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        submitBranding(branding, project.id);
                                    }}
                                >
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <label
                                            className={`cursor-pointer rounded-xl border p-4 transition-colors ${
                                                branding.data.docs_template === 'classic'
                                                    ? 'border-primary ring-2 ring-primary/20'
                                                    : 'hover:border-muted-foreground/30'
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="docs_template"
                                                value="classic"
                                                className="sr-only"
                                                checked={branding.data.docs_template === 'classic'}
                                                onChange={() => branding.setData('docs_template', 'classic')}
                                            />
                                            <p className="font-medium">Classic</p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Wide layout with optional classic width toggle, inline search, and familiar docs chrome.
                                            </p>
                                            <div className="mt-3 h-24 rounded-md border bg-muted/30" />
                                        </label>
                                        <label
                                            className={`cursor-pointer rounded-xl border p-4 transition-colors ${
                                                branding.data.docs_template === 'gitbook'
                                                    ? 'border-primary ring-2 ring-primary/20'
                                                    : 'hover:border-muted-foreground/30'
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="docs_template"
                                                value="gitbook"
                                                className="sr-only"
                                                checked={branding.data.docs_template === 'gitbook'}
                                                onChange={() => branding.setData('docs_template', 'gitbook')}
                                            />
                                            <p className="font-medium">Guide</p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Grouped sidebar, breadcrumbs, numbered step cards, search modal, and sticky table of contents.
                                            </p>
                                            <div className="mt-3 h-24 rounded-md border bg-gradient-to-br from-muted/40 to-muted/10" />
                                        </label>
                                    </div>
                                    {branding.data.docs_template === 'gitbook' && (
                                        <div className="space-y-3">
                                            <Label>Guide layout</Label>
                                            <div className="grid gap-4 md:grid-cols-2">
                                                <label
                                                    className={`cursor-pointer rounded-xl border p-4 transition-colors ${
                                                        branding.data.docs_layout === 'centered'
                                                            ? 'border-primary ring-2 ring-primary/20'
                                                            : 'hover:border-muted-foreground/30'
                                                    }`}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="docs_layout"
                                                        value="centered"
                                                        className="sr-only"
                                                        checked={branding.data.docs_layout === 'centered'}
                                                        onChange={() => branding.setData('docs_layout', 'centered')}
                                                    />
                                                    <p className="font-medium">Original (centered)</p>
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        Centered container with margins — the default Guide layout.
                                                    </p>
                                                </label>
                                                <label
                                                    className={`cursor-pointer rounded-xl border p-4 transition-colors ${
                                                        branding.data.docs_layout === 'wide'
                                                            ? 'border-primary ring-2 ring-primary/20'
                                                            : 'hover:border-muted-foreground/30'
                                                    }`}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="docs_layout"
                                                        value="wide"
                                                        className="sr-only"
                                                        checked={branding.data.docs_layout === 'wide'}
                                                        onChange={() => branding.setData('docs_layout', 'wide')}
                                                    />
                                                    <p className="font-medium">Full width</p>
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        Sidebar flush to the left edge with content using the full horizontal space.
                                                    </p>
                                                </label>
                                            </div>
                                        </div>
                                    )}
                                    <div className="space-y-2">
                                        <Label>Edit on GitHub URL (optional)</Label>
                                        <Input
                                            value={branding.data.github_edit_url}
                                            placeholder="https://github.com/org/repo/edit/main/docs/{slug}.md"
                                            onChange={(event) => branding.setData('github_edit_url', event.target.value)}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Use {'{slug}'} as a placeholder for the page slug. Shown in the Guide template header.
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Button type="submit" disabled={branding.processing}>
                                            {branding.processing ? 'Saving…' : 'Save template'}
                                        </Button>
                                        <Button variant="outline" asChild>
                                            <a href={project.public_url} target="_blank" rel="noreferrer">
                                                Preview template
                                            </a>
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="navigation" className="space-y-6">
                        <Card className="shadow-sm">
                            <CardHeader>
                                <CardTitle>Header navigation menu</CardTitle>
                                <CardDescription>
                                    Menu links appear in the center of the public docs header. Up to two action links
                                    (Login, Sign up, Register, Get started) show on the right next to search and theme
                                    controls. Put CTAs last in the list if you are not using those labels.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form
                                    className="space-y-4"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        const payload = headerLinksForm.data.header_links
                                            .map((link) => ({
                                                label: link.label.trim(),
                                                url: link.url.trim(),
                                            }))
                                            .filter((link) => link.label !== '' && link.url !== '');

                                        headerLinksForm.transform(() => ({ header_links: payload }));
                                        headerLinksForm.post(`/projects/${project.id}/header-links`, {
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                headerLinksForm.setData(
                                                    'header_links',
                                                    payload.length > 0 ? payload : [{ label: '', url: '' }],
                                                );
                                            },
                                        });
                                    }}
                                >
                                    <div className="space-y-3">
                                        {headerLinksForm.data.header_links.map((link, index) => (
                                            <div key={index} className="grid gap-2 rounded-lg border p-3 md:grid-cols-[1fr_1fr_auto]">
                                                <div className="space-y-2">
                                                    <Label>Label</Label>
                                                    <Input
                                                        value={link.label}
                                                        maxLength={40}
                                                        placeholder="Login"
                                                        onChange={(event) =>
                                                            updateHeaderLink(index, 'label', event.target.value)
                                                        }
                                                    />
                                                    {headerLinksForm.errors[`header_links.${index}.label`] ? (
                                                        <p className="text-sm text-destructive">
                                                            {headerLinksForm.errors[`header_links.${index}.label`]}
                                                        </p>
                                                    ) : null}
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>URL</Label>
                                                    <Input
                                                        value={link.url}
                                                        placeholder="/login or https://app.example.com/signup"
                                                        onChange={(event) =>
                                                            updateHeaderLink(index, 'url', event.target.value)
                                                        }
                                                    />
                                                    {headerLinksForm.errors[`header_links.${index}.url`] ? (
                                                        <p className="text-sm text-destructive">
                                                            {headerLinksForm.errors[`header_links.${index}.url`]}
                                                        </p>
                                                    ) : null}
                                                </div>
                                                <div className="flex items-end gap-1">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        aria-label="Move link up"
                                                        disabled={index === 0}
                                                        onClick={() => moveHeaderLink(index, -1)}
                                                    >
                                                        <ArrowUp className="size-4" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        aria-label="Move link down"
                                                        disabled={index === headerLinksForm.data.header_links.length - 1}
                                                        onClick={() => moveHeaderLink(index, 1)}
                                                    >
                                                        <ArrowDown className="size-4" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label="Remove link"
                                                        onClick={() => removeHeaderLink(index)}
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={headerLinksForm.data.header_links.length >= 8}
                                            onClick={addHeaderLink}
                                        >
                                            <Plus className="size-4" />
                                            Add link
                                        </Button>
                                        <Button type="submit" disabled={headerLinksForm.processing}>
                                            {headerLinksForm.processing ? 'Saving…' : 'Save navigation menu'}
                                        </Button>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Up to 8 links. Labels like Login, Sign up, or Register appear as buttons on the
                                        right (max 2). Other links stay centered. External URLs open in a new tab; paths
                                        starting with / open in the same tab.
                                    </p>
                                </form>
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardHeader>
                                <CardTitle>AI knowledge index</CardTitle>
                                <CardDescription>
                                    Published documentation is chunked and indexed so Ask AI can retrieve accurate answers
                                    from your docs. Pages are indexed automatically when you publish.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <div className="rounded-lg border bg-muted/20 p-4">
                                        <p className="text-xs uppercase tracking-wide text-muted-foreground">Indexed pages</p>
                                        <p className="mt-1 text-2xl font-semibold">{aiIndex.pages}</p>
                                    </div>
                                    <div className="rounded-lg border bg-muted/20 p-4">
                                        <p className="text-xs uppercase tracking-wide text-muted-foreground">Knowledge chunks</p>
                                        <p className="mt-1 text-2xl font-semibold">{aiIndex.chunks}</p>
                                    </div>
                                    <div className="rounded-lg border bg-muted/20 p-4">
                                        <p className="text-xs uppercase tracking-wide text-muted-foreground">Last indexed</p>
                                        <p className="mt-1 text-sm font-medium">
                                            {aiIndex.indexed_at
                                                ? new Date(aiIndex.indexed_at).toLocaleString()
                                                : 'Not indexed yet'}
                                        </p>
                                    </div>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    Rebuild the index after bulk imports or if Ask AI misses content from published pages.
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={aiIndexForm.processing}
                                    onClick={() => {
                                        aiIndexForm.post(`/projects/${project.id}/ai-index`, {
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    {aiIndexForm.processing ? 'Indexing…' : 'Rebuild AI index'}
                                </Button>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="domains">
                        <Card className="shadow-sm">
                            <CardHeader>
                                <CardTitle>Custom domains</CardTitle>
                                <CardDescription>
                                    {dns.cloudflare_configured
                                        ? 'Connect your hostname with Cloudflare SSL for SaaS. Point a CNAME to the platform fallback origin and complete DNS validation.'
                                        : `Point a CNAME to ${project.subdomain}.${appDomain} and add the TXT challenge.`}
                                    {!features.custom_domain && ' Custom domains require Pro or higher.'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="rounded-lg border bg-muted/30 p-3 text-sm text-muted-foreground">
                                    <p>
                                        <strong className="text-foreground">Tenant subdomain:</strong>{' '}
                                        <code>{project.subdomain}.{appDomain}</code> should CNAME to{' '}
                                        <code>{dns.tenant_subdomain_cname}</code>
                                        {dns.cloudflare_configured && ' (managed in Cloudflare).'}
                                    </p>
                                    <p className="mt-2">
                                        <strong className="text-foreground">Localhost:</strong> public docs use path URLs at{' '}
                                        <code>/docs/{'{project-slug}'}</code> because host-based routing is not available on{' '}
                                        <code>php artisan serve</code>.
                                    </p>
                                    <p className="mt-2">
                                        <strong className="text-foreground">Production:</strong> verified custom domains become the
                                        primary public URL. Active primary domains redirect traffic away from the platform subdomain.
                                    </p>
                                </div>
                                <form
                                    className="flex gap-2"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        domainForm.post(`/projects/${project.id}/domains`);
                                    }}
                                >
                                    <Input
                                        value={domainForm.data.hostname}
                                        onChange={(event) => domainForm.setData('hostname', event.target.value)}
                                        placeholder="docs.example.com"
                                    />
                                    <Button type="submit">Add domain</Button>
                                </form>
                                {domains.map((domain) => {
                                    const cnameTarget = domain.cname_target;
                                    const txtName = `_anytdocs-challenge.${domain.hostname}`;
                                    const txtValue = `anytdocs-verify=${domain.verification_token}`;
                                    const ownershipName = domain.ownership_txt_name;
                                    const ownershipValue = domain.ownership_txt_value;

                                    return (
                                        <div key={domain.id} className="space-y-3 rounded-lg border p-3 text-sm">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <p className="font-medium">{domain.hostname}</p>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Badge variant="outline">
                                                        {domainStatusLabel(domain.status)}
                                                        {domain.is_primary ? ' · primary' : ''}
                                                    </Badge>
                                                    {domain.cloudflare_managed && (
                                                        <Badge variant="secondary">Cloudflare</Badge>
                                                    )}
                                                    {domain.ssl_ready && (
                                                        <Badge>SSL active</Badge>
                                                    )}
                                                    {domain.cloudflare_managed && domain.ssl_status && !domain.ssl_ready && (
                                                        <Badge variant="outline">SSL {domain.ssl_status}</Badge>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="space-y-2 rounded-md bg-muted/20 p-3 font-mono text-xs">
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <span>CNAME {domain.hostname} → {cnameTarget}</span>
                                                    <CopyButton value={cnameTarget} label="Copy target" />
                                                </div>
                                                {ownershipName && ownershipValue && (
                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                        <span>TXT {ownershipName} = {ownershipValue}</span>
                                                        <CopyButton value={ownershipValue} label="Copy TXT" />
                                                    </div>
                                                )}
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <span>TXT {txtName} = {txtValue}</span>
                                                    <CopyButton value={txtValue} label="Copy TXT" />
                                                </div>
                                            </div>
                                            {domain.error_message && <p className="text-destructive">{domain.error_message}</p>}
                                            <div className="flex flex-wrap gap-2">
                                                <Button size="sm" variant="outline" onClick={() => router.post(`/projects/${project.id}/domains/${domain.id}/verify`)}>
                                                    Verify now
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={domain.status !== 'active'}
                                                    onClick={() => router.post(`/projects/${project.id}/domains/${domain.id}/primary`)}
                                                >
                                                    Make primary
                                                </Button>
                                                <Button size="sm" variant="ghost" onClick={() => router.delete(`/projects/${project.id}/domains/${domain.id}`)}>
                                                    Remove
                                                </Button>
                                            </div>
                                        </div>
                                    );
                                })}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="versions">
                        <Card className="shadow-sm">
                            <CardHeader>
                                <CardTitle>Versions</CardTitle>
                                <CardDescription>
                                    {features.versioning ? 'Create and copy versions.' : 'Additional versions require Business.'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {versions.map((version) => (
                                    <div key={version.id} className="flex items-center justify-between text-sm">
                                        <span>
                                            {version.name} {version.is_default ? '(default)' : ''}
                                        </span>
                                        {!version.is_default && (
                                            <Button size="sm" variant="ghost" onClick={() => router.post(`/projects/${project.id}/versions/${version.id}/default`)}>
                                                Set default
                                            </Button>
                                        )}
                                    </div>
                                ))}
                                <form
                                    className="flex gap-2"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        versionForm.post(`/projects/${project.id}/versions`);
                                    }}
                                >
                                    <Input value={versionForm.data.name} onChange={(event) => versionForm.setData('name', event.target.value)} placeholder="v2.0" />
                                    <Button type="submit">Add</Button>
                                </form>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="languages">
                        <Card className="shadow-sm">
                            <CardHeader>
                                <CardTitle>Languages</CardTitle>
                                <CardDescription>
                                    {features.localization ? 'Add locales and hreflang alternates.' : 'Additional languages require Business.'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {languages.map((language) => (
                                    <div key={language.id} className="flex items-center justify-between text-sm">
                                        <span>
                                            {language.name} {language.is_default ? '(default)' : ''}
                                        </span>
                                        {!language.is_default && (
                                            <Button size="sm" variant="ghost" onClick={() => router.post(`/projects/${project.id}/languages/${language.language_id}/default`)}>
                                                Set default
                                            </Button>
                                        )}
                                    </div>
                                ))}
                                <form
                                    className="flex gap-2"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        languageForm.post(`/projects/${project.id}/languages`);
                                    }}
                                >
                                    <select
                                        className="h-9 w-full rounded-md border bg-transparent px-3 text-sm"
                                        value={languageForm.data.language_id}
                                        onChange={(event) => languageForm.setData('language_id', event.target.value)}
                                    >
                                        {availableLanguages.map((language) => (
                                            <option key={language.id} value={language.id}>
                                                {language.name}
                                            </option>
                                        ))}
                                    </select>
                                    <Button type="submit">Add</Button>
                                </form>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </PageContainer>
        </>
    );
}

ProjectSettings.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Projects', href: '/projects' },
        { title: 'Settings', href: '#' },
    ],
};
