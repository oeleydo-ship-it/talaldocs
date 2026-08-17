import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { PlatformPublicContentForm } from '@/components/platform/platform-public-content-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import type { PublicContentSettings } from '@/types/platform';

type PlatformSettings = {
    app_domain: string;
    app_url: string;
    reserved_subdomains: string[];
    maintenance_mode: boolean;
    mail_configured: boolean;
    stripe_configured: boolean;
};

type GeneralSettings = {
    app_name: string;
    app_domain: string;
    support_email: string | null;
    tagline: string | null;
    logo_url: string | null;
    favicon_url: string | null;
    hide_app_name_next_to_logo: boolean;
};

type SmtpSettings = {
    mail_mailer: string;
    mail_host: string | null;
    mail_port: number | null;
    mail_username: string | null;
    mail_encryption: string | null;
    mail_from_address: string | null;
    mail_from_name: string | null;
    mail_password_set: boolean;
    mail_password_masked: string | null;
    configured: boolean;
};

type PaymentSettings = {
    stripe_enabled: boolean;
    stripe_key: string | null;
    stripe_secret_set: boolean;
    stripe_secret_masked: string | null;
    stripe_webhook_secret_set: boolean;
    stripe_webhook_secret_masked: string | null;
    billing_enforced: boolean;
    trial_days: number;
    trial_requires_card: boolean;
    configured: boolean;
};

type AiProviderPreset = {
    label: string;
    base_url: string;
    base_urls?: Record<string, string>;
    models: string[];
    default_model: string;
};

type AiSettings = {
    ai_enabled: boolean;
    ai_provider: string;
    ai_base_url: string;
    ai_model: string;
    ai_api_key_masked: string | null;
    ai_api_key_set: boolean;
    providers: Record<string, AiProviderPreset>;
};

type CloudflareSettings = {
    cloudflare_enabled: boolean;
    cloudflare_zone_id: string | null;
    cloudflare_account_id: string | null;
    cloudflare_fallback_origin: string | null;
    cloudflare_auto_subdomains: boolean;
    cloudflare_api_token_set: boolean;
    cloudflare_api_token_masked: string | null;
    configured: boolean;
    app_domain: string;
    cname_target?: string | null;
};

type Props = {
    section: string;
    platformSettings: PlatformSettings;
    generalSettings: GeneralSettings;
    smtpSettings: SmtpSettings;
    paymentSettings: PaymentSettings;
    aiSettings: AiSettings;
    cloudflareSettings: CloudflareSettings;
    publicContent: PublicContentSettings;
};

