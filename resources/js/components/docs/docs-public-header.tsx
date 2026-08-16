import type { ReactNode } from 'react';
import { DocsHeaderBrand } from '@/components/docs/docs-header-brand';
import { DocsHeaderNav } from '@/components/docs/docs-header-nav';
import { cn } from '@/lib/utils';
import type { DocsTemplateName, HeaderLink } from '@/pages/docs/types';

type BrandProject = {
    name: string;
    logo_url: string | null;
    heading_font: string;
};

type Props = {
    template: DocsTemplateName;
    brandHref: string;
    project: BrandProject;
    brandChildren?: ReactNode;
    /** Mobile sheet trigger / other leading controls before the brand. */
    leading?: ReactNode;
    centerLinks: HeaderLink[];
    endLinks: HeaderLink[];
    /** Search, Ask, layout, theme, etc. — rendered before end CTAs. */
    actions?: ReactNode;
    /** Override inner max-width / padding (e.g. classic wide layout). */
    innerClassName?: string;
    className?: string;
};

/**
 * Shared top chrome for classic/gitbook docs and public hub pages
 * (directory, announcements, changelog).
 */
export function DocsPublicHeader({
    template,
    brandHref,
    project,
    brandChildren,
    leading,
    centerLinks,
    endLinks,
    actions,
    innerClassName,
    className,
}: Props) {
    const isGitbook = template === 'gitbook';

    return (
        <header
            className={cn(
                'sticky top-0 z-30 border-b backdrop-blur',
                isGitbook ? 'bg-background/95' : 'bg-background/90',
                className,
            )}
        >
            <div
                className={cn(
                    'grid h-14 grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3 px-4',
                    isGitbook ? 'w-full lg:px-6' : 'mx-auto max-w-6xl',
                    innerClassName,
                )}
            >
                <div className="flex min-w-0 items-center justify-start gap-3">
                    {leading}
                    <DocsHeaderBrand
                        href={brandHref}
                        name={project.name}
                        logoUrl={project.logo_url}
                        headingFont={project.heading_font}
                    >
                        {brandChildren}
                    </DocsHeaderBrand>
                </div>
                <div className="hidden min-w-0 justify-center px-2 lg:flex">
                    {centerLinks.length > 0 ? (
                        <DocsHeaderNav links={centerLinks} placement="center" />
                    ) : null}
                </div>
                <div className="flex min-w-0 items-center justify-end gap-2">
                    {actions}
                    <DocsHeaderNav links={endLinks} placement="end" emphasizePrimary />
                </div>
            </div>
        </header>
    );
}
