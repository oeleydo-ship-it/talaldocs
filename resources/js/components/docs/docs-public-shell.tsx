import { Head } from '@inertiajs/react';
import { Moon, Search, Sparkles, Sun } from 'lucide-react';
import type { ReactNode } from 'react';
import { DocsAskModal } from '@/components/docs/docs-ask-modal';
import { splitHeaderLinks } from '@/components/docs/docs-header-nav';
import { DocsPublicHeader } from '@/components/docs/docs-public-header';
import { DocsSearchModal } from '@/components/docs/docs-search-modal';
import { DocsShellFooter } from '@/components/docs/docs-shell-footer';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useAppearance } from '@/hooks/use-appearance';
import { useDocsAsk } from '@/hooks/use-docs-ask';
import { useDocsSearch } from '@/hooks/use-docs-search';
import { docsThemeStyle, googleFontsHref } from '@/lib/docs-branding';
import { cn } from '@/lib/utils';
import type { DocsShellProps } from '@/pages/docs/types';

type Props = DocsShellProps & {
    children: ReactNode;
    title?: string;
    /** Narrow centered column for announcements/changelog reading. */
    narrow?: boolean;
};

export function DocsPublicShell({
    project,
    template = 'classic',
    headerLinks,
    docsHomeUrl,
    directoryUrl,
    announcementsUrl,
    changelogUrl,
    docsPagesBasePath,
    searchUrl,
    askUrl,
    aiAskEnabled = false,
    showPoweredBy,
    children,
    title,
    narrow = false,
}: Props) {
    const isGitbook = template === 'gitbook';
    const { center: centerLinks, end: endLinks } = splitHeaderLinks(headerLinks);
    const theme = docsThemeStyle(project);
    const fontsHref = googleFontsHref([project.font_family, project.heading_font]);
    const { appearance, updateAppearance } = useAppearance();
    const search = useDocsSearch(searchUrl);
    const ask = useDocsAsk(askUrl, aiAskEnabled);

    const cycleTheme = () => {
        updateAppearance(appearance === 'dark' ? 'light' : appearance === 'light' ? 'system' : 'dark');
    };

    return (
        <>
            <Head>
                {fontsHref ? <link rel="stylesheet" href={fontsHref} /> : null}
                {project.favicon_url ? <link rel="icon" href={project.favicon_url} /> : null}
            </Head>
            <div
                className={cn(
                    'flex min-h-screen flex-col bg-background text-foreground',
                    isGitbook && 'docs-template-gitbook',
                )}
                style={theme}
            >
                <DocsPublicHeader
                    template={template}
                    brandHref={docsHomeUrl}
                    project={project}
                    centerLinks={centerLinks}
                    endLinks={endLinks}
                    actions={
                        <>
                            {isGitbook ? (
                                <>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="hidden gap-2 sm:inline-flex"
                                        onClick={search.openSearch}
                                    >
                                        <Search className="size-4" />
                                        <span className="text-muted-foreground">Search</span>
                                        <kbd className="hidden rounded border bg-muted px-1.5 py-0.5 font-mono text-[10px] md:inline">
                                            ⌘K
                                        </kbd>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        className="sm:hidden"
                                        onClick={search.openSearch}
                                    >
                                        <Search className="size-4" />
                                    </Button>
                                </>
                            ) : (
                                <>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="hidden sm:inline-flex"
                                        onClick={search.openSearch}
                                    >
                                        <Search className="size-4" />
                                        Search
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        className="sm:hidden"
                                        onClick={search.openSearch}
                                    >
                                        <Search className="size-4" />
                                    </Button>
                                </>
                            )}
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="hidden gap-1.5 md:inline-flex"
                                        onClick={ask.openAsk}
                                    >
                                        <Sparkles className="size-3.5" />
                                        Ask
                                    </Button>
                                </TooltipTrigger>
                                {!aiAskEnabled && (
                                    <TooltipContent>
                                        AI Ask requires the platform administrator to enable AI in Platform → Settings.
                                    </TooltipContent>
                                )}
                            </Tooltip>
                            <Button variant="outline" size="icon" onClick={cycleTheme} aria-label="Toggle theme">
                                {appearance === 'dark' ? <Sun className="size-4" /> : <Moon className="size-4" />}
                            </Button>
                        </>
                    }
                />

                <main
                    className={cn(
                        'w-full flex-1 px-4',
                        narrow ? 'py-8 sm:py-10' : 'py-10',
                        narrow
                            ? 'mx-auto max-w-3xl lg:px-8'
                            : isGitbook
                              ? 'mx-auto max-w-7xl lg:px-10'
                              : 'mx-auto max-w-6xl',
                    )}
                >
                    {title ? (
                        <h1
                            className={cn(
                                'tracking-tight',
                                narrow ? 'mb-6' : 'mb-8',
                                isGitbook ? 'text-4xl font-bold' : 'text-3xl font-semibold',
                            )}
                            style={{
                                fontFamily: project.heading_font,
                                color: isGitbook ? undefined : project.primary_color,
                            }}
                        >
                            {title}
                        </h1>
                    ) : null}
                    {children}
                </main>

                <DocsShellFooter
                    project={project}
                    headerLinks={headerLinks}
                    docsHomeUrl={docsHomeUrl}
                    directoryUrl={directoryUrl}
                    announcementsUrl={announcementsUrl}
                    changelogUrl={changelogUrl}
                    showPoweredBy={showPoweredBy}
                />
            </div>
            <DocsSearchModal
                open={search.open}
                query={search.query}
                results={search.results}
                loading={search.loading}
                basePath={docsPagesBasePath}
                onQueryChange={search.search}
                onClose={search.closeSearch}
            />
            <DocsAskModal
                open={ask.open}
                enabled={ask.enabled}
                question={ask.question}
                loading={ask.loading}
                result={ask.result}
                error={ask.error}
                basePath={docsPagesBasePath}
                onQuestionChange={ask.setQuestion}
                onSubmit={() => void ask.submit()}
                onClose={ask.closeAsk}
            />
        </>
    );
}
