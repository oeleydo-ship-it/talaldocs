'use no memo';

import { Link, router } from '@inertiajs/react';
import { ExternalLink, Maximize2, Menu, Minimize2, Moon, Search, Sparkles, Sun } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { CopyButton } from '@/components/copy-button';
import { DocsAskModal } from '@/components/docs/docs-ask-modal';
import { DocsFeedbackWidget } from '@/components/docs/docs-feedback-widget';
import { DocsHeaderBrand } from '@/components/docs/docs-header-brand';
import { splitHeaderLinks } from '@/components/docs/docs-header-nav';
import { DocsHead } from '@/components/docs/docs-head';
import { DocsPublicHeader } from '@/components/docs/docs-public-header';
import { DocsSearchModal } from '@/components/docs/docs-search-modal';
import { DocsShellFooter } from '@/components/docs/docs-shell-footer';
import { DocsSidebarNav } from '@/components/docs/docs-sidebar-nav';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useAppearance } from '@/hooks/use-appearance';
import { projectDocsLayoutToHookLayout, useDocsLayout } from '@/hooks/use-docs-layout';
import { useDocsAsk } from '@/hooks/use-docs-ask';
import { useDocsSearch } from '@/hooks/use-docs-search';
import { docsThemeStyle } from '@/lib/docs-branding';
import { enhanceDocsContent } from '@/lib/docs-content';
import { cn } from '@/lib/utils';
import { docLinkOptions, githubEditUrl, type PublicDocsProps } from '@/pages/docs/types';

function VersionLanguageSelectors({
    project,
    version,
    locale,
    versions,
    languages,
    page,
}: Pick<
    PublicDocsProps,
    'project' | 'version' | 'locale' | 'versions' | 'languages' | 'page'
>) {
    return (
        <div className="mb-4 flex gap-2 border-b pb-4">
            <select
                className="w-full rounded-md border bg-transparent px-2 py-1.5 text-xs"
                value={version.slug}
                onChange={(event) =>
                    router.get(`/docs/${project.pathKey}/${event.target.value}/${locale.code}/${page.slug}`)
                }
            >
                {versions.map((item) => (
                    <option key={item.slug} value={item.slug}>
                        {item.name}
                    </option>
                ))}
            </select>
            <select
                className="w-full rounded-md border bg-transparent px-2 py-1.5 text-xs"
                value={locale.code}
                onChange={(event) =>
                    router.get(`/docs/${project.pathKey}/${version.slug}/${event.target.value}/${page.slug}`)
                }
            >
                {languages.map((item) => (
                    <option key={item.code} value={item.code}>
                        {item.name}
                    </option>
                ))}
            </select>
        </div>
    );
}

