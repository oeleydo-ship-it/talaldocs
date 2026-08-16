import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { docLinkOptions } from '@/pages/docs/types';

type Props = {
    href: string;
    name: string;
    logoUrl: string | null;
    headingFont: string;
    children?: ReactNode;
    className?: string;
};

export function DocsHeaderBrand({ href, name, logoUrl, headingFont, children, className }: Props) {
    return (
        <Link
            href={href}
            {...docLinkOptions}
            className={`flex min-w-0 items-center gap-3 rounded-md transition-colors hover:text-primary ${className ?? ''}`}
            aria-label={`${name} — docs home`}
        >
            {logoUrl ? <img src={logoUrl} alt="" className="size-7 shrink-0 object-contain" /> : null}
            <div className="min-w-0">
                <p className="truncate font-semibold" style={{ fontFamily: headingFont }}>
                    {name}
                </p>
                {children}
            </div>
        </Link>
    );
}
