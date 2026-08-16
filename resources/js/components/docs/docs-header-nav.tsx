import { ChevronDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export type HeaderLink = { label: string; url: string };

function isExternalUrl(url: string): boolean {
    return /^https?:\/\//i.test(url);
}

function isPrimaryAction(label: string): boolean {
    return /sign\s*up|register|get\s*started|try\s*free|login|log\s*in|sign\s*in/i.test(label.trim());
}

/**
 * Center = primary menu. Right = up to 2 CTA-style links (Sign up / Register / Login, or last 1–2).
 */
export function splitHeaderLinks(links: HeaderLink[]): { center: HeaderLink[]; end: HeaderLink[] } {
    if (links.length === 0) {
        return { center: [], end: [] };
    }

    const primary = links.filter((link) => isPrimaryAction(link.label));
    const nonPrimary = links.filter((link) => !isPrimaryAction(link.label));

    if (primary.length > 0) {
        const end = primary.slice(-2);
        const overflow = primary.slice(0, Math.max(0, primary.length - 2));

        return {
            center: [...nonPrimary, ...overflow],
            end,
        };
    }

    if (links.length === 1) {
        return { center: links, end: [] };
    }

    const take = links.length >= 4 ? 2 : 1;

    return {
        center: links.slice(0, -take),
        end: links.slice(-take),
    };
}

function HeaderLinkAnchor({
    link,
    className,
    emphasis,
}: {
    link: HeaderLink;
    className?: string;
    emphasis?: boolean;
}) {
    const external = isExternalUrl(link.url);

    if (emphasis) {
        return (
            <Button asChild size="sm" className={cn('h-8 shrink-0 px-3', className)}>
                <a href={link.url} {...(external ? { target: '_blank', rel: 'noreferrer' } : {})}>
                    {link.label}
                </a>
            </Button>
        );
    }

    return (
        <a
            href={link.url}
            className={cn(
                'shrink-0 truncate text-sm text-muted-foreground transition-colors hover:text-foreground',
                className,
            )}
            {...(external ? { target: '_blank', rel: 'noreferrer' } : {})}
        >
            {link.label}
        </a>
    );
}

export function DocsHeaderNav({
    links,
    className,
    placement = 'center',
    emphasizePrimary = false,
}: {
    links: HeaderLink[];
    className?: string;
    placement?: 'center' | 'end';
    /** When true, Sign up / Register-style labels render as buttons. */
    emphasizePrimary?: boolean;
}) {
    if (links.length === 0) {
        return null;
    }

    const mobileVisible = links.slice(0, 2);
    const mobileOverflow = links.slice(2);

    return (
        <nav
            aria-label={placement === 'center' ? 'Header menu' : 'Header actions'}
            className={cn(
                'min-w-0',
                placement === 'end' && 'flex items-center gap-2 sm:gap-3',
                className,
            )}
        >
            {/* Desktop: parent templates hide the center column below lg */}
            <div
                className={cn(
                    'flex items-center',
                    placement === 'center' ? 'justify-center gap-5' : 'hidden gap-2 sm:gap-3 lg:flex',
                )}
            >
                {links.map((link) => (
                    <HeaderLinkAnchor
                        key={`${link.label}-${link.url}`}
                        link={link}
                        className={placement === 'center' ? 'max-w-[10rem]' : 'max-w-[9rem]'}
                        emphasis={emphasizePrimary && isPrimaryAction(link.label)}
                    />
                ))}
            </div>
            {placement === 'end' ? (
                <div className="flex items-center gap-2 lg:hidden">
                    {mobileVisible.map((link) => (
                        <HeaderLinkAnchor
                            key={`${link.label}-${link.url}`}
                            link={link}
                            className="max-w-[5.5rem]"
                            emphasis={emphasizePrimary && isPrimaryAction(link.label)}
                        />
                    ))}
                    {mobileOverflow.length > 0 ? (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="sm" className="h-8 gap-1 px-2 text-sm text-muted-foreground">
                                    More
                                    <ChevronDown className="size-3.5" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {mobileOverflow.map((link) => (
                                    <DropdownMenuItem key={`${link.label}-${link.url}`} asChild>
                                        <a
                                            href={link.url}
                                            {...(isExternalUrl(link.url) ? { target: '_blank', rel: 'noreferrer' } : {})}
                                        >
                                            {link.label}
                                        </a>
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ) : null}
                </div>
            ) : null}
        </nav>
    );
}
