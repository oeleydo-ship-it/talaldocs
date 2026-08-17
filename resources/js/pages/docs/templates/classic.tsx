'use no memo';

import { Link, router } from '@inertiajs/react';
import { Maximize2, Menu, Minimize2, Moon, Search, Sparkles, Sun } from 'lucide-react';
import { useEffect, useRef } from 'react';
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
import { useDocsAsk } from '@/hooks/use-docs-ask';
import { useDocsLayout } from '@/hooks/use-docs-layout';
import { useDocsSearch } from '@/hooks/use-docs-search';
import { docsThemeStyle } from '@/lib/docs-branding';
import { enhanceDocsContent } from '@/lib/docs-content';
import { cn } from '@/lib/utils';
import { docLinkOptions, type PublicDocsProps } from '@/pages/docs/types';

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
        <div className="mb-3 flex gap-2">
            <select
                className="w-full rounded-md border bg-transparent px-2 py-1 text-xs"
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
                className="w-full rounded-md border bg-transparent px-2 py-1 text-xs"
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

export default function ClassicDocsTemplate(props: PublicDocsProps) {
    const {
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
        headerLinks,
        showPoweredBy,
        docsHomeUrl,
        directoryUrl,
        announcementsUrl,
        changelogUrl,
    } = props;
    const { appearance, updateAppearance } = useAppearance();
    const { layout, toggleLayout } = useDocsLayout();
    const contentRef = useRef<HTMLDivElement>(null);
    const isWide = layout === 'wide';
    const homeHref = `${basePath}/${navTree[0]?.slug ?? pages[0]?.slug ?? page.slug}`;
    const { center: centerLinks, end: endLinks } = splitHeaderLinks(headerLinks);
    const search = useDocsSearch(props.searchUrl, { versionId: version.id, languageId: locale.id });
    const ask = useDocsAsk(props.askUrl, props.aiAskEnabled);

    useEffect(() => {
        if (!contentRef.current) {
            return;
        }

        enhanceDocsContent(contentRef.current);
    }, [page.html]);

    const theme = docsThemeStyle(project);

    const cycleTheme = () => {
        updateAppearance(appearance === 'dark' ? 'light' : appearance === 'light' ? 'system' : 'dark');
    };

    return (
        <>
            <DocsHead
                project={project}
                page={page}
                hreflang={hreflang}
                canonicalUrl={props.canonicalUrl}
            />
            <div key={page.id} className="flex min-h-screen flex-col bg-background text-foreground" style={theme}>
                <DocsPublicHeader
                    template="classic"
                    brandHref={homeHref}
                    project={project}
                    brandChildren={
                        <p className="truncate text-xs text-muted-foreground">
                            {version.name} · {locale.name}
                        </p>
                    }
                    innerClassName={isWide ? 'w-full max-w-none' : undefined}
                    leading={
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button variant="outline" size="icon" className="shrink-0 lg:hidden">
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
                                <VersionLanguageSelectors
                                    project={project}
                                    version={version}
                                    locale={locale}
                                    versions={versions}
                                    languages={languages}
                                    page={page}
                                />
                                <DocsSidebarNav tree={navTree} basePath={basePath} currentSlug={page.slug} />
                            </SheetContent>
                        </Sheet>
                    }
                    centerLinks={centerLinks}
                    endLinks={endLinks}
                    actions={
                        <>
                            <Button variant="outline" size="sm" className="hidden sm:inline-flex" onClick={search.openSearch}>
                                <Search className="size-4" />
                                Search
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
                                {!props.aiAskEnabled && (
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
                                        aria-label={isWide ? 'Switch to classic layout' : 'Switch to wide layout'}
                                    >
                                        {isWide ? (
                                            <Minimize2 className="size-4" />
                                        ) : (
                                            <Maximize2 className="size-4" />
                                        )}
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>{isWide ? 'Classic layout' : 'Wide layout'}</TooltipContent>
                            </Tooltip>
                            <Button variant="outline" size="icon" onClick={cycleTheme} aria-label="Toggle theme">
                                {appearance === 'dark' ? <Sun className="size-4" /> : <Moon className="size-4" />}
                            </Button>
                        </>
                    }
                />

                <div
                    className={cn(
                        'flex-1',
                        isWide
                            ? toc.length > 0
                                ? 'grid lg:grid-cols-[260px_minmax(0,1fr)_240px]'
                                : 'grid lg:grid-cols-[260px_minmax(0,1fr)]'
                            : cn(
                                  'mx-auto grid max-w-6xl gap-6 px-4 py-6',
                                  toc.length > 0
                                      ? 'lg:grid-cols-[220px_minmax(0,1fr)_200px]'
                                      : 'lg:grid-cols-[220px_minmax(0,1fr)]',
                              ),
                    )}
                >
                    <nav
                        className={cn(
                            'hidden space-y-2 lg:block',
                            isWide &&
                                'docs-sidebar-scroll sticky top-[57px] max-h-[calc(100vh-57px)] overflow-y-auto border-r bg-muted/20 px-4 py-6',
                        )}
                    >
                        <VersionLanguageSelectors
                            project={project}
                            version={version}
                            locale={locale}
                            versions={versions}
                            languages={languages}
                            page={page}
                        />
                        <DocsSidebarNav tree={navTree} basePath={basePath} currentSlug={page.slug} />
                    </nav>

                    <article key={page.id} className={cn('min-w-0', isWide && 'px-6 py-6 lg:px-10')}>
                        {breadcrumbs.length > 1 && (
                            <p className="mb-3 text-xs text-muted-foreground">
                                {breadcrumbs.map((crumb, index) => (
                                    <span key={`${crumb.slug ?? 'root'}-${index}`}>
                                        {index > 0 && ' / '}
                                        {crumb.slug && index < breadcrumbs.length - 1 ? (
                                            <Link
                                                href={`${basePath}/${crumb.slug}`}
                                                {...docLinkOptions}
                                                className="hover:text-foreground"
                                            >
                                                {crumb.title}
                                            </Link>
                                        ) : (
                                            <span>{crumb.title}</span>
                                        )}
                                    </span>
                                ))}
                            </p>
                        )}
                        <h1
                            className="mb-2 text-3xl font-semibold tracking-tight sm:text-4xl"
                            style={{ fontFamily: project.heading_font, color: project.primary_color }}
                        >
                            {page.title}
                        </h1>
                        {page.subtitle ? (
                            <p className="mb-6 text-lg text-muted-foreground">{page.subtitle}</p>
                        ) : (
                            <div className="mb-6" />
                        )}
                        <div
                            ref={contentRef}
                            className="docs-content space-y-4 text-[15px] leading-7"
                            dangerouslySetInnerHTML={{ __html: page.html }}
                        />
                        <div className="mt-10 flex justify-between gap-4 border-t pt-4 text-sm">
                            {prev ? (
                                <Link href={`${basePath}/${prev.slug}`} {...docLinkOptions}>
                                    ← {prev.title}
                                </Link>
                            ) : (
                                <span />
                            )}
                            {next ? (
                                <Link href={`${basePath}/${next.slug}`} {...docLinkOptions}>
                                    {next.title} →
                                </Link>
                            ) : (
                                <span />
                            )}
                        </div>
                        <DocsFeedbackWidget pageId={page.id} feedbackUrl={feedbackUrl} className="mt-8" />
                    </article>

                    {toc.length > 0 ? (
                        <aside
                            className={cn(
                                'hidden text-sm lg:block',
                                isWide &&
                                    'sticky top-[57px] max-h-[calc(100vh-57px)] overflow-y-auto border-l px-4 py-6',
                            )}
                        >
                            <p className="mb-2 font-medium">On this page</p>
                            <div className="space-y-1">
                                {toc.map((item) => (
                                    <a
                                        key={item.id}
                                        href={`#${item.id}`}
                                        className="block text-muted-foreground hover:text-foreground"
                                        style={{ paddingLeft: (item.level - 2) * 12 }}
                                    >
                                        {item.text}
                                    </a>
                                ))}
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
