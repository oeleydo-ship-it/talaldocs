import { Link, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { MarketingBrand } from '@/components/marketing/marketing-brand';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { useAppName, usePlatformBranding } from '@/lib/app-branding';
import { cn } from '@/lib/utils';
import { login, register } from '@/routes';

const navLinks = [
    { href: '/features', label: 'Features' },
    { href: '/pricing', label: 'Pricing' },
    { href: '/examples', label: 'Examples' },
    { href: '/faq', label: 'FAQ' },
    { href: '/contact', label: 'Contact' },
];

const legalLinks = [
    { href: '/privacy', label: 'Privacy' },
    { href: '/terms', label: 'Terms' },
];

function useCurrentPath(): string {
    if (typeof window === 'undefined') {
        return '/';
    }

    return window.location.pathname;
}

function NavLink({ href, label, onNavigate }: { href: string; label: string; onNavigate?: () => void }) {
    const active = useCurrentPath() === href;

    return (
        <Link
            href={href}
            onClick={onNavigate}
            className={cn(
                'font-medium transition hover:text-foreground',
                active ? 'text-foreground' : 'text-muted-foreground',
            )}
        >
            {label}
        </Link>
    );
}

export function MarketingHeader() {
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);

    return (
        <header className="sticky top-0 z-40 border-b bg-background/80 backdrop-blur-md">
            <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <MarketingBrand />

                <nav className="hidden items-center gap-6 text-sm md:flex">
                    {navLinks.map((link) => (
                        <NavLink key={link.href} href={link.href} label={link.label} />
                    ))}
                </nav>

                <div className="flex items-center gap-2">
                    {auth.user ? (
                        <Button asChild size="sm">
                            <Link href="/dashboard">Dashboard</Link>
                        </Button>
                    ) : (
                        <>
                            <Button asChild variant="ghost" size="sm" className="hidden sm:inline-flex">
                                <Link href={login()}>Log in</Link>
                            </Button>
                            <Button asChild size="sm">
                                <Link href={register()}>Get started</Link>
                            </Button>
                        </>
                    )}

                    <Sheet open={open} onOpenChange={setOpen}>
                        <SheetTrigger asChild>
                            <Button variant="outline" size="icon" className="md:hidden" aria-label="Open menu">
                                <Menu className="size-4" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="right" className="w-full sm:max-w-xs">
                            <SheetHeader>
                                <SheetTitle>Menu</SheetTitle>
                            </SheetHeader>
                            <nav className="flex flex-col gap-4 px-4 text-sm">
                                {navLinks.map((link) => (
                                    <NavLink
                                        key={link.href}
                                        href={link.href}
                                        label={link.label}
                                        onNavigate={() => setOpen(false)}
                                    />
                                ))}
                                {!auth.user && (
                                    <>
                                        <NavLink href={login()} label="Log in" onNavigate={() => setOpen(false)} />
                                        <NavLink href={register()} label="Create workspace" onNavigate={() => setOpen(false)} />
                                    </>
                                )}
                            </nav>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </header>
    );
}

export function MarketingFooter() {
    const branding = usePlatformBranding();
    const appName = useAppName();
    const tagline =
        branding?.tagline ??
        `Beautiful documentation for software teams. Publish on your subdomain, bring your own domain, and ship faster with ${appName}.`;

    return (
        <footer className="border-t bg-muted/30">
            <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-4">
                <div className="md:col-span-2">
                    <MarketingBrand showName className="text-base" />
                    <p className="mt-3 max-w-md text-sm leading-relaxed text-muted-foreground">{tagline}</p>
                </div>
                <div>
                    <p className="text-sm font-medium">Product</p>
                    <div className="mt-3 flex flex-col gap-2 text-sm text-muted-foreground">
                        {navLinks.map((link) => (
                            <Link key={link.href} href={link.href} className="hover:text-foreground">
                                {link.label}
                            </Link>
                        ))}
                    </div>
                </div>
                <div>
                    <p className="text-sm font-medium">Legal & account</p>
                    <div className="mt-3 flex flex-col gap-2 text-sm text-muted-foreground">
                        {legalLinks.map((link) => (
                            <Link key={link.href} href={link.href} className="hover:text-foreground">
                                {link.label}
                            </Link>
                        ))}
                        <Link href={login()} className="hover:text-foreground">
                            Log in
                        </Link>
                        <Link href={register()} className="hover:text-foreground">
                            Create workspace
                        </Link>
                    </div>
                </div>
            </div>
            <div className="border-t px-4 py-4 text-center text-xs text-muted-foreground sm:px-6">
                © {new Date().getFullYear()} {appName}. Built for teams who care about docs.
            </div>
        </footer>
    );
}

export default function MarketingLayout({ children }: { children: ReactNode }) {
    return (
        <div className="min-h-screen bg-background text-foreground">
            <MarketingHeader />
            <main>{children}</main>
            <MarketingFooter />
        </div>
    );
}
