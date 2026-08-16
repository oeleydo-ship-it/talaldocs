import { Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import {
    Activity,
    CreditCard,
    Flag,
    Globe,
    LayoutDashboard,
    Settings,
    Shield,
    Users,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const tabs = [
    { id: 'overview', label: 'Overview', icon: LayoutDashboard },
    { id: 'users', label: 'Users', icon: Users },
    { id: 'workspaces', label: 'Workspaces', icon: Shield },
    { id: 'plans', label: 'Plans', icon: CreditCard },
    { id: 'domains', label: 'Domains', icon: Globe },
    { id: 'reports', label: 'Reports', icon: Flag },
    { id: 'health', label: 'System', icon: Activity },
    { id: 'settings', label: 'Settings', icon: Settings },
];

export default function PlatformLayout({ children }: { children: ReactNode }) {
    const { url } = usePage();
    const { isImpersonating } = usePage<{ isImpersonating?: boolean }>().props;
    const currentTab = new URL(url, window.location.origin).searchParams.get('tab') ?? 'overview';

    return (
        <div className="min-h-screen bg-muted/30">
            {isImpersonating && (
                <div className="flex items-center justify-between gap-3 border-b bg-amber-50 px-4 py-2 text-sm text-amber-950 dark:bg-amber-950/40 dark:text-amber-100">
                    <span>You are impersonating a user.</span>
                    <Button size="sm" variant="outline" onClick={() => router.post('/platform/impersonation/stop')}>
                        Exit impersonation
                    </Button>
                </div>
            )}
            <div className="border-b bg-background shadow-sm">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-5">
                    <div>
                        <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            Platform
                        </p>
                        <h1 className="text-xl font-semibold tracking-tight">Super Admin</h1>
                    </div>
                    <Button asChild variant="outline" size="sm">
                        <Link href="/dashboard">Back to app</Link>
                    </Button>
                </div>
                <div className="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-6 pb-3">
                    {tabs.map((tab) => {
                        const Icon = tab.icon;
                        const active = currentTab === tab.id;

                        return (
                            <Link
                                key={tab.id}
                                href={tab.id === 'settings' ? '/platform?tab=settings&section=general' : `/platform?tab=${tab.id}`}
                                className={cn(
                                    'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition',
                                    active
                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                )}
                            >
                                <Icon className="size-4" />
                                {tab.label}
                            </Link>
                        );
                    })}
                </div>
            </div>
            <div className="mx-auto max-w-7xl px-6 py-6">{children}</div>
        </div>
    );
}
