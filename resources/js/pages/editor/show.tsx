import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Columns2,
    Copy,
    Eye,
    FileCode,
    FileText,
    Globe,
    MoreHorizontal,
    PenLine,
    Settings,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { AiPageGenerateDialog } from '@/components/ai-page-generate-dialog';
import { AiPageReviewDialog } from '@/components/ai-page-review-dialog';
import { EditorPagesSidebar } from '@/components/editor/editor-pages-sidebar';
import { MarkdownToolbar } from '@/components/editor/markdown-toolbar';
import { VisualEditor } from '@/components/editor/visual-editor';
import { EmptyState } from '@/components/empty-state';
import { GenerateWithAiDialog } from '@/components/generate-with-ai-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useSidebar } from '@/components/ui/sidebar';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { diffLines } from '@/lib/text-diff';

type PageNode = {
    id: number;
    title: string;
    slug: string;
    parent_id: number | null;
    position: number;
    status: string;
    hidden: boolean;
};

type ImportResult = {
    created: number;
    updated: number;
    skipped: number;
    pages: { id: number; title: string; slug: string; action: string }[];
    errors: { file: string; message: string }[];
};

type EditorFields = {
    title: string;
    subtitle: string;
    slug: string;
    markdown: string;
    parentId: number | null;
};

type Props = {
    project: { id: number; name: string; public_url: string };
    versions: { id: number; name: string; slug: string; is_default: boolean }[];
    languages: { id: number; code: string; name: string; is_default: boolean }[];
    currentVersionId: number;
    currentLanguageId: number;
    pages: PageNode[];
    page: (PageNode & {
        subtitle: string | null;
        markdown: string;
        published_markdown: string | null;
        updated_at: string | null;
        published_at: string | null;
    }) | null;
    revisions: { id: number; event: string; created_at: string | null; user: string | null; markdown: string | null }[];
    archivedPages: { id: number; title: string; slug: string; deleted_at: string | null }[];
    blocks: { id: number; name: string; slug: string }[];
    canEdit: boolean;
    previewHtml: string | null;
    ai: { configured: boolean; remaining: number | null; unlimited: boolean };
};

function editorFieldsFromPage(
    page: Props['page'],
): EditorFields {
    return {
        title: page?.title ?? '',
        subtitle: page?.subtitle ?? '',
        slug: page?.slug ?? '',
        markdown: page?.markdown ?? '',
        parentId: page?.parent_id ?? null,
    };
}

function descendantIds(pages: PageNode[], rootId: number): Set<number> {
    const children = new Map<number, number[]>();

    for (const item of pages) {
        if (item.parent_id === null) {
            continue;
        }

        const group = children.get(item.parent_id) ?? [];
        group.push(item.id);
        children.set(item.parent_id, group);
    }

    const ids = new Set<number>();
    const walk = (id: number) => {
        for (const childId of children.get(id) ?? []) {
            ids.add(childId);
            walk(childId);
        }
    };

    walk(rootId);

    return ids;
}

