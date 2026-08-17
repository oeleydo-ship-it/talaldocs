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
            className={`flex min-w-0 items-center gap-3 rounded-md transition-colors hover:text-primary lg:min-w-max ${className ?? ''}`}
            aria-label={`${name} — docs home`}
        >
            {logoUrl ? <img src={logoUrl} alt="" className="size-7 shrink-0 object-contain" /> : null}
            <div className="min-w-0 lg:min-w-max">
                <p className="truncate font-semibold lg:overflow-visible" style={{ fontFamily: headingFont }}>
                    {name}
                </p>
                {children}
            </div>
        </Link>
    );
}
