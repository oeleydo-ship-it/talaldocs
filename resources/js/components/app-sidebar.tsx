import { Link, usePage } from '@inertiajs/react';
import {
    Blocks,
    CreditCard,
    FolderOpen,
    LayoutGrid,
    ScrollText,
    Settings,
    Shield,
    Users,
    BarChart3,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { WorkspaceSwitcher } from '@/components/workspace-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const contentNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Projects',
        href: '/projects',
        icon: FolderOpen,
    },
    {
        title: 'Blocks',
        href: '/blocks',
        icon: Blocks,
    },
];

const workspaceNavItems: NavItem[] = [
    {
        title: 'Members',
        href: '/members',
        icon: Users,
    },
    {
        title: 'Analytics',
        href: '/analytics',
        icon: BarChart3,
    },
    {
        title: 'Billing',
        href: '/billing',
        icon: CreditCard,
    },
    {
        title: 'Audit log',
        href: '/audit',
        icon: ScrollText,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Platform admin',
        href: '/platform',
        icon: Shield,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Workspace settings',
        href: '/settings/profile',
        icon: Settings,
    },
];

export function AppSidebar() {
    const { isPlatformAdmin } = usePage<{ isPlatformAdmin?: boolean }>().props;

    const navGroups = [
        { label: 'Content', items: contentNavItems },
        { label: 'Workspace', items: workspaceNavItems },
        ...(isPlatformAdmin ? [{ label: 'Admin', items: adminNavItems }] : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="border-b border-sidebar-border/50 pb-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem className="px-2 group-data-[collapsible=icon]:hidden">
                        <WorkspaceSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-0 py-2">
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border/50 pt-2">
                <NavFooter items={footerNavItems} className="mt-auto" />
                <SidebarSeparator className="mx-2 group-data-[collapsible=icon]:hidden" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
