import { Link } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import { docLinkOptions, type NavTreeItem } from '@/pages/docs/types';
import { cn } from '@/lib/utils';

type Props = {
    tree: NavTreeItem[];
    basePath: string;
    currentSlug: string;
};

function branchContainsSlug(node: NavTreeItem, slug: string): boolean {
    if (node.slug === slug) {
        return true;
    }

    return node.children.some((child) => branchContainsSlug(child, slug));
}

function NavBranch({
    node,
    basePath,
    currentSlug,
    depth,
}: {
    node: NavTreeItem;
    basePath: string;
    currentSlug: string;
    depth: number;
}) {
    const hasChildren = node.children.length > 0;
    const isActive = node.slug === currentSlug;
    const branchActive = branchContainsSlug(node, currentSlug);
    const [open, setOpen] = useState(branchActive);

    useEffect(() => {
        if (branchActive) {
            setOpen(true);
        }
    }, [branchActive, currentSlug]);

    return (
        <div>
            <div
                className="flex items-center gap-0.5"
                style={{ paddingLeft: depth * 12 }}
            >
                <Link
                    href={`${basePath}/${node.slug}`}
                    {...docLinkOptions}
                    className={cn(
                        'min-w-0 flex-1 rounded-md px-2 py-1.5 text-sm transition-colors',
                        isActive
                            ? 'bg-primary/10 font-medium text-primary'
                            : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                    )}
                >
                    <span className="truncate">{node.title}</span>
                </Link>
                {hasChildren ? (
                    <button
                        type="button"
                        className="flex size-6 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-muted/60 hover:text-foreground"
                        onClick={() => setOpen((value) => !value)}
                        aria-label={open ? 'Collapse section' : 'Expand section'}
                    >
                        {open ? <ChevronDown className="size-3.5" /> : <ChevronRight className="size-3.5" />}
                    </button>
                ) : null}
            </div>
            {hasChildren && open && (
                <div className="space-y-0.5">
                    {node.children.map((child) => (
                        <NavBranch
                            key={child.id}
                            node={child}
                            basePath={basePath}
                            currentSlug={currentSlug}
                            depth={depth + 1}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

export function DocsSidebarNav({ tree, basePath, currentSlug }: Props) {
    if (tree.length === 0) {
        return <p className="px-2 text-xs text-muted-foreground">No pages published yet.</p>;
    }

    return (
        <nav className="space-y-0.5">
            {tree.map((node) => (
                <NavBranch
                    key={node.id}
                    node={node}
                    basePath={basePath}
                    currentSlug={currentSlug}
                    depth={0}
                />
            ))}
        </nav>
    );
}
