import { MarketingBrand } from '@/components/marketing/marketing-brand';
import { useAppName, usePlatformBranding } from '@/lib/app-branding';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const branding = usePlatformBranding();
    const appName = useAppName();
    const tagline =
        branding?.tagline ??
        'Beautiful documentation for software teams — publish on your subdomain, bring your own domain, and ship faster.';

    return (
        <div className="grid min-h-svh lg:grid-cols-2">
            <div className="relative hidden flex-col justify-between overflow-hidden bg-primary p-10 text-primary-foreground lg:flex">
                <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.15),transparent_50%)]" />
                <MarketingBrand href={home()} className="relative text-primary-foreground" showName />

                <div className="relative space-y-4">
                    <blockquote className="space-y-2">
                        <p className="text-lg leading-relaxed">&ldquo;{tagline}&rdquo;</p>
                        <footer className="text-sm text-primary-foreground/70">
                            Documentation platform for modern SaaS teams
                        </footer>
                    </blockquote>
                </div>
                <p className="relative text-xs text-primary-foreground/60">
                    © {new Date().getFullYear()} {appName}
                </p>
            </div>

            <div className="flex flex-col items-center justify-center bg-muted/30 p-6 md:p-10">
                <div className="mb-8 lg:hidden">
                    <MarketingBrand href={home()} showName />
                </div>

                <div className="w-full max-w-sm">
                    <div className="rounded-xl border bg-card p-6 shadow-sm">
                        <div className="mb-6 space-y-1 text-center">
                            <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
                            {description && (
                                <p className="text-sm text-muted-foreground">{description}</p>
                            )}
                        </div>
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
