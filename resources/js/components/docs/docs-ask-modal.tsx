import { Link } from '@inertiajs/react';
import { Loader2, Sparkles, X } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { docLinkOptions } from '@/pages/docs/types';
import type { AskResult } from '@/hooks/use-docs-ask';

type Props = {
    open: boolean;
    enabled: boolean;
    question: string;
    loading: boolean;
    result: AskResult | null;
    error: string | null;
    basePath: string;
    onQuestionChange: (value: string) => void;
    onSubmit: () => void;
    onClose: () => void;
};

export function DocsAskModal({
    open,
    enabled,
    question,
    loading,
    result,
    error,
    basePath,
    onQuestionChange,
    onSubmit,
    onClose,
}: Props) {
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (open) {
            window.setTimeout(() => inputRef.current?.focus(), 0);
        }
    }, [open]);

    if (!open) {
        return null;
    }

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        void onSubmit();
    };

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-[10vh] backdrop-blur-sm">
            <div className="absolute inset-0" onClick={onClose} aria-hidden />
            <div className="relative flex max-h-[80vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border bg-background shadow-2xl">
                <div className="flex items-center gap-2 border-b px-4 py-3">
                    <Sparkles className="size-4 shrink-0 text-primary" />
                    <p className="font-medium">Ask AI</p>
                    <button
                        type="button"
                        className="ml-auto rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                        onClick={onClose}
                        aria-label="Close Ask"
                    >
                        <X className="size-4" />
                    </button>
                </div>

                <form className="flex items-center gap-2 border-b px-4 py-3" onSubmit={handleSubmit}>
                    <Input
                        ref={inputRef}
                        value={question}
                        placeholder="Ask a question about this documentation…"
                        className="flex-1"
                        disabled={loading || !enabled}
                        onChange={(event) => onQuestionChange(event.target.value)}
                    />
                    <Button type="submit" size="sm" disabled={loading || !enabled || question.trim().length < 3}>
                        {loading ? <Loader2 className="size-4 animate-spin" /> : 'Ask'}
                    </Button>
                </form>

                <div className="flex-1 overflow-y-auto p-4">
                    {!enabled && (
                        <p className="text-sm text-muted-foreground">
                            AI Ask is not available. The platform administrator needs to enable AI in Platform → Settings.
                        </p>
                    )}

                    {enabled && !loading && !result && !error && (
                        <p className="text-sm text-muted-foreground">
                            Get answers grounded in this project&apos;s published documentation.
                        </p>
                    )}

                    {loading && (
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Loader2 className="size-4 animate-spin" />
                            Searching docs and generating an answer…
                        </div>
                    )}

                    {error && <p className="text-sm text-destructive">{error}</p>}

                    {result && (
                        <div className="space-y-4">
                            <article
                                className="docs-content max-w-none text-sm leading-7"
                                dangerouslySetInnerHTML={{ __html: result.answer_html }}
                            />
                            {result.sources.length > 0 && (
                                <div className="border-t pt-4">
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Sources
                                    </p>
                                    <ul className="space-y-1">
                                        {result.sources.map((source) => (
                                            <li key={source.slug}>
                                                <Link
                                                    href={`${basePath}/${source.slug}`}
                                                    {...docLinkOptions}
                                                    className="text-sm text-primary hover:underline"
                                                    onClick={onClose}
                                                >
                                                    {source.title}
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>
                    )}
                </div>

                <div className="border-t px-4 py-2 text-xs text-muted-foreground">
                    Powered by AI · Answers are based on published documentation and may be incomplete.{' '}
                    <kbd className="rounded border bg-muted px-1.5 py-0.5 font-mono">Esc</kbd> to close
                </div>
            </div>
        </div>
    );
}