export default function EditorShow({
    project,
    versions,
    languages,
    currentVersionId,
    currentLanguageId,
    pages,
    page,
    revisions,
    archivedPages,
    blocks,
    canEdit,
    previewHtml,
    ai,
}: Props) {
    const pageProps = usePage<{ flash?: { import?: ImportResult | null } }>().props;
    const csrf = String(pageProps.csrf ?? '');
    const importResult = pageProps.flash?.import ?? null;
    const { open: sidebarOpen, setOpen: setSidebarOpen } = useSidebar();
    const previousSidebarOpenRef = useRef<boolean | null>(null);
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const [markdown, setMarkdown] = useState(page?.markdown ?? '');
    const [title, setTitle] = useState(page?.title ?? '');
    const [subtitle, setSubtitle] = useState(page?.subtitle ?? '');
    const [slug, setSlug] = useState(page?.slug ?? '');
    const [parentId, setParentId] = useState<number | null>(page?.parent_id ?? null);
    const [mode, setMode] = useState<'write' | 'visual' | 'preview' | 'split'>('write');
    const [savedAt, setSavedAt] = useState<string | null>(page?.updated_at ?? null);
    const [savedFields, setSavedFields] = useState<EditorFields>(() => editorFieldsFromPage(page));
    const [publishedSnapshot, setPublishedSnapshot] = useState<EditorFields | null>(null);
    const [isSaving, setIsSaving] = useState(false);
    const [liveHtml, setLiveHtml] = useState<string | null>(previewHtml);
    const [diffRevisionId, setDiffRevisionId] = useState<number | null>(null);
    const [diffRows, setDiffRows] = useState<{ type: 'same' | 'add' | 'remove'; line: string }[]>([]);

    useEffect(() => {
        previousSidebarOpenRef.current = sidebarOpen;
        setSidebarOpen(false);

        return () => {
            if (previousSidebarOpenRef.current !== null) {
                setSidebarOpen(previousSidebarOpenRef.current);
            }
        };
        // Collapse once on editor entry; allow manual expand without re-collapsing.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        const fields = editorFieldsFromPage(page);
        setMarkdown(fields.markdown);
        setTitle(fields.title);
        setSubtitle(fields.subtitle);
        setSlug(fields.slug);
        setParentId(fields.parentId);
        setSavedFields(fields);

        if (page?.status === 'published') {
            setPublishedSnapshot({
                title: page.title,
                subtitle: page.subtitle ?? '',
                slug: page.slug,
                markdown: page.published_markdown ?? '',
                parentId: page.parent_id,
            });
        } else {
            setPublishedSnapshot(null);
        }
    }, [page?.id, page?.markdown, page?.published_markdown, page?.status, page?.title, page?.subtitle, page?.slug, page?.parent_id]);

    useEffect(() => {
        setLiveHtml(previewHtml);
    }, [previewHtml, page?.id]);

    const isDirty = useMemo(
        () =>
            title !== savedFields.title
            || subtitle !== savedFields.subtitle
            || slug !== savedFields.slug
            || markdown !== savedFields.markdown
            || parentId !== savedFields.parentId,
        [title, subtitle, slug, markdown, parentId, savedFields],
    );

    const hasUnpublishedChanges = useMemo(() => {
        if (!publishedSnapshot) {
            return false;
        }

        return (
            title !== publishedSnapshot.title
            || subtitle !== publishedSnapshot.subtitle
            || slug !== publishedSnapshot.slug
            || markdown !== publishedSnapshot.markdown
        );
    }, [markdown, publishedSnapshot, slug, subtitle, title]);

    const showPublishButton = page?.status !== 'published' || hasUnpublishedChanges;

    const savePage = async (options: { revision?: boolean } = {}): Promise<boolean> => {
        if (!page) {
            return false;
        }

        const response = await fetch(`/projects/${project.id}/pages/${page.id}`, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                title,
                subtitle,
                slug,
                markdown,
                parent_id: parentId,
                autosave: true,
                revision: options.revision ?? false,
            }),
        });

        if (!response.ok) {
            return false;
        }

        const json = (await response.json()) as { saved_at?: string; html?: string };
        const nextFields: EditorFields = { title, subtitle, slug, markdown, parentId };
        const parentChanged = parentId !== savedFields.parentId;
        setSavedFields(nextFields);
        setSavedAt(json.saved_at ?? new Date().toISOString());

        if (json.html) {
            setLiveHtml(json.html);
        }

        if (parentChanged) {
            router.reload({ only: ['pages', 'page'] });
        }

        return true;
    };

    const handleSave = async () => {
        setIsSaving(true);
        await savePage({ revision: true });
        setIsSaving(false);
    };

    const handlePublish = async () => {
        if (!page) {
            return;
        }

        if (isDirty || isSaving) {
            setIsSaving(true);
            const saved = await savePage({ revision: true });

            setIsSaving(false);

            if (!saved) {
                return;
            }
        }

        router.post(`/projects/${project.id}/pages/${page.id}/publish`);
    };

    useEffect(() => {
        if (!page || !canEdit || !isDirty) {
            return;
        }

        setIsSaving(true);

        const handle = window.setTimeout(() => {
            void savePage().finally(() => setIsSaving(false));
        }, 1200);

        return () => {
            window.clearTimeout(handle);
            setIsSaving(false);
        };
    }, [canEdit, csrf, isDirty, markdown, page, parentId, project.id, slug, subtitle, title]);

    const tree = useMemo(() => pages.slice().sort((a, b) => a.position - b.position), [pages]);

    const parentOptions = useMemo(() => {
        if (!page) {
            return [];
        }

        const blocked = descendantIds(pages, page.id);
        blocked.add(page.id);

        return pages
            .filter((item) => !blocked.has(item.id))
            .sort((a, b) => a.position - b.position);
    }, [page, pages]);

    const addPage = (parentPageId: number | null = null) => {
        router.post(`/projects/${project.id}/pages`, {
            title: parentPageId ? 'New subpage' : 'New page',
            documentation_version_id: currentVersionId,
            language_id: currentLanguageId,
            parent_id: parentPageId,
        });
    };

    const uploadImage = async (file: File) => {
        if (!page) {
            return;
        }

        const body = new FormData();
        body.append('file', file);

        const response = await fetch(`/projects/${project.id}/uploads`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        });

        if (!response.ok) {
            return;
        }

        const json = (await response.json()) as { url?: string };

        if (json.url) {
            const alt = file.name.replace(/\.[^.]+$/, '');
            setMarkdown((value) => `${value}\n\n![${alt}](${json.url})\n`);
        }
    };

    const showRevisionDiff = (revisionId: number) => {
        if (!page) {
            return;
        }

        const revision = revisions.find((item) => item.id === revisionId);

        if (revision?.markdown !== undefined && revision.markdown !== null) {
            setDiffRevisionId(revisionId);
            setDiffRows(diffLines(revision.markdown, markdown));

            return;
        }

        void fetch(`/projects/${project.id}/pages/${page.id}/revisions/${revisionId}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(async (response) => {
            if (!response.ok) {
                return;
            }

            const json = (await response.json()) as { markdown?: string; current_markdown?: string };
            setDiffRevisionId(revisionId);
            setDiffRows(diffLines(json.markdown ?? '', json.current_markdown ?? markdown));
        });
    };

    const switchContext = (version: string, language: string, pageId?: number) => {
        const params = new URLSearchParams({ version, language });

        if (pageId) {
            params.set('page', String(pageId));
        }

        router.get(`/projects/${project.id}/editor?${params.toString()}`, {}, { preserveState: false });
    };

    return (
        <>
            <Head title={`${project.name} editor`} />
            <div className="flex h-[calc(100vh-3.5rem)] min-h-[640px] flex-col gap-4 p-6">
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-lg font-semibold tracking-tight">{project.name}</h1>
                            {page && (
                                <>
                                    <Badge
                                        variant={page.status === 'published' ? 'default' : 'secondary'}
                                        className="font-normal capitalize"
                                    >
                                        {page.status}
                                    </Badge>
                                    {hasUnpublishedChanges && (
                                        <Badge variant="outline" className="font-normal">
                                            Unpublished changes
                                        </Badge>
                                    )}
                                </>
                            )}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {isSaving
                                ? 'Saving…'
                                : isDirty
                                  ? 'Unsaved changes'
                                  : hasUnpublishedChanges
                                    ? 'Saved · not yet published'
                                    : savedAt
                                      ? `Saved · ${new Date(savedAt).toLocaleTimeString()}`
                                      : 'Saved'}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-1.5">
                        <Select
                            value={String(currentVersionId)}
                            onValueChange={(value) => switchContext(value, String(currentLanguageId), page?.id)}
                        >
                            <SelectTrigger className="h-8 w-28">
                                <SelectValue placeholder="Version" />
                            </SelectTrigger>
                            <SelectContent>
                                {versions.map((version) => (
                                    <SelectItem key={version.id} value={String(version.id)}>
                                        {version.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={String(currentLanguageId)}
                            onValueChange={(value) => switchContext(String(currentVersionId), value, page?.id)}
                        >
                            <SelectTrigger className="h-8 w-28">
                                <SelectValue placeholder="Language" />
                            </SelectTrigger>
                            <SelectContent>
                                {languages.map((language) => (
                                    <SelectItem key={language.id} value={String(language.id)}>
                                        {language.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button variant="outline" size="icon" className="size-8" asChild>
                                    <Link href={`/projects/${project.id}/settings`}>
                                        <Settings className="size-4" />
                                        <span className="sr-only">Project settings</span>
                                    </Link>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Project settings</TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button variant="outline" size="icon" className="size-8" asChild>
                                    <a href={project.public_url} target="_blank" rel="noreferrer">
                                        <Globe className="size-4" />
                                        <span className="sr-only">Public site</span>
                                    </a>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Public site</TooltipContent>
                        </Tooltip>
                        {canEdit && (
                            <GenerateWithAiDialog
                                projectId={project.id}
                                ai={ai}
                                canEdit={canEdit}
                                compact
                                onComplete={() => router.reload({ only: ['pages', 'page', 'previewHtml'] })}
                            />
                        )}
                    </div>
                </div>

                <div className="grid min-h-0 flex-1 gap-4 lg:grid-cols-[minmax(220px,280px)_minmax(0,1fr)_260px]">
                    <EditorPagesSidebar
                        projectId={project.id}
                        currentVersionId={currentVersionId}
                        currentLanguageId={currentLanguageId}
                        pages={tree}
                        activePageId={page?.id}
                        canEdit={canEdit}
                        archivedPages={archivedPages}
                        importResult={importResult}
                        onSelect={(pageId) =>
                            switchContext(String(currentVersionId), String(currentLanguageId), pageId)
                        }
                        onAddPage={() => addPage()}
                        onAddSubpage={(parentPageId) => addPage(parentPageId)}
                    />

                    <section className="flex min-h-0 flex-col overflow-hidden rounded-xl border bg-card shadow-sm">
                        {page ? (
                            <>
                                <div className="flex flex-col gap-2 border-b bg-muted/20 p-3">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Input
                                            value={title}
                                            onChange={(event) => setTitle(event.target.value)}
                                            disabled={!canEdit}
                                            className="h-8 max-w-xs min-w-0 flex-1 font-medium"
                                            placeholder="Page title"
                                        />
                                        <div className="flex min-w-0 items-center gap-1.5">
                                            <span className="text-xs text-muted-foreground">/</span>
                                            <Input
                                                value={slug}
                                                onChange={(event) => setSlug(event.target.value)}
                                                disabled={!canEdit}
                                                className="h-7 max-w-[140px] text-xs text-muted-foreground"
                                                placeholder="slug"
                                            />
                                        </div>
                                        <div className="ml-auto flex flex-wrap items-center gap-1.5">
                                            <ToggleGroup
                                                type="single"
                                                value={mode}
                                                onValueChange={(value) => {
                                                    if (value) {
                                                        setMode(value as typeof mode);
                                                    }
                                                }}
                                                variant="outline"
                                                size="sm"
                                            >
                                                <ToggleGroupItem
                                                    value="write"
                                                    aria-label="Markdown"
                                                    title="Markdown"
                                                >
                                                    <FileCode className="size-3.5" />
                                                </ToggleGroupItem>
                                                <ToggleGroupItem
                                                    value="visual"
                                                    aria-label="Visual"
                                                    title="Visual"
                                                >
                                                    <PenLine className="size-3.5" />
                                                </ToggleGroupItem>
                                                <ToggleGroupItem
                                                    value="preview"
                                                    aria-label="Preview"
                                                    title="Preview"
                                                >
                                                    <Eye className="size-3.5" />
                                                </ToggleGroupItem>
                                                <ToggleGroupItem
                                                    value="split"
                                                    aria-label="Split"
                                                    title="Split"
                                                >
                                                    <Columns2 className="size-3.5" />
                                                </ToggleGroupItem>
                                            </ToggleGroup>
                                            {canEdit && (
                                                <>
                                                    <AiPageGenerateDialog
                                                        projectId={project.id}
                                                        pageId={page.id}
                                                        pageTitle={title || page.title}
                                                        hasContent={markdown.trim() !== ''}
                                                        ai={ai}
                                                        compact
                                                        onApplied={({ markdown: nextMarkdown, html: nextHtml, title: nextTitle, subtitle: nextSubtitle }) => {
                                                            setMarkdown(nextMarkdown);

                                                            if (nextHtml) {
                                                                setLiveHtml(nextHtml);
                                                            }

                                                            if (nextTitle) {
                                                                setTitle(nextTitle);
                                                            }

                                                            if (nextSubtitle) {
                                                                setSubtitle(nextSubtitle);
                                                            }

                                                            router.reload({ only: ['revisions'] });
                                                        }}
                                                    />
                                                    <AiPageReviewDialog
                                                        projectId={project.id}
                                                        pageId={page.id}
                                                        pageTitle={title || page.title}
                                                        ai={ai}
                                                        compact
                                                        onApplied={(nextMarkdown, nextHtml) => {
                                                            setMarkdown(nextMarkdown);

                                                            if (nextHtml) {
                                                                setLiveHtml(nextHtml);
                                                            }

                                                            router.reload({ only: ['revisions'] });
                                                        }}
                                                    />
                                                    {isDirty && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={isSaving}
                                                            onClick={() => void handleSave()}
                                                        >
                                                            Save
                                                        </Button>
                                                    )}
                                                    {showPublishButton && (
                                                        <Button
                                                            size="sm"
                                                            disabled={isSaving}
                                                            onClick={() => void handlePublish()}
                                                        >
                                                            {page.status === 'published'
                                                                ? 'Publish changes'
                                                                : 'Publish'}
                                                        </Button>
                                                    )}
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button
                                                                variant="outline"
                                                                size="icon"
                                                                className="size-8"
                                                            >
                                                                <MoreHorizontal className="size-4" />
                                                                <span className="sr-only">More actions</span>
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end" className="z-[200]">
                                                            {page.status === 'published' && (
                                                                <DropdownMenuItem
                                                                    onClick={() =>
                                                                        router.post(
                                                                            `/projects/${project.id}/pages/${page.id}/unpublish`,
                                                                        )
                                                                    }
                                                                >
                                                                    Unpublish
                                                                </DropdownMenuItem>
                                                            )}
                                                            <DropdownMenuItem
                                                                onClick={() =>
                                                                    router.post(
                                                                        `/projects/${project.id}/pages/${page.id}/duplicate`,
                                                                    )
                                                                }
                                                            >
                                                                <Copy className="size-4" />
                                                                Duplicate
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                variant="destructive"
                                                                onClick={() => {
                                                                    if (
                                                                        confirm(
                                                                            'Delete this page? You can restore it from Archived pages in the sidebar.',
                                                                        )
                                                                    ) {
                                                                        router.delete(
                                                                            `/projects/${project.id}/pages/${page.id}`,
                                                                        );
                                                                    }
                                                                }}
                                                            >
                                                                <Trash2 className="size-4" />
                                                                Delete page
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                    <Input
                                        value={subtitle}
                                        onChange={(event) => setSubtitle(event.target.value)}
                                        disabled={!canEdit}
                                        className="h-8 max-w-xl text-sm text-muted-foreground"
                                        placeholder="Subtitle (optional — shown on public docs)"
                                    />
                                    <div className="flex max-w-xl items-center gap-2">
                                        <span className="shrink-0 text-xs text-muted-foreground">Parent</span>
                                        <Select
                                            value={parentId === null ? 'root' : String(parentId)}
                                            onValueChange={(value) =>
                                                setParentId(value === 'root' ? null : Number(value))
                                            }
                                            disabled={!canEdit}
                                        >
                                            <SelectTrigger className="h-8 text-xs">
                                                <SelectValue placeholder="Top-level page" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="root">Top-level page</SelectItem>
                                                {parentOptions.map((item) => (
                                                    <SelectItem key={item.id} value={String(item.id)}>
                                                        {item.title}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                {mode === 'write' ? (
                                    <>
                                        <MarkdownToolbar
                                            textareaRef={textareaRef}
                                            value={markdown}
                                            onChange={setMarkdown}
                                            disabled={!canEdit}
                                            onUploadImage={page ? uploadImage : undefined}
                                        />
                                        <Textarea
                                            ref={textareaRef}
                                            value={markdown}
                                            disabled={!canEdit}
                                            onChange={(event) => setMarkdown(event.target.value)}
                                            className="min-h-0 flex-1 resize-none rounded-none border-0 font-mono text-sm"
                                        />
                                    </>
                                ) : mode === 'visual' ? (
                                    <VisualEditor
                                        html={liveHtml ?? ''}
                                        disabled={!canEdit}
                                        onChange={setMarkdown}
                                    />
                                ) : mode === 'preview' ? (
                                    <div
                                        className="prose dark:prose-invert max-w-none flex-1 overflow-auto p-6"
                                        dangerouslySetInnerHTML={{
                                            __html: liveHtml ?? previewHtml ?? '',
                                        }}
                                    />
                                ) : (
                                    <div className="grid min-h-0 flex-1 lg:grid-cols-2">
                                        <Textarea
                                            ref={textareaRef}
                                            value={markdown}
                                            disabled={!canEdit}
                                            onChange={(event) => setMarkdown(event.target.value)}
                                            className="min-h-0 resize-none rounded-none border-0 border-r font-mono text-sm"
                                        />
                                        <div
                                            className="prose dark:prose-invert max-w-none overflow-auto p-6"
                                            dangerouslySetInnerHTML={{
                                                __html: liveHtml ?? previewHtml ?? '',
                                            }}
                                        />
                                    </div>
                                )}
                            </>
                        ) : (
                            <EmptyState
                                icon={FileText}
                                title="Select or create a page"
                                description="Use Add to create your first page, then drag rows to reorder the tree."
                            />
                        )}
                    </section>

                    <aside className="min-h-0 space-y-4 overflow-auto rounded-xl border bg-card p-3 shadow-sm">
                        <div>
                            <p className="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Reusable blocks
                            </p>
                            <p className="mb-2 text-xs text-muted-foreground">
                                Insert <code>{'{{block:slug}}'}</code> in Markdown.
                            </p>
                            <div className="space-y-1">
                                {blocks.map((block) => (
                                    <button
                                        key={block.id}
                                        type="button"
                                        className="w-full rounded-md border bg-muted/20 px-2 py-1.5 text-left text-xs transition-colors hover:bg-muted/50"
                                        onClick={() =>
                                            setMarkdown(
                                                (value) =>
                                                    `${value}\n\n{{block:${block.slug}}}\n`,
                                            )
                                        }
                                    >
                                        {block.name}
                                    </button>
                                ))}
                                {blocks.length === 0 && (
                                    <Link href="/blocks" className="text-xs underline">
                                        Create blocks
                                    </Link>
                                )}
                            </div>
                        </div>
                        <div>
                            <p className="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Revisions
                            </p>
                            <div className="space-y-2">
                                {revisions.map((revision) => (
                                    <div
                                        key={revision.id}
                                        className="rounded-md border bg-muted/20 p-2 text-xs"
                                    >
                                        <p>
                                            {revision.event} · {revision.user ?? 'System'}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {revision.created_at
                                                ? new Date(revision.created_at).toLocaleString()
                                                : ''}
                                        </p>
                                        {page && canEdit && (
                                            <div className="mt-1 flex gap-1">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="h-7 px-2"
                                                    onClick={() => showRevisionDiff(revision.id)}
                                                >
                                                    Diff
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="h-7 px-2"
                                                    onClick={() =>
                                                        router.post(
                                                            `/projects/${project.id}/pages/${page.id}/revisions/${revision.id}`,
                                                        )
                                                    }
                                                >
                                                    Restore
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                ))}
                                {diffRevisionId !== null && diffRows.length > 0 && (
                                    <div className="rounded-md border bg-background p-2 font-mono text-[11px] leading-5">
                                        {diffRows.map((row, index) => (
                                            <div
                                                key={`${diffRevisionId}-${index}`}
                                                className={
                                                    row.type === 'add'
                                                        ? 'bg-primary/10 text-primary'
                                                        : row.type === 'remove'
                                                          ? 'bg-destructive/10 text-destructive line-through'
                                                          : 'text-muted-foreground'
                                                }
                                            >
                                                {row.line || ' '}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </>
    );
}

EditorShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Projects', href: '/projects' },
        { title: 'Editor', href: '#' },
    ],
};
