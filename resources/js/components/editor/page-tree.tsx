import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { GripVertical, IndentIncrease, Plus } from 'lucide-react';

type PageNode = {
    id: number;
    title: string;
    slug: string;
    parent_id: number | null;
    position: number;
    status: string;
    hidden: boolean;
};

type Props = {
    pages: PageNode[];
    activePageId?: number;
    canEdit: boolean;
    projectId: number;
    onSelect: (pageId: number) => void;
    onAddSubpage?: (parentId: number) => void;
};

function buildTree(pages: PageNode[]): Array<PageNode & { depth: number }> {
    const byParent = new Map<number | null, PageNode[]>();

    for (const page of pages) {
        const key = page.parent_id;
        if (!byParent.has(key)) {
            byParent.set(key, []);
        }
        byParent.get(key)!.push(page);
    }

    for (const group of byParent.values()) {
        group.sort((a, b) => a.position - b.position);
    }

    const result: Array<PageNode & { depth: number }> = [];

    const walk = (parentId: number | null, depth: number) => {
        for (const node of byParent.get(parentId) ?? []) {
            result.push({ ...node, depth });
            walk(node.id, depth + 1);
        }
    };

    walk(null, 0);

    return result;
}

export function PageTree({ pages, activePageId, canEdit, projectId, onSelect, onAddSubpage }: Props) {
    const [draggingId, setDraggingId] = useState<number | null>(null);
    const tree = useMemo(() => buildTree(pages), [pages]);

    const persistOrder = (next: PageNode[]) => {
        router.post(
            `/projects/${projectId}/pages/reorder`,
            {
                order: next.map((page, index) => ({
                    id: page.id,
                    position: index,
                    parent_id: page.parent_id,
                })),
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    const reorder = (targetId: number, nest = false) => {
        if (draggingId === null || draggingId === targetId) {
            return;
        }

        const flat = pages.slice();
        const fromIndex = flat.findIndex((page) => page.id === draggingId);
        const target = flat.find((page) => page.id === targetId);

        if (fromIndex < 0 || !target) {
            return;
        }

        const [moved] = flat.splice(fromIndex, 1);
        const toIndex = flat.findIndex((page) => page.id === targetId);

        if (nest) {
            moved.parent_id = target.id;
            flat.splice(toIndex + 1, 0, moved);
        } else {
            moved.parent_id = target.parent_id;
            flat.splice(toIndex, 0, moved);
        }

        persistOrder(flat.map((page, index) => ({ ...page, position: index })));
    };

    const unnest = (pageId: number) => {
        const flat = pages.map((page) =>
            page.id === pageId ? { ...page, parent_id: null } : page,
        );
        persistOrder(flat.map((page, index) => ({ ...page, position: index })));
    };

    return (
        <div className="space-y-1">
            {tree.map((node) => (
                <div
                    key={node.id}
                    draggable={canEdit}
                    onDragStart={() => setDraggingId(node.id)}
                    onDragEnd={() => setDraggingId(null)}
                    onDragOver={(event) => event.preventDefault()}
                    onDrop={(event) => {
                        event.preventDefault();
                        reorder(node.id, event.shiftKey);
                    }}
                    className={`flex items-center gap-1 rounded-md ${
                        activePageId === node.id ? 'bg-primary/10' : 'hover:bg-muted'
                    }`}
                    style={{ paddingLeft: `${node.depth * 12}px` }}
                >
                    {canEdit && <GripVertical className="ml-1 size-4 shrink-0 text-muted-foreground" />}
                    <button
                        type="button"
                        onClick={() => onSelect(node.id)}
                        className="flex min-w-0 flex-1 items-center justify-between px-2 py-1.5 text-left text-sm"
                    >
                        <span className="truncate">{node.title}</span>
                        <Badge variant="outline">{node.status}</Badge>
                    </button>
                    {canEdit && onAddSubpage && (
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            className="size-7 shrink-0"
                            title="Add subpage"
                            onClick={(event) => {
                                event.stopPropagation();
                                onAddSubpage(node.id);
                            }}
                        >
                            <Plus className="size-3.5" />
                        </Button>
                    )}
                    {canEdit && node.parent_id !== null && (
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            className="size-7 shrink-0"
                            title="Move to top level"
                            onClick={() => unnest(node.id)}
                        >
                            <IndentIncrease className="size-3.5" />
                        </Button>
                    )}
                </div>
            ))}
            {tree.length === 0 && (
                <p className="px-2 py-6 text-center text-xs text-muted-foreground">
                    No pages yet. Create one to get started.
                </p>
            )}
            {canEdit && tree.length > 0 && (
                <p className="px-2 pt-2 text-[11px] text-muted-foreground">
                    Drag to reorder. Hold Shift while dropping to nest. Use + to add a subpage.
                </p>
            )}
        </div>
    );
}