function AssetPreview({ label, url }: { label: string; url: string | null }) {
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

function AiSettingsForm({ settings }: { settings: AiSettings }) {
    const [aiEnabled, setAiEnabled] = useState(settings.ai_enabled);
    const [provider, setProvider] = useState(settings.ai_provider);
    const [apiKey, setApiKey] = useState('');
    const [baseUrl, setBaseUrl] = useState(settings.ai_base_url);
    const [model, setModel] = useState(settings.ai_model);
    const [testing, setTesting] = useState(false);

    const providerPreset = settings.providers[provider];
    const modelOptions = providerPreset?.models ?? [];

    const applyProviderDefaults = (nextProvider: string) => {
        const preset = settings.providers[nextProvider];

        if (!preset) {
            return;
        }

        setProvider(nextProvider);
        setBaseUrl(preset.base_url);
        setModel(preset.default_model);
    };

    const payload = () => ({
        ai_enabled: aiEnabled,
        ai_provider: provider,
        ai_api_key: apiKey.trim() === '' ? null : apiKey,
        ai_base_url: provider === 'openai' ? null : (baseUrl || null),
        ai_model: model,
    });

    const testConnection = async () => {
        setTesting(true);

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const response = await fetch('/platform/settings/ai/test', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload()),
            });

            const json = (await response.json()) as { ok?: boolean; message?: string };

            if (response.ok && json.ok) {
                toast.success(json.message ?? 'Connection successful.');
            } else {
                toast.error(json.message ?? 'Connection failed.');
            }
        } finally {
            setTesting(false);
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>AI configuration</CardTitle>
                <CardDescription>
                    Platform-wide AI provider for documentation generation and public docs Ask. Applies to all
                    workspaces.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={aiEnabled} onChange={(event) => setAiEnabled(event.target.checked)} />
                    Enable AI features
                </label>

                <div className="grid gap-2">
                    <Label>Provider</Label>
                    <select
                        className="rounded-md border bg-transparent px-3 py-2 text-sm"
                        value={provider}
                        onChange={(event) => applyProviderDefaults(event.target.value)}
                    >
                        {Object.entries(settings.providers).map(([id, preset]) => (
                            <option key={id} value={id}>
                                {preset.label}
                            </option>
                        ))}
                    </select>
                </div>

                {provider === 'kimi' && providerPreset?.base_urls && (
                    <div className="grid gap-2">
                        <Label>Region / Base URL</Label>
                        <select
                            className="rounded-md border bg-transparent px-3 py-2 text-sm"
                            value={baseUrl}
                            onChange={(event) => setBaseUrl(event.target.value)}
                        >
                            {Object.entries(providerPreset.base_urls).map(([url, label]) => (
                                <option key={url} value={url}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                {provider === 'custom' && (
                    <div className="grid gap-2">
                        <Label>Base URL</Label>
                        <Input value={baseUrl} onChange={(event) => setBaseUrl(event.target.value)} />
                    </div>
                )}

                <div className="grid gap-2">
                    <Label>Model</Label>
                    <select
                        className="rounded-md border bg-transparent px-3 py-2 text-sm"
                        value={modelOptions.includes(model) ? model : '__custom__'}
                        onChange={(event) => {
                            if (event.target.value !== '__custom__') {
                                setModel(event.target.value);
                            }
                        }}
                    >
                        {modelOptions.map((option) => (
                            <option key={option} value={option}>
                                {option}
                            </option>
                        ))}
                        <option value="__custom__">Custom model slug…</option>
                    </select>
                    {!modelOptions.includes(model) && (
                        <Input value={model} onChange={(event) => setModel(event.target.value)} placeholder="model-slug" />
                    )}
                </div>

                <div className="grid gap-2">
                    <Label>API key</Label>
                    <Input
                        type="password"
                        value={apiKey}
                        onChange={(event) => setApiKey(event.target.value)}
                        placeholder={settings.ai_api_key_set ? 'Leave blank to keep current key' : 'sk-...'}
                    />
                    {settings.ai_api_key_masked && (
                        <p className="text-xs text-muted-foreground">Current key: {settings.ai_api_key_masked}</p>
                    )}
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button type="button" onClick={() => router.post('/platform/settings/ai', payload())}>
                        Save AI settings
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={testing || (!settings.ai_api_key_set && apiKey.trim() === '')}
                        onClick={() => void testConnection()}
                    >
                        {testing ? 'Testing…' : 'Test connection'}
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function CloudflareSettingsForm({ settings }: { settings: CloudflareSettings }) {
    const [enabled, setEnabled] = useState(settings.cloudflare_enabled);
    const [autoSubdomains, setAutoSubdomains] = useState(settings.cloudflare_auto_subdomains);
    const [apiToken, setApiToken] = useState('');
    const [testing, setTesting] = useState(false);

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const form = event.currentTarget;
        const payload: Record<string, string | boolean | null> = {
            cloudflare_enabled: enabled,
            cloudflare_zone_id: (form.elements.namedItem('cloudflare_zone_id') as HTMLInputElement).value,
            cloudflare_account_id: (form.elements.namedItem('cloudflare_account_id') as HTMLInputElement).value,
            cloudflare_fallback_origin: (form.elements.namedItem('cloudflare_fallback_origin') as HTMLInputElement).value,
            cloudflare_auto_subdomains: autoSubdomains,
        };

        if (apiToken.trim() !== '') {
            payload.cloudflare_api_token = apiToken;
        }

        router.post('/platform/settings/cloudflare', payload, { preserveScroll: true });
    };

    const testConnection = async () => {
        setTesting(true);

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const response = await fetch('/platform/settings/cloudflare/test', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: '{}',
            });
            const json = (await response.json()) as { ok?: boolean; message?: string };

            if (response.ok && json.ok) {
                toast.success(json.message ?? 'Cloudflare connection successful.');
            } else {
                toast.error(json.message ?? 'Could not connect to Cloudflare.');
            }
        } finally {
            setTesting(false);
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>DNS & Cloudflare</CardTitle>
                <CardDescription>
                    SSL for SaaS custom hostnames and optional tenant subdomain provisioning on{' '}
                    <code>{settings.app_domain}</code>.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form className="grid gap-4" onSubmit={submit}>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={enabled} onChange={(event) => setEnabled(event.target.checked)} />
                        Enable Cloudflare DNS integration
                    </label>
                    <div className="grid gap-2">
                        <Label htmlFor="cloudflare_zone_id">Zone ID</Label>
                        <Input
                            id="cloudflare_zone_id"
                            name="cloudflare_zone_id"
                            defaultValue={settings.cloudflare_zone_id ?? ''}
                            placeholder="Cloudflare zone for your app domain"
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="cloudflare_account_id">Account ID (optional)</Label>
                        <Input
                            id="cloudflare_account_id"
                            name="cloudflare_account_id"
                            defaultValue={settings.cloudflare_account_id ?? ''}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="cloudflare_fallback_origin">Fallback origin (CNAME target)</Label>
                        <Input
                            id="cloudflare_fallback_origin"
                            name="cloudflare_fallback_origin"
                            defaultValue={settings.cloudflare_fallback_origin ?? ''}
                            placeholder={`fallback.${settings.app_domain}`}
                        />
                        <p className="text-xs text-muted-foreground">
                            Customer hostnames and tenant subdomains should CNAME to this hostname. Configure it as your
                            Cloudflare SSL for SaaS fallback origin.
                        </p>
                    </div>
                    <label className="flex items-start gap-3 rounded-lg border px-3 py-3 text-sm">
                        <input
                            type="checkbox"
                            checked={autoSubdomains}
                            onChange={(event) => setAutoSubdomains(event.target.checked)}
                        />
                        <span>
                            <span className="font-medium">Auto-provision tenant subdomains</span>
                            <span className="mt-1 block text-muted-foreground">
                                Create proxied CNAME records for each project subdomain via the Cloudflare API. Use a
                                wildcard <code>*.{settings.app_domain}</code> instead if you prefer not to auto-create records.
                            </span>
                        </span>
                    </label>
                    <div className="grid gap-2">
                        <Label htmlFor="cloudflare_api_token">API token</Label>
                        <Input
                            id="cloudflare_api_token"
                            type="password"
                            value={apiToken}
                            onChange={(event) => setApiToken(event.target.value)}
                            placeholder={settings.cloudflare_api_token_set ? 'Leave blank to keep current token' : 'Cloudflare API token'}
                        />
                        {settings.cloudflare_api_token_masked && (
                            <p className="text-xs text-muted-foreground">Current token: {settings.cloudflare_api_token_masked}</p>
                        )}
                        <p className="text-xs text-muted-foreground">
                            Use a Cloudflare API token (not the Global API Key). Required permissions: Zone → SSL and
                            Certificates → Edit, and Custom Hostnames. Zone → DNS → Edit is enough for tenant subdomains
                            but custom domains will fail without Custom Hostnames.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button type="submit">Save Cloudflare settings</Button>
                        <Button type="button" variant="outline" disabled={testing} onClick={() => void testConnection()}>
                            {testing ? 'Testing…' : 'Test connection'}
                        </Button>
                    </div>
                    <p className={cn('text-xs', settings.configured ? 'text-emerald-600' : 'text-muted-foreground')}>
                        Status: {settings.configured ? 'Cloudflare configured' : 'Manual DNS verification only'}
                    </p>
                </form>
            </CardContent>
        </Card>
    );
}

export function PlatformSettingsPanel({
    section,
    platformSettings,
    generalSettings,
    smtpSettings,
    paymentSettings,
    aiSettings,
    cloudflareSettings,
    publicContent,
}: Props) {
    const [generalLogo, setGeneralLogo] = useState<File | null>(null);
    const [generalFavicon, setGeneralFavicon] = useState<File | null>(null);
    const [hideAppNameNextToLogo, setHideAppNameNextToLogo] = useState(
        generalSettings.hide_app_name_next_to_logo,
    );
    const [smtpPassword, setSmtpPassword] = useState('');
    const [stripeSecret, setStripeSecret] = useState('');
    const [stripeWebhookSecret, setStripeWebhookSecret] = useState('');
    const [testingSmtp, setTestingSmtp] = useState(false);
    const [testEmail, setTestEmail] = useState('');

    const changeSection = (value: string) => {
        router.get('/platform', { tab: 'settings', section: value }, { preserveState: true, replace: true });
    };

    const submitGeneral = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const form = event.currentTarget;
        const data = new FormData(form);

        if (generalLogo) {
            data.set('logo', generalLogo);
        }

        if (generalFavicon) {
            data.set('favicon', generalFavicon);
        }

        data.set('hide_app_name_next_to_logo', hideAppNameNextToLogo ? '1' : '0');

        router.post('/platform/settings/general', data, { forceFormData: true, preserveScroll: true });
    };

    const submitSmtp = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const form = event.currentTarget;
        const payload: Record<string, string | number | null> = {
            mail_mailer: (form.elements.namedItem('mail_mailer') as HTMLSelectElement).value,
            mail_host: (form.elements.namedItem('mail_host') as HTMLInputElement).value,
            mail_port: (form.elements.namedItem('mail_port') as HTMLInputElement).value
                ? Number((form.elements.namedItem('mail_port') as HTMLInputElement).value)
                : null,
            mail_username: (form.elements.namedItem('mail_username') as HTMLInputElement).value,
            mail_encryption: (form.elements.namedItem('mail_encryption') as HTMLSelectElement).value,
            mail_from_address: (form.elements.namedItem('mail_from_address') as HTMLInputElement).value,
            mail_from_name: (form.elements.namedItem('mail_from_name') as HTMLInputElement).value,
        };

        if (smtpPassword.trim() !== '') {
            payload.mail_password = smtpPassword;
        }

        router.post('/platform/settings/smtp', payload, { preserveScroll: true });
    };

    const submitPayment = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const form = event.currentTarget;
        const payload: Record<string, string | boolean | number | null> = {
            stripe_enabled: (form.elements.namedItem('stripe_enabled') as HTMLInputElement).checked,
            stripe_key: (form.elements.namedItem('stripe_key') as HTMLInputElement).value,
            billing_enforced: (form.elements.namedItem('billing_enforced') as HTMLInputElement).checked,
            trial_days: Number((form.elements.namedItem('trial_days') as HTMLSelectElement).value),
            trial_requires_card: (form.elements.namedItem('trial_requires_card') as HTMLInputElement).checked,
        };

        if (stripeSecret.trim() !== '') {
            payload.stripe_secret = stripeSecret;
        }

        if (stripeWebhookSecret.trim() !== '') {
            payload.stripe_webhook_secret = stripeWebhookSecret;
        }

        router.post('/platform/settings/payment', payload, { preserveScroll: true });
    };

    const testSmtp = async () => {
        if (testEmail.trim() === '') {
            toast.error('Enter a test email address.');
            return;
        }

        setTestingSmtp(true);

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const response = await fetch('/platform/settings/smtp/test', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ to: testEmail }),
            });

            const json = (await response.json()) as { ok?: boolean; message?: string };

            if (response.ok && json.ok) {
                toast.success(json.message ?? 'Test email sent.');
            } else {
                toast.error(json.message ?? 'Could not send test email.');
            }
        } finally {
            setTestingSmtp(false);
        }
    };

    return (
        <Tabs value={section} onValueChange={changeSection} className="gap-6">
            <TabsList className="h-auto flex-wrap justify-start">
                <TabsTrigger value="general">General</TabsTrigger>
                <TabsTrigger value="public">Public content</TabsTrigger>
                <TabsTrigger value="platform">Platform</TabsTrigger>
                <TabsTrigger value="dns">DNS</TabsTrigger>
                <TabsTrigger value="ai">AI</TabsTrigger>
                <TabsTrigger value="smtp">SMTP</TabsTrigger>
                <TabsTrigger value="payment">Payment</TabsTrigger>
            </TabsList>

            <TabsContent value="general">
                <Card>
                    <CardHeader>
                        <CardTitle>General settings</CardTitle>
                        <CardDescription>
                            Product name and root domain used across the platform. Works with any brand and domain.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form className="grid gap-4" onSubmit={submitGeneral}>
                            <div className="grid gap-2">
                                <Label htmlFor="app_name">App name</Label>
                                <Input
                                    id="app_name"
                                    name="app_name"
                                    defaultValue={generalSettings.app_name}
                                    placeholder="Docs"
                                    required
                                />
                                <p className="text-xs text-muted-foreground">
                                    Shown in marketing pages, emails, docs footers, and browser titles.
                                </p>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="app_domain">App domain</Label>
                                <Input
                                    id="app_domain"
                                    name="app_domain"
                                    defaultValue={generalSettings.app_domain}
                                    placeholder="docs.example.com"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Root domain for tenant subdomains (for example <code>acme.yourdomain.com</code>).
                                    Leave blank to use the <code>APP_DOMAIN</code> environment value.
                                </p>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="tagline">Tagline (optional)</Label>
                                <Input id="tagline" name="tagline" defaultValue={generalSettings.tagline ?? ''} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="support_email">Support email (optional)</Label>
                                <Input
                                    id="support_email"
                                    name="support_email"
                                    type="email"
                                    defaultValue={generalSettings.support_email ?? ''}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="logo">Logo</Label>
                                <Input
                                    id="logo"
                                    type="file"
                                    accept="image/*,.svg"
                                    onChange={(event) => setGeneralLogo(event.target.files?.[0] ?? null)}
                                />
                                <AssetPreview label="logo" url={generalSettings.logo_url} />
                            </div>
                            <label
                                className={cn(
                                    'flex items-start gap-2 text-sm',
                                    !generalSettings.logo_url && !generalLogo && 'opacity-60',
                                )}
                            >
                                <input
                                    id="hide_app_name_next_to_logo"
                                    type="checkbox"
                                    name="hide_app_name_next_to_logo"
                                    value="1"
                                    className="mt-0.5"
                                    checked={hideAppNameNextToLogo}
                                    disabled={!generalSettings.logo_url && !generalLogo}
                                    onChange={(event) => setHideAppNameNextToLogo(event.target.checked)}
                                />
                                <span>
                                    Hide app name next to logo
                                    <span className="mt-0.5 block text-xs text-muted-foreground">
                                        After a custom logo is uploaded, show only the image in marketing and auth
                                        headers. The app name stays in titles and emails.
                                    </span>
                                </span>
                            </label>
                            <div className="grid gap-2">
                                <Label htmlFor="favicon">Favicon (.ico, .png, .svg)</Label>
                                <Input
                                    id="favicon"
                                    type="file"
                                    accept="image/*,.ico,.svg"
                                    onChange={(event) => setGeneralFavicon(event.target.files?.[0] ?? null)}
                                />
                                <AssetPreview label="favicon" url={generalSettings.favicon_url} />
                            </div>
                            <Button type="submit">Save general settings</Button>
                        </form>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="public">
                <PlatformPublicContentForm settings={publicContent} />
            </TabsContent>

            <TabsContent value="platform">
                <Card>
                    <CardHeader>
                        <CardTitle>Platform settings</CardTitle>
                        <CardDescription>Runtime configuration. App domain is editable under General.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <div className="grid gap-3 md:grid-cols-2">
                            <div>
                                <p className="font-medium">App domain</p>
                                <p className="text-muted-foreground">{platformSettings.app_domain}</p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Tenant docs publish at subdomain.your-domain (configure under General).
                                </p>
                            </div>
                            <div>
                                <p className="font-medium">App URL</p>
                                <p className="text-muted-foreground">{platformSettings.app_url}</p>
                                <p className="mt-1 text-xs text-muted-foreground">From APP_URL / environment.</p>
                            </div>
                        </div>
                        <div>
                            <p className="mb-2 font-medium">Reserved subdomains</p>
                            <p className="text-muted-foreground">{platformSettings.reserved_subdomains.join(', ')}</p>
                        </div>
                        <form
                            className="flex flex-wrap items-center gap-3 border-t pt-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                router.post('/platform/settings', {
                                    maintenance_mode: (event.currentTarget.elements.namedItem('maintenance_mode') as HTMLInputElement)
                                        .checked,
                                });
                            }}
                        >
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="maintenance_mode"
                                    defaultChecked={platformSettings.maintenance_mode}
                                />
                                Maintenance mode (blocks public docs when enabled)
                            </label>
                            <Button type="submit" size="sm">
                                Save platform settings
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="dns">
                <CloudflareSettingsForm settings={cloudflareSettings} />
            </TabsContent>

            <TabsContent value="ai">
                <AiSettingsForm settings={aiSettings} />
            </TabsContent>

            <TabsContent value="smtp">
                <Card>
                    <CardHeader>
                        <CardTitle>SMTP / Email</CardTitle>
                        <CardDescription>
                            Configure outbound email for invitations, notifications, and password resets. Overrides .env
                            when host is set.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form className="grid gap-4" onSubmit={submitSmtp}>
                            <div className="grid gap-2">
                                <Label htmlFor="mail_mailer">Mail driver</Label>
                                <select
                                    id="mail_mailer"
                                    name="mail_mailer"
                                    className="rounded-md border bg-transparent px-3 py-2 text-sm"
                                    defaultValue={smtpSettings.mail_mailer}
                                >
                                    <option value="smtp">SMTP</option>
                                    <option value="log">Log (development)</option>
                                </select>
                            </div>
                            <div className="grid gap-3 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="mail_host">SMTP host</Label>
                                    <Input id="mail_host" name="mail_host" defaultValue={smtpSettings.mail_host ?? ''} placeholder="smtp.mailgun.org" />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="mail_port">Port</Label>
                                    <Input id="mail_port" name="mail_port" type="number" defaultValue={smtpSettings.mail_port ?? 587} />
                                </div>
                            </div>
                            <div className="grid gap-3 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="mail_username">Username</Label>
                                    <Input id="mail_username" name="mail_username" defaultValue={smtpSettings.mail_username ?? ''} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="mail_password">Password</Label>
                                    <Input
                                        id="mail_password"
                                        type="password"
                                        value={smtpPassword}
                                        onChange={(event) => setSmtpPassword(event.target.value)}
                                        placeholder={smtpSettings.mail_password_set ? 'Leave blank to keep current password' : ''}
                                    />
                                    {smtpSettings.mail_password_masked && (
                                        <p className="text-xs text-muted-foreground">Current: {smtpSettings.mail_password_masked}</p>
                                    )}
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="mail_encryption">Encryption</Label>
                                <select
                                    id="mail_encryption"
                                    name="mail_encryption"
                                    className="rounded-md border bg-transparent px-3 py-2 text-sm"
                                    defaultValue={smtpSettings.mail_encryption ?? 'tls'}
                                >
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="">None</option>
                                </select>
                            </div>
                            <div className="grid gap-3 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="mail_from_address">From address</Label>
                                    <Input
                                        id="mail_from_address"
                                        name="mail_from_address"
                                        type="email"
                                        defaultValue={smtpSettings.mail_from_address ?? ''}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="mail_from_name">From name</Label>
                                    <Input id="mail_from_name" name="mail_from_name" defaultValue={smtpSettings.mail_from_name ?? ''} />
                                </div>
                            </div>
                            <div className="flex flex-wrap items-end gap-2">
                                <Button type="submit">Save SMTP settings</Button>
                                <Input
                                    className="max-w-xs"
                                    type="email"
                                    placeholder="Test recipient email"
                                    value={testEmail}
                                    onChange={(event) => setTestEmail(event.target.value)}
                                />
                                <Button type="button" variant="outline" disabled={testingSmtp} onClick={() => void testSmtp()}>
                                    {testingSmtp ? 'Sending…' : 'Send test email'}
                                </Button>
                            </div>
                            <p className={cn('text-xs', smtpSettings.configured ? 'text-emerald-600' : 'text-muted-foreground')}>
                                Status: {smtpSettings.configured ? 'SMTP configured' : 'Using .env / log driver'}
                            </p>
                        </form>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="payment">
                <Card>
                    <CardHeader>
                        <CardTitle>Payment gateway (Stripe)</CardTitle>
                        <CardDescription>
                            Stripe keys for workspace billing checkout and customer portal. Overrides .env when enabled.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form className="grid gap-4" onSubmit={submitPayment}>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="stripe_enabled"
                                    defaultChecked={paymentSettings.stripe_enabled}
                                />
                                Enable Stripe billing
                            </label>
                            <label className="flex items-start gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="billing_enforced"
                                    className="mt-0.5"
                                    defaultChecked={paymentSettings.billing_enforced}
                                />
                                <span>
                                    Require an active subscription or trial to use the app
                                    <span className="mt-0.5 block text-xs text-muted-foreground">
                                        Ignored when Stripe is not configured, so workspaces are never locked out without a payment
                                        provider.
                                    </span>
                                </span>
                            </label>
                            <div className="grid gap-2">
                                <Label htmlFor="trial_days">Free trial length</Label>
                                <select
                                    id="trial_days"
                                    name="trial_days"
                                    defaultValue={String(paymentSettings.trial_days)}
                                    className="rounded-md border bg-transparent px-3 py-2 text-sm"
                                >
                                    <option value="7">7 days</option>
                                    <option value="14">14 days</option>
                                    <option value="30">30 days</option>
                                    <option value="0">1 month</option>
                                </select>
                                <p className="text-xs text-muted-foreground">
                                    New workspaces receive this trial. Paid checkout also uses the remaining trial days.
                                </p>
                            </div>
                            <label className="flex items-start gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="trial_requires_card"
                                    className="mt-0.5"
                                    defaultChecked={paymentSettings.trial_requires_card}
                                />
                                <span>
                                    Require credit card for trial
                                    <span className="mt-0.5 block text-xs text-muted-foreground">
                                        On: workspaces start a Stripe Checkout trial and must add a card. Off: the trial starts
                                        immediately without a card.
                                    </span>
                                </span>
                            </label>
                            <div className="grid gap-2">
                                <Label htmlFor="stripe_key">Publishable key</Label>
                                <Input id="stripe_key" name="stripe_key" defaultValue={paymentSettings.stripe_key ?? ''} placeholder="pk_test_..." />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="stripe_secret">Secret key</Label>
                                <Input
                                    id="stripe_secret"
                                    type="password"
                                    value={stripeSecret}
                                    onChange={(event) => setStripeSecret(event.target.value)}
                                    placeholder={paymentSettings.stripe_secret_set ? 'Leave blank to keep current secret' : 'sk_test_...'}
                                />
                                {paymentSettings.stripe_secret_masked && (
                                    <p className="text-xs text-muted-foreground">Current: {paymentSettings.stripe_secret_masked}</p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="stripe_webhook_secret">Webhook signing secret</Label>
                                <Input
                                    id="stripe_webhook_secret"
                                    type="password"
                                    value={stripeWebhookSecret}
                                    onChange={(event) => setStripeWebhookSecret(event.target.value)}
                                    placeholder={
                                        paymentSettings.stripe_webhook_secret_set
                                            ? 'Leave blank to keep current secret'
                                            : 'whsec_...'
                                    }
                                />
                                {paymentSettings.stripe_webhook_secret_masked && (
                                    <p className="text-xs text-muted-foreground">
                                        Current: {paymentSettings.stripe_webhook_secret_masked}
                                    </p>
                                )}
                            </div>
                            <Button type="submit">Save payment settings</Button>
                            <p className={cn('text-xs', paymentSettings.configured ? 'text-emerald-600' : 'text-muted-foreground')}>
                                Status: {paymentSettings.configured ? 'Stripe configured' : 'Using .env or disabled'}
                            </p>
                        </form>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>
    );
}
