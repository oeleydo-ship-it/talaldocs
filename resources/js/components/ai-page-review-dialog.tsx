import { usePage } from '@inertiajs/react';
import { Wand2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { diffLines } from '@/lib/text-diff';

type AiMeta = {
    configured: boolean;
    remaining: number | null;
    unlimited: boolean;
};

type Proposal = {
    markdown: string;
    summary: string;
    notes: string[];
};

type ReviewResponse = {
    intent: string;
    current_markdown: string | null;
    proposal: Proposal;
};

const INTENTS = [
    { value: 'accuracy', label: 'Accuracy & correctness' },
    { value: 'clarity', label: 'Grammar & clarity' },
    { value: 'structure', label: 'Structure & headings' },
];

type Props = {
    projectId: number;
    pageId: number;
    pageTitle: string;
    ai: AiMeta;
    onApplied: (markdown: string, html: string | null) => void;
    compact?: boolean;
};

function jsonFetchHeaders(csrf: string): HeadersInit {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
    };
}

async function readErrorMessage(response: Response, fallback: string): Promise<string> {
    try {
        const json = (await response.json()) as { message?: string; errors?: Record<string, string[]> };

        return json.message ?? Object.values(json.errors ?? {})[0]?.[0] ?? fallback;
    } catch {
        return fallback;
    }
}

export function AiPageReviewDialog({
    projectId,
    pageId,
    pageTitle,
    ai,
    onApplied,
    compact = false,
}: Props) {
    const csrf = String(usePage().props.csrf ?? '');
    const [open, setOpen] = useState(false);
    const [intent, setIntent] = useState('accuracy');
    const [running, setRunning] = useState(false);
    const [applying, setApplying] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [result, setResult] = useState<ReviewResponse | null>(null);

    const reset = () => {
        setError(null);
        setResult(null);
        setRunning(false);
        setApplying(false);
    };

    const runReview = async () => {
        setError(null);
        setResult(null);
        setRunning(true);

        const response = await fetch(`/projects/${projectId}/pages/${pageId}/ai/review`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: jsonFetchHeaders(csrf),
            body: JSON.stringify({ intent }),
        });

        if (!response.ok) {
            setError(
                await readErrorMessage(
                    response,
                    response.status === 429
                        ? 'Too many AI requests. Wait a moment and try again.'
                        : 'The AI review could not be completed.',
                ),
            );
            setRunning(false);

            return;
        }

        setResult((await response.json()) as ReviewResponse);
        setRunning(false);
    };

    const applyProposal = async () => {
        if (!result) {
            return;
        }

        setApplying(true);
        setError(null);

        const response = await fetch(`/projects/${projectId}/pages/${pageId}/ai/apply`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: jsonFetchHeaders(csrf),
            body: JSON.stringify({ markdown: result.proposal.markdown, intent: result.intent }),
        });

        if (!response.ok) {
            setError(await readErrorMessage(response, 'The corrected page could not be saved.'));
            setApplying(false);

            return;
        }

        const json = (await response.json()) as { markdown?: string; html?: string | null };
        onApplied(json.markdown ?? result.proposal.markdown, json.html ?? null);
        setApplying(false);
        setOpen(false);
        reset();
    };

    const diffRows = result ? diffLines(result.current_markdown ?? '', result.proposal.markdown) : [];
    const unchanged = result !== null && diffRows.every((row) => row.type === 'same');

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    reset();
                }
            }}
        >
            {compact ? (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <DialogTrigger asChild>
                            <Button size="sm" variant="outline" className="gap-1.5">
                                <Wand2 className="size-3.5" />
                                <span className="hidden sm:inline">Review</span>
                            </Button>
                        </DialogTrigger>
                    </TooltipTrigger>
                    <TooltipContent>Review with AI</TooltipContent>
                </Tooltip>
            ) : (
                <DialogTrigger asChild>
                    <Button size="sm" variant="outline">
                        <Wand2 className="size-3.5" />
                        Review with AI
                    </Button>
                </DialogTrigger>
            )}
            <DialogContent className="flex max-h-[min(90dvh,920px)] flex-col gap-0 overflow-hidden p-0 sm:max-w-3xl">
                <DialogHeader className="shrink-0 space-y-2 border-b px-6 py-4 pr-12">
                    <DialogTitle>Review “{pageTitle}” with AI</DialogTitle>
                    <DialogDescription>
                        The AI reads this page only and proposes a corrected version. Nothing is saved
                        until you apply it, and applying creates a revision you can restore.
                    </DialogDescription>
                </DialogHeader>

                <div className="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                    {!ai.configured ? (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                            Add AI configuration in <strong>Platform → Settings</strong> to enable AI review.
                        </div>
                    ) : (
                        <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="ai-review-intent">What should the AI check?</Label>
                            <Select value={intent} onValueChange={setIntent} disabled={running || applying}>
                                <SelectTrigger id="ai-review-intent" className="w-full sm:w-72">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {INTENTS.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {error && <p className="text-sm text-destructive">{error}</p>}

                        {result && (
                            <div className="grid gap-3">
                                {result.proposal.summary && (
                                    <p className="text-sm text-muted-foreground">{result.proposal.summary}</p>
                                )}

                                {result.proposal.notes.length > 0 && (
                                    <div className="rounded-lg border bg-muted/20 px-4 py-3 text-sm">
                                        <p className="mb-2 font-medium">Findings</p>
                                        <ul className="list-disc space-y-1 pl-4">
                                            {result.proposal.notes.map((note, index) => (
                                                <li key={index}>{note}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}

                                {unchanged ? (
                                    <p className="text-sm text-muted-foreground">
                                        The AI did not propose any changes to this page.
                                    </p>
                                ) : (
                                    <div className="max-h-72 overflow-auto rounded-md border bg-background p-2 font-mono text-[11px] leading-5">
                                        {diffRows.map((row, index) => (
                                            <div
                                                key={index}
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
                        )}
                    </div>
                    )}
                </div>

                <DialogFooter className="shrink-0 border-t bg-background px-6 py-4">
                    {result && !unchanged && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => reset()}
                            disabled={applying}
                        >
                            Discard
                        </Button>
                    )}
                    <Button
                        type="button"
                        variant={result ? 'outline' : 'default'}
                        onClick={() => void runReview()}
                        disabled={!ai.configured || running || applying}
                    >
                        {running && <Spinner />}
                        {result ? 'Run again' : 'Review page'}
                    </Button>
                    {result && !unchanged && (
                        <Button type="button" onClick={() => void applyProposal()} disabled={applying}>
                            {applying && <Spinner />}
                            Apply correction
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