export default function GitbookDocsTemplate({
    project,
    version,
    locale,
    versions,
    languages,
    pages,
    page,
    navTree,
    breadcrumbs,
    toc,
    prev,
    next,
    hreflang,
    basePath,
    feedbackUrl,
    showPoweredBy,
    docsLayout,
    canonicalUrl,
    aiAskEnabled,
    askUrl,
    searchUrl,
    headerLinks,
    docsHomeUrl,
    directoryUrl,
    announcementsUrl,
    changelogUrl,
}: PublicDocsProps) {
    const { appearance, updateAppearance } = useAppearance();
    const { layout, toggleLayout } = useDocsLayout({
        defaultLayout: projectDocsLayoutToHookLayout(docsLayout),
        storageKey: `docs-layout-${project.pathKey}`,
    });
    const isWide = layout === 'wide';
    const homeHref = `${basePath}/${navTree[0]?.slug ?? pages[0]?.slug ?? page.slug}`;
    const { center: centerLinks, end: endLinks } = splitHeaderLinks(headerLinks);
    const contentRef = useRef<HTMLDivElement>(null);
    const search = useDocsSearch(searchUrl, { versionId: version.id, languageId: locale.id });
    const ask = useDocsAsk(askUrl, aiAskEnabled);
    const editUrl = githubEditUrl(project.github_edit_url, page.slug);

    useEffect(() => {
        if (!contentRef.current) {
            return;
        }

        enhanceDocsContent(contentRef.current, { steps: true });
    }, [page.html]);

    const theme = docsThemeStyle(project);

    const cycleTheme = () => {
        updateAppearance(appearance === 'dark' ? 'light' : appearance === 'light' ? 'system' : 'dark');
    };

    const sidebar = (
        <>
            <VersionLanguageSelectors
                project={project}
                version={version}
                locale={locale}
                versions={versions}
                languages={languages}
                page={page}
            />
            <p className="mb-2 px-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                Documentation
            </p>
            <DocsSidebarNav tree={navTree} basePath={basePath} currentSlug={page.slug} />
        </>
    );

    return (
        <>
            <DocsHead project={project} page={page} hreflang={hreflang} canonicalUrl={canonicalUrl} />
            <div
                key={page.id}
                className="docs-template-gitbook flex min-h-screen flex-col bg-background text-foreground"
                style={theme}
            >
                <DocsPublicHeader
                    template="gitbook"
                    brandHref={homeHref}
                    project={project}
                    leading={
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button variant="ghost" size="icon" className="shrink-0 lg:hidden">
                                    <Menu className="size-4" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left" className="docs-sidebar-scroll w-72 overflow-y-auto">
                                <DocsHeaderBrand
                                    href={homeHref}
                                    name={project.name}
                                    logoUrl={project.logo_url}
                                    headingFont={project.heading_font}
                                    className="mb-4"
                                />
                                {sidebar}
                            </SheetContent>
                        </Sheet>
                    }
                    centerLinks={centerLinks}
                    endLinks={endLinks}
                    actions={
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
                            <Button variant="outline" size="icon" className="sm:hidden" onClick={search.openSearch}>
                                <Search className="size-4" />
                            </Button>
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
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        onClick={toggleLayout}
                                        aria-label={isWide ? 'Switch to centered layout' : 'Switch to full width layout'}
                                    >
                                        {isWide ? <Minimize2 className="size-4" /> : <Maximize2 className="size-4" />}
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>{isWide ? 'Centered layout' : 'Full width layout'}</TooltipContent>
                            </Tooltip>
                            <Button variant="outline" size="icon" onClick={cycleTheme} aria-label="Toggle theme">
                                {appearance === 'dark' ? <Sun className="size-4" /> : <Moon className="size-4" />}
                            </Button>
                        </>
                    }
                />

                <div
                    className={cn(
                        'grid w-full flex-1 gap-0',
                        toc.length > 0
                            ? 'lg:grid-cols-[260px_minmax(0,1fr)_220px]'
                            : 'lg:grid-cols-[260px_minmax(0,1fr)]',
                        !isWide && 'mx-auto max-w-7xl',
                    )}
                >
                    <aside
                        className={cn(
                            'hidden border-r py-6 lg:block',
                            isWide ? 'pr-3' : 'px-4',
                        )}
                    >
                        <div
                            className={cn(
                                'docs-sidebar-scroll sticky top-[72px] max-h-[calc(100vh-88px)] overflow-y-auto',
                                isWide && 'pl-4',
                            )}
                        >
                            {sidebar}
                        </div>
                    </aside>

                    <main className={cn('min-w-0 py-8 lg:py-10', isWide ? 'px-6 lg:px-10' : 'px-4 lg:px-10')}>
                        <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                            <nav className="flex flex-wrap items-center gap-1.5 text-sm text-muted-foreground">
                                {breadcrumbs.map((crumb, index) => (
                                    <span key={`${crumb.title}-${index}`} className="flex items-center gap-1.5">
                                        {index > 0 && <span>/</span>}
                                        {crumb.slug && index < breadcrumbs.length - 1 ? (
                                            <Link
                                                href={`${basePath}/${crumb.slug}`}
                                                {...docLinkOptions}
                                                className="hover:text-foreground"
                                            >
                                                {crumb.title}
                                            </Link>
                                        ) : (
                                            <span className={index === breadcrumbs.length - 1 ? 'text-foreground' : ''}>
                                                {crumb.title}
                                            </span>
                                        )}
                                    </span>
                                ))}
                            </nav>
                            <DocsFeedbackWidget pageId={page.id} feedbackUrl={feedbackUrl} compact />
                        </div>

                        <div className="mb-8 flex flex-wrap items-center justify-between gap-3">
                            <div className="min-w-0 flex-1">
                                <h1
                                    className="text-4xl font-bold tracking-tight sm:text-[2.75rem] sm:leading-tight"
                                    style={{ fontFamily: project.heading_font }}
                                >
                                    {page.title}
                                </h1>
                                {page.subtitle ? (
                                    <p className="mt-3 max-w-2xl text-lg text-muted-foreground">{page.subtitle}</p>
                                ) : null}
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                {typeof window !== 'undefined' ? (
                                    <CopyButton value={window.location.href} label="Copy link" />
                                ) : null}
                                {editUrl ? (
                                    <Button variant="outline" size="sm" asChild>
                                        <a href={editUrl} target="_blank" rel="noreferrer">
                                            <ExternalLink className="size-3.5" />
                                            Edit
                                        </a>
                                    </Button>
                                ) : null}
                            </div>
                        </div>

                        <article
                            ref={contentRef}
                            className="docs-content docs-content-steps max-w-none text-[15px] leading-7"
                            dangerouslySetInnerHTML={{ __html: page.html }}
                        />

                        <div className="mt-12 flex justify-between gap-4 border-t pt-6 text-sm">
                            {prev ? (
                                <Link
                                    href={`${basePath}/${prev.slug}`}
                                    {...docLinkOptions}
                                    className="hover:text-primary"
                                    style={{ color: 'var(--docs-primary)' }}
                                >
                                    ← {prev.title}
                                </Link>
                            ) : (
                                <span />
                            )}
                            {next ? (
                                <Link
                                    href={`${basePath}/${next.slug}`}
                                    {...docLinkOptions}
                                    className="hover:text-primary"
                                    style={{ color: 'var(--docs-primary)' }}
                                >
                                    {next.title} →
                                </Link>
                            ) : (
                                <span />
                            )}
                        </div>
                    </main>

                    {toc.length > 0 ? (
                        <aside className={cn('hidden border-l py-6 lg:block', isWide ? 'pl-3 pr-4' : 'px-4')}>
                            <div className="sticky top-[72px] max-h-[calc(100vh-88px)] overflow-y-auto">
                                <p className="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    On this page
                                </p>
                                <div className="space-y-2 border-l pl-3">
                                    {toc.map((item) => (
                                        <a
                                            key={item.id}
                                            href={`#${item.id}`}
                                            className="block text-sm text-muted-foreground transition-colors hover:text-foreground"
                                            style={{
                                                paddingLeft: (item.level - 2) * 8,
                                                borderLeftColor: 'var(--docs-primary)',
                                            }}
                                        >
                                            {item.text}
                                        </a>
                                    ))}
                                </div>
                            </div>
                        </aside>
                    ) : null}
                </div>

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
                basePath={basePath}
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
                basePath={basePath}
                onQuestionChange={ask.setQuestion}
                onSubmit={() => void ask.submit()}
                onClose={ask.closeAsk}
            />
        </>
    );
}
