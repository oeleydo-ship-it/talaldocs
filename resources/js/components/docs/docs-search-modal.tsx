import { Link } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { Input } from '@/components/ui/input';
import { docLinkOptions } from '@/pages/docs/types';
import { cn } from '@/lib/utils';

type Props = {
    open: boolean;
    query: string;
    results: { title: string; slug: string; excerpt?: string | null }[];
    loading: boolean;
    basePath: string;
    onQueryChange: (value: string) => void;
    onClose: () => void;
};

export function DocsSearchModal({ open, query, results, loading, basePath, onQueryChange, onClose }: Props) {
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (open) {
            window.setTimeout(() => inputRef.current?.focus(), 0);
        }
    }, [open]);

    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-[12vh] backdrop-blur-sm">
            <div
                className="absolute inset-0"
                onClick={onClose}
                aria-hidden
            />
            <div className="relative w-full max-w-xl overflow-hidden rounded-xl border bg-background shadow-2xl">
                <div className="flex items-center gap-2 border-b px-4 py-3">
                    <Search className="size-4 shrink-0 text-muted-foreground" />
                    <Input
                        ref={inputRef}
                        value={query}
                        placeholder="Search documentation…"
                        className="border-0 shadow-none focus-visible:ring-0"
                        onChange={(event) => void onQueryChange(event.target.value)}
                    />
                    <button
                        type="button"
                        className="rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                        onClick={onClose}
                        aria-label="Close search"
                    >
                        <X className="size-4" />
                    </button>
                </div>
                <div className="max-h-80 overflow-y-auto p-2">
                    {loading && <p className="px-3 py-2 text-sm text-muted-foreground">Searching…</p>}
                    {!loading && query.length >= 2 && results.length === 0 && (
                        <p className="px-3 py-2 text-sm text-muted-foreground">No results found.</p>
                    )}
                    {results.map((result) => (
                        <Link
                            key={result.slug}
                            href={`${basePath}/${result.slug}`}
                            {...docLinkOptions}
                            className={cn(
                                'block rounded-lg px-3 py-2.5 transition-colors hover:bg-muted',
                            )}
                            onClick={onClose}
                        >
                            <span className="block text-sm font-medium">{result.title}</span>
                            {result.excerpt && (
                                <span className="mt-0.5 block line-clamp-2 text-xs text-muted-foreground">
                                    {result.excerpt}
                                </span>
                            )}
                        </Link>
                    ))}
                    {query.length < 2 && (
                        <p className="px-3 py-2 text-sm text-muted-foreground">Type at least 2 characters to search.</p>
                    )}
                </div>
                <div className="border-t px-4 py-2 text-xs text-muted-foreground">
                    <kbd className="rounded border bg-muted px-1.5 py-0.5 font-mono">Esc</kbd> to close ·{' '}
                    <kbd className="rounded border bg-muted px-1.5 py-0.5 font-mono">⌘K</kbd> toggle
                </div>
            </div>
        </div>
    );
}
