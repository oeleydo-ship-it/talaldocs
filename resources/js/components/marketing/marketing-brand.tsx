import { Link } from '@inertiajs/react';
import { shouldShowAppNameNextToLogo, useAppName, usePlatformBranding } from '@/lib/app-branding';
import { cn } from '@/lib/utils';

type Props = {
    className?: string;
    href?: string;
    showName?: boolean;
    iconClassName?: string;
};

export function MarketingBrand({
    className,
    href = '/',
    showName = true,
    iconClassName,
}: Props) {
    const branding = usePlatformBranding();
    const name = useAppName();
    const initial = name.trim().charAt(0).toUpperCase() || 'D';
    const showWordmark = showName && shouldShowAppNameNextToLogo(branding);

    return (
        <Link
            href={href}
            aria-label={showWordmark ? undefined : name}
            className={cn('flex items-center gap-2.5 text-lg font-semibold tracking-tight', className)}
        >
            {branding?.logo_url ? (
                <img src={branding.logo_url} alt="" className={cn('h-8 w-auto object-contain', iconClassName)} />
            ) : (
                <span
                    className={cn(
                        'inline-flex size-8 items-center justify-center rounded-lg bg-primary text-sm font-bold text-primary-foreground shadow-sm',
                        iconClassName,
                    )}
                >
                    {initial}
                </span>
            )}
            {showWordmark ? <span>{name}</span> : <span className="sr-only">{name}</span>}
        </Link>
    );
}
