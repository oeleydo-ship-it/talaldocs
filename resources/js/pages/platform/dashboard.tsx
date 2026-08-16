import { Head, router } from '@inertiajs/react';
import { PlatformSettingsPanel } from '@/components/platform/platform-settings-panel';
import { PlatformPlanEditor } from '@/components/platform/platform-plan-editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { StatCard } from '@/components/stat-card';

type Stats = {
    users: number;
    workspaces: number;
    projects: number;
    domains: number;
    subscriptions: number;
    open_reports: number;
    failed_domains: number;
    failed_jobs: number;
    pending_jobs: number;
    ai_configured: boolean;
    ai_generations_total: number;
    ai_generations_this_month: number;
};

type PlatformSettings = {
    app_domain: string;
    app_url: string;
    reserved_subdomains: string[];
    maintenance_mode: boolean;
    mail_configured: boolean;
    stripe_configured: boolean;
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

type Props = {
    tab: string;
    settingsSection: string;
    search: string;
    stats: Stats;
    platformSettings: PlatformSettings;
    generalSettings: {
        app_name: string;
        app_domain: string;
        support_email: string | null;
        tagline: string | null;
        logo_url: string | null;
        favicon_url: string | null;
    };
    smtpSettings: {
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
    paymentSettings: {
        stripe_enabled: boolean;
        stripe_key: string | null;
        stripe_secret_set: boolean;
        stripe_secret_masked: string | null;
        stripe_webhook_secret_set: boolean;
        stripe_webhook_secret_masked: string | null;
        configured: boolean;
    };
    aiSettings: AiSettings;
    cloudflareSettings: {
        cloudflare_enabled: boolean;
        cloudflare_zone_id: string | null;
        cloudflare_account_id: string | null;
        cloudflare_fallback_origin: string | null;
        cloudflare_auto_subdomains: boolean;
        cloudflare_api_token_set: boolean;
        cloudflare_api_token_masked: string | null;
        configured: boolean;
        app_domain: string;
    };
    recentActivity: { id: number; action: string; admin: string | null; metadata: Record<string, unknown> | null; created_at: string | null }[];
    workspaces: { id: number; name: string; slug: string; plan: string | null; plan_id: number | null; projects_count: number; suspended_at: string | null; stripe_id: boolean; created_at: string | null }[];
    users: { id: number; name: string; email: string; is_platform_admin: boolean; suspended_at: string | null; email_verified_at: string | null; created_at: string | null; workspaces_count: number }[];
    plans: {
        id: number;
        name: string;
        slug: string;
        price_cents: number;
        stripe_price_id: string | null;
        limits: Record<string, number | null>;
        features: Record<string, boolean>;
        is_active: boolean;
    }[];
    allDomains: { id: number; hostname: string; status: string; is_primary: boolean; project: string | null; project_slug: string | null; verified_at: string | null; error_message: string | null; last_checked_at: string | null }[];
    failedDomains: { id: number; hostname: string; project: string | null; error_message: string | null; last_checked_at: string | null }[];
    reports: { id: number; reason: string; details: string | null; status: string; project: string | null; page: string | null; created_at: string | null }[];
};

function PlatformStatCard({ title, value }: { title: string; value: number }) {
    return <StatCard title={title} value={value} />;
}

function domainStatusBadge(status: string, verifiedAt: string | null) {
    if (status === 'active') {
        return (
            <Badge variant="default">
                Active · SSL ready
            </Badge>
        );
    }

    if (status === 'failed') {
        return <Badge variant="destructive">Failed</Badge>;
    }

    if (status === 'verifying') {
        return <Badge variant="secondary">Verifying</Badge>;
    }

    return <Badge variant="outline">{status}{verifiedAt ? '' : ' · pending DNS'}</Badge>;
}

export default function PlatformDashboard({
    tab,
    settingsSection,
    search,
    stats,
    platformSettings,
    generalSettings,
    smtpSettings,
    paymentSettings,
    aiSettings,
    cloudflareSettings,
    recentActivity,
    workspaces,
    users,
    plans,
    allDomains,
    failedDomains,
    reports,
}: Props) {
    const submitSearch = (value: string) => {
        router.get('/platform', { tab, q: value }, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Platform admin" />
            {tab === 'overview' && (
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-3 xl:grid-cols-5">
                        <PlatformStatCard title="Users" value={stats.users} />
                        <PlatformStatCard title="Workspaces" value={stats.workspaces} />
                        <PlatformStatCard title="Projects" value={stats.projects} />
                        <PlatformStatCard title="Domains" value={stats.domains} />
                        <PlatformStatCard title="Subscriptions" value={stats.subscriptions} />
                    </div>
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Recent platform activity</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                {recentActivity.length === 0 && <p className="text-muted-foreground">No platform events yet.</p>}
                                {recentActivity.map((item) => (
                                    <div key={item.id} className="rounded-md border p-3">
                                        <p className="font-medium">{item.action}</p>
                                        <p className="text-muted-foreground">
                                            {item.admin ?? 'System'} · {item.created_at ? new Date(item.created_at).toLocaleString() : ''}
                                        </p>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>AI generation</CardTitle>
                                <CardDescription>Documentation import usage</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <p>
                                    Provider configured:{' '}
                                    <Badge variant={stats.ai_configured ? 'default' : 'secondary'}>
                                        {stats.ai_configured ? 'Yes' : 'No'}
                                    </Badge>
                                </p>
                                <p>Total generations: {stats.ai_generations_total}</p>
                                <p>This month: {stats.ai_generations_this_month}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Alerts</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <p>Open reports: {stats.open_reports}</p>
                                <p>Failed domain verifications: {stats.failed_domains}</p>
                                <p>Pending queue jobs: {stats.pending_jobs}</p>
                                <p>Failed jobs: {stats.failed_jobs}</p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            )}

            {tab === 'users' && (
                <Card>
                    <CardHeader>
                        <CardTitle>Users</CardTitle>
                        <Input defaultValue={search} placeholder="Search users..." onChange={(event) => submitSearch(event.target.value)} />
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {users.map((user) => (
                            <div key={user.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 text-sm">
                                <div>
                                    <p className="font-medium">
                                        {user.name} · {user.email}
                                        {user.is_platform_admin && <Badge className="ml-2">Admin</Badge>}
                                        {user.suspended_at && <Badge variant="destructive" className="ml-2">Suspended</Badge>}
                                        {!user.email_verified_at && <Badge variant="outline" className="ml-2">Unverified</Badge>}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {user.workspaces_count} workspace(s)
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {!user.email_verified_at && (
                                        <Button size="sm" variant="outline" onClick={() => router.post(`/platform/users/${user.id}/verify-email`)}>
                                            Verify email
                                        </Button>
                                    )}
                                    <Button size="sm" variant="outline" onClick={() => router.post(`/platform/users/${user.id}/admin`)}>
                                        Toggle admin
                                    </Button>
                                    {user.suspended_at ? (
                                        <Button size="sm" variant="outline" onClick={() => router.post(`/platform/users/${user.id}/restore`)}>
                                            Restore
                                        </Button>
                                    ) : (
                                        <Button size="sm" variant="outline" onClick={() => router.post(`/platform/users/${user.id}/suspend`)}>
                                            Suspend
                                        </Button>
                                    )}
                                    {!user.is_platform_admin && (
                                        <Button size="sm" onClick={() => router.post(`/platform/users/${user.id}/impersonate`)}>
                                            Impersonate
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}

            {tab === 'workspaces' && (
                <Card>
                    <CardHeader>
                        <CardTitle>Workspaces</CardTitle>
                        <Input defaultValue={search} placeholder="Search workspaces..." onChange={(event) => submitSearch(event.target.value)} />
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {workspaces.map((workspace) => (
                            <div key={workspace.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 text-sm">
                                <div>
                                    <p className="font-medium">
                                        {workspace.name} · {workspace.plan}
                                        {workspace.suspended_at && <Badge variant="destructive" className="ml-2">Suspended</Badge>}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {workspace.slug} · {workspace.projects_count} project(s)
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <select
                                        className="h-9 rounded-md border bg-transparent px-3"
                                        defaultValue={workspace.plan_id ?? ''}
                                        onChange={(event) => {
                                            if (event.target.value) {
                                                router.post(`/platform/workspaces/${workspace.id}/plan`, { plan_id: event.target.value });
                                            }
                                        }}
                                    >
                                        <option value="">Change plan</option>
                                        {plans.map((plan) => (
                                            <option key={plan.id} value={plan.id}>
                                                {plan.name}
                                            </option>
                                        ))}
                                    </select>
                                    {workspace.suspended_at ? (
                                        <Button size="sm" variant="outline" onClick={() => router.post(`/platform/workspaces/${workspace.id}/restore`)}>
                                            Restore
                                        </Button>
                                    ) : (
                                        <Button size="sm" variant="outline" onClick={() => router.post(`/platform/workspaces/${workspace.id}/suspend`)}>
                                            Suspend
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}

            {tab === 'plans' && (
                <div className="space-y-4">
                    {!platformSettings.stripe_configured && (
                        <Card className="border-amber-200 bg-amber-50/50 dark:border-amber-900 dark:bg-amber-950/20">
                            <CardContent className="py-4 text-sm text-muted-foreground">
                                Configure Stripe in{' '}
                                <a href="/platform?tab=settings&section=payment" className="font-medium text-foreground underline">
                                    Platform → Settings → Payment
                                </a>{' '}
                                so paid plans route through Stripe Checkout.
                            </CardContent>
                        </Card>
                    )}
                    <div className="grid gap-4 lg:grid-cols-2">
                        {plans.map((plan) => (
                            <PlatformPlanEditor
                                key={plan.id}
                                plan={plan}
                                stripeConfigured={platformSettings.stripe_configured}
                            />
                        ))}
                    </div>
                </div>
            )}

            {tab === 'domains' && (
                <div className="space-y-4">
                    {failedDomains.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Failed verifications</CardTitle>
                                <CardDescription>Domains that did not pass TXT/CNAME checks.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                {failedDomains.map((domain) => (
                                    <div key={domain.id} className="rounded-lg border p-3">
                                        <p className="font-medium">{domain.hostname}</p>
                                        <p className="text-muted-foreground">{domain.project}</p>
                                        <p className="text-destructive">{domain.error_message}</p>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    )}
                    <Card>
                        <CardHeader>
                            <CardTitle>All custom domains</CardTitle>
                            <CardDescription>Retry verification or disconnect domains platform-wide.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            {allDomains.length === 0 && <p className="text-muted-foreground">No custom domains yet.</p>}
                            {allDomains.map((domain) => (
                                <div key={domain.id} className="flex flex-wrap items-start justify-between gap-2 rounded-lg border p-3">
                                    <div>
                                        <p className="font-medium">
                                            {domain.hostname}
                                            {domain.is_primary && <Badge className="ml-2">Primary</Badge>}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {domain.project} ({domain.project_slug})
                                        </p>
                                        <div className="mt-1">{domainStatusBadge(domain.status, domain.verified_at)}</div>
                                        {domain.error_message && <p className="mt-1 text-destructive">{domain.error_message}</p>}
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Button size="sm" variant="outline" onClick={() => router.post(`/platform/domains/${domain.id}/retry`)}>
                                            Retry verify
                                        </Button>
                                        <Button size="sm" variant="ghost" onClick={() => router.delete(`/platform/domains/${domain.id}`)}>
                                            Disconnect
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            )}

            {tab === 'reports' && (
                <Card>
                    <CardHeader>
                        <CardTitle>Content moderation</CardTitle>
                        <CardDescription>Review reported or flagged documentation pages.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        {reports.length === 0 && <p className="text-muted-foreground">No reports yet.</p>}
                        {reports.map((report) => (
                            <div key={report.id} className="flex flex-wrap items-start justify-between gap-2 rounded-lg border p-3">
                                <div>
                                    <p className="font-medium">{report.reason}</p>
                                    <p className="text-muted-foreground">
                                        {report.project} · {report.page}
                                    </p>
                                    <p>{report.details}</p>
                                    <Badge variant="outline">{report.status}</Badge>
                                </div>
                                {report.status === 'open' && (
                                    <div className="flex gap-2">
                                        <Button size="sm" onClick={() => router.post(`/platform/reports/${report.id}/resolve`, { status: 'resolved' })}>
                                            Resolve
                                        </Button>
                                        <Button size="sm" variant="outline" onClick={() => router.post(`/platform/reports/${report.id}/resolve`, { status: 'dismissed' })}>
                                            Dismiss
                                        </Button>
                                    </div>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}

            {tab === 'health' && (
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>System</CardTitle>
                            <CardDescription>Queue status and cache controls.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <p>Pending jobs: {stats.pending_jobs}</p>
                            <p>Failed jobs: {stats.failed_jobs}</p>
                            <p>Mail configured: {platformSettings.mail_configured ? 'Yes' : 'No (using log driver)'}</p>
                            <p>Stripe configured: {platformSettings.stripe_configured ? 'Yes' : 'No'}</p>
                            <Button size="sm" variant="outline" onClick={() => router.post('/platform/system/clear-cache')}>
                                Clear application cache
                            </Button>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent platform events</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {recentActivity.slice(0, 6).map((item) => (
                                <p key={item.id}>
                                    {item.action} · {item.admin}
                                </p>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            )}

            {tab === 'settings' && (
                <PlatformSettingsPanel
                    section={settingsSection}
                    platformSettings={platformSettings}
                    generalSettings={generalSettings}
                    smtpSettings={smtpSettings}
                    paymentSettings={paymentSettings}
                    aiSettings={aiSettings}
                    cloudflareSettings={cloudflareSettings}
                />
            )}
        </>
    );
}
