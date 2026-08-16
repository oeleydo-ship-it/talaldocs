import { Link } from '@inertiajs/react';
import { useAppName } from '@/lib/app-branding';
import type { HeaderLink } from '@/pages/docs/types';

export type DocsFooterProps = {
    projectName: string;
    logoUrl?: string | null;
    headingFont?: string;
    docsHomeUrl: string;
    directoryUrl: string;
    announcementsUrl: string;
    changelogUrl: string;
    headerLinks?: HeaderLink[];
    showPoweredBy?: boolean;
};

export function DocsFooter({
    projectName,
    logoUrl,
    headingFont,
    docsHomeUrl,
    directoryUrl,
    announcementsUrl,
    changelogUrl,
    headerLinks = [],
    showPoweredBy = true,
}: DocsFooterProps) {
    const productLinks = headerLinks.filter((link) => link.label.trim() !== '' && link.url.trim() !== '');
    const appName = useAppName();

    return (
        <footer className="mt-auto border-t bg-muted/20">
            <div className="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
                <div className="space-y-3 sm:col-span-2 lg:col-span-1">
                    <Link href={docsHomeUrl} className="inline-flex items-center gap-2">
                        {logoUrl ? (
                            <img src={logoUrl} alt="" className="h-7 w-auto object-contain" />
                        ) : null}
                        <span className="text-base font-semibold tracking-tight" style={{ fontFamily: headingFont }}>
                            {projectName}
                        </span>
                    </Link>
                    <p className="max-w-xs text-sm text-muted-foreground">
                        Documentation, announcements, and product updates in one place.
                    </p>
                </div>

                <div>
                    <p className="mb-3 text-sm font-semibold">Documentation</p>
                    <ul className="space-y-2 text-sm text-muted-foreground">
                        <li>
                            <Link href={docsHomeUrl} className="hover:text-foreground">
                                Docs home
                            </Link>
                        </li>
                        <li>
                            <Link href={directoryUrl} className="hover:text-foreground">
                                Directory
                            </Link>
                        </li>
                    </ul>
                </div>

                <div>
                    <p className="mb-3 text-sm font-semibold">Updates</p>
                    <ul className="space-y-2 text-sm text-muted-foreground">
                        <li>
                            <Link href={announcementsUrl} className="hover:text-foreground">
                                Announcements
                            </Link>
                        </li>
                        <li>
                            <Link href={changelogUrl} className="hover:text-foreground">
                                Changelog
                            </Link>
                        </li>
                    </ul>
                </div>

                <div>
                    <p className="mb-3 text-sm font-semibold">{productLinks.length > 0 ? 'Links' : 'Directory'}</p>
                    <ul className="space-y-2 text-sm text-muted-foreground">
                        {productLinks.length > 0 ? (
                            productLinks.map((link) => (
                                <li key={`${link.label}-${link.url}`}>
                                    <a
                                        href={link.url}
                                        className="hover:text-foreground"
                                        {...(link.url.startsWith('http')
                                            ? { target: '_blank', rel: 'noreferrer' }
                                            : {})}
                                    >
                                        {link.label}
                                    </a>
                                </li>
                            ))
                        ) : (
                            <li>
                                <Link href={directoryUrl} className="hover:text-foreground">
                                    Browse directory
                                </Link>
                            </li>
                        )}
                    </ul>
                </div>
            </div>

            <div className="border-t">
                <div className="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-4 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                    <p>
                        © {new Date().getFullYear()} {projectName}. All rights reserved.
                    </p>
                    {showPoweredBy ? (
                        <p>
                            Powered by{' '}
                            <a href="/" className="font-medium hover:text-foreground">
                                {appName}
                            </a>
                        </p>
                    ) : null}
                </div>
            </div>
        </footer>
    );
}
