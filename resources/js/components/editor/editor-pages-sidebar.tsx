import { router } from '@inertiajs/react';
import { Archive, FileUp } from 'lucide-react';
import { useState } from 'react';
import { PageTree } from '@/components/editor/page-tree';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

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

type Props = {
    projectId: number;
    currentVersionId: number;
    currentLanguageId: number;
    pages: PageNode[];
    activePageId?: number;
    canEdit: boolean;
    archivedPages: { id: number; title: string; slug: string; deleted_at: string | null }[];
    importResult: ImportResult | null;
    onSelect: (pageId: number) => void;
    onAddPage: () => void;
    onAddSubpage?: (parentId: number) => void;
};

export function EditorPagesSidebar({
    projectId,
    currentVersionId,
    currentLanguageId,
    pages,
    activePageId,
    canEdit,
    archivedPages,
    importResult,
    onSelect,
    onAddPage,
    onAddSubpage,
}: Props) {
    const [importOpen, setImportOpen] = useState(false);
    const [archivedOpen, setArchivedOpen] = useState(false);
    const [importConflict, setImportConflict] = useState('unique');
    const [importPublish, setImportPublish] = useState(false);

    const submitImport = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const input = event.currentTarget.elements.namedItem('files') as HTMLInputElement;

        if (!input.files?.length) {
            return;
        }

        const body = new FormData();
        Array.from(input.files).forEach((file) => body.append('files[]', file));
        body.append('documentation_version_id', String(currentVersionId));
        body.append('language_id', String(currentLanguageId));
        body.append('conflict', importConflict);
        body.append('publish_immediately', importPublish ? '1' : '0');

        router.post(`/projects/${projectId}/import`, body, {
            onSuccess: () => {
                input.value = '';
                setImportOpen(false);
            },
        });
    };

    return (
        <>
            <aside className="flex min-h-0 flex-col rounded-xl border bg-card shadow-sm">
                <div className="flex items-center justify-between gap-2 border-b px-3 py-2.5">
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Pages
                    </p>
                    {canEdit && (
                        <div className="flex items-center gap-0.5">
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        onClick={() => setImportOpen(true)}
                                    >
                                        <FileUp className="size-3.5" />
                                        <span className="sr-only">Import Markdown</span>
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>Import Markdown</TooltipContent>
                            </Tooltip>
                            {archivedPages.length > 0 && (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="relative size-7"
                                            onClick={() => setArchivedOpen(true)}
                                        >
                                            <Archive className="size-3.5" />
                                            <span className="sr-only">Archived pages</span>
                                            <Badge
                                                variant="secondary"
                                                className="absolute -top-0.5 -right-0.5 h-4 min-w-4 px-1 text-[10px] leading-none"
                                            >
                                                {archivedPages.length}
                                            </Badge>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        Archived pages ({archivedPages.length})
                                    </TooltipContent>
                                </Tooltip>
                            )}
                            <Button size="sm" className="h-7 px-2.5" onClick={onAddPage}>
                                Add
                            </Button>
                        </div>
                    )}
                </div>

                <div className="min-h-0 flex-1 overflow-auto p-2">
                    <PageTree
                        pages={pages}
                        activePageId={activePageId}
                        canEdit={canEdit}
                        projectId={projectId}
                        onSelect={onSelect}
                        onAddSubpage={onAddSubpage}
                    />
                </div>
            </aside>

            <Dialog open={importOpen} onOpenChange={setImportOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Import Markdown</DialogTitle>
                        <DialogDescription>
                            Upload .md, .mdx, or .zip files to create or update pages in this version
                            and language.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="grid gap-4" onSubmit={submitImport}>
                        <div className="grid gap-2">
                            <Label htmlFor="editor-import-files">Files</Label>
                            <Input
                                id="editor-import-files"
                                name="files"
                                type="file"
                                accept=".md,.markdown,.mdx,.zip,text/markdown"
                                multiple
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="editor-import-conflict">When a slug already exists</Label>
                            <Select value={importConflict} onValueChange={setImportConflict}>
                                <SelectTrigger id="editor-import-conflict">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="unique">Import as a new page</SelectItem>
                                    <SelectItem value="update">Overwrite that page</SelectItem>
                                    <SelectItem value="skip">Skip the file</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <label className="flex items-start gap-3 rounded-lg border px-3 py-3 text-sm">
                            <Checkbox
                                checked={importPublish}
                                onCheckedChange={(checked) => setImportPublish(checked === true)}
                            />
                            <span>
                                <span className="font-medium">Publish immediately</span>
                                <span className="mt-1 block text-muted-foreground">
                                    Leave unchecked to save imported pages as drafts.
                                </span>
                            </span>
                        </label>
                        {importResult && (
                            <div className="rounded-md border bg-muted/20 p-3 text-sm">
                                <p>
                                    {importResult.created} created · {importResult.updated} updated ·{' '}
                                    {importResult.skipped} skipped
                                </p>
                                {importResult.errors.length > 0 && (
                                    <ul className="mt-2 space-y-1 text-destructive">
                                        {importResult.errors.map((row, index) => (
                                            <li key={index}>
                                                {row.file}: {row.message}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        )}
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setImportOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit">Import</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={archivedOpen} onOpenChange={setArchivedOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Archived pages</DialogTitle>
                        <DialogDescription>
                            Deleted pages stay here until you restore or permanently remove them.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        {archivedPages.map((archived) => (
                            <div
                                key={archived.id}
                                className="flex items-center justify-between gap-3 rounded-lg border px-3 py-2"
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">{archived.title}</p>
                                    <p className="text-xs text-muted-foreground">{archived.slug}</p>
                                </div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            `/projects/${projectId}/pages/${archived.id}/archive-restore`,
                                        )
                                    }
                                >
                                    Restore
                                </Button>
                            </div>
                        ))}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
