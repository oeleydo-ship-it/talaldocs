import { Link, usePage } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type AiMeta = {
    configured: boolean;
    remaining: number | null;
    unlimited: boolean;
};

type GeneratedPage = {
    id: number;
    title: string;
    slug: string;
};

type Props = {
    projectId: number;
    ai: AiMeta;
    canEdit?: boolean;
    variant?: 'default' | 'outline' | 'secondary';
    size?: 'default' | 'sm';
    compact?: boolean;
    onComplete?: (pages: GeneratedPage[]) => void;
};

type JobResponse = {
    job: {
        id: number;
        status: 'pending' | 'processing' | 'completed' | 'failed';
        error: string | null;
        result_summary?: {
            pages?: GeneratedPage[];
        } | null;
    };
    remaining?: number | null;
    requires_queue_worker?: boolean;
    pending_stale?: boolean;
};

const QUEUE_WORKER_HELP =
    'Background jobs are not running. Start a queue worker with `composer dev` (recommended) or run `php artisan queue:work` in another terminal, then try again.';

function queueWorkerMessage(requiresQueueWorker: boolean): string {
    return requiresQueueWorker ? QUEUE_WORKER_HELP : 'Generation is taking longer than expected. Refresh the editor to check for new pages.';
}

function readErrorMessage(json: { message?: string; errors?: Record<string, string[]> }, fallback: string): string {
    return json.message ?? Object.values(json.errors ?? {})[0]?.[0] ?? fallback;
}

function jsonFetchHeaders(csrf: string): HeadersInit {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
    };
}

export function GenerateWithAiDialog({
    projectId,
    ai,
    canEdit = true,
    variant = 'outline',
    size = 'sm',
    compact = false,
    onComplete,
}: Props) {
    const csrf = String(usePage().props.csrf ?? '');
    const [open, setOpen] = useState(false);
    const [url, setUrl] = useState('');
    const [productDescription, setProductDescription] = useState('');
    const [publishImmediately, setPublishImmediately] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [polling, setPolling] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [generatedPages, setGeneratedPages] = useState<GeneratedPage[]>([]);
    const [remaining, setRemaining] = useState<number | null>(ai.remaining);

    useEffect(() => {
        setRemaining(ai.remaining);
    }, [ai.remaining]);

    if (!canEdit) {
        return null;
    }

    const limitLabel = ai.unlimited
        ? 'Unlimited AI generations on your plan.'
        : remaining === null
          ? ''
          : `${remaining} AI generation${remaining === 1 ? '' : 's'} remaining this month on the Free plan.`;

    const pollJob = async (jobId: number, requiresQueueWorker = false) => {
        setPolling(true);

        for (let attempt = 0; attempt < 60; attempt++) {
            const response = await fetch(`/projects/${projectId}/ai/jobs/${jobId}`, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
            });

            if (!response.ok) {
                let message = 'Could not check generation status.';

                if (response.status === 429) {
                    message = 'Too many status checks. Please wait a moment and try again.';
                } else {
                    try {
                        const json = (await response.json()) as { message?: string };

                        if (json.message) {
                            message = json.message;
                        }
                    } catch {
                        // ignore parse errors
                    }
                }

                setError(message);
                setPolling(false);
                setSubmitting(false);

                return;
            }

            const json = (await response.json()) as JobResponse;
            const status = json.job.status;
            const queueWorkerRequired = json.requires_queue_worker ?? requiresQueueWorker;

            if (status === 'completed') {
                const pages = json.job.result_summary?.pages ?? [];
                setGeneratedPages(pages);

                if (typeof json.remaining === 'number') {
                    setRemaining(json.remaining);
                }

                setPolling(false);
                setSubmitting(false);
                onComplete?.(pages);

                return;
            }

            if (status === 'failed') {
                setError(json.job.error ?? 'Documentation generation failed.');
                setPolling(false);
                setSubmitting(false);

                return;
            }

            if (
                status === 'pending'
                && queueWorkerRequired
                && (json.pending_stale || attempt >= 14)
            ) {
                setError(QUEUE_WORKER_HELP);
                setPolling(false);
                setSubmitting(false);

                return;
            }

            await new Promise((resolve) => window.setTimeout(resolve, 2000));
        }

        setError(queueWorkerMessage(requiresQueueWorker));
        setPolling(false);
        setSubmitting(false);
    };

    const submit = async () => {
        setError(null);
        setGeneratedPages([]);
        setSubmitting(true);

        const response = await fetch(`/projects/${projectId}/ai/generate`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: jsonFetchHeaders(csrf),
            body: JSON.stringify({
                url,
                product_description: productDescription || null,
                publish_immediately: publishImmediately,
            }),
        });

        if (response.status === 503) {
            const json = (await response.json()) as { message?: string };
            setError(json.message ?? 'AI is not configured.');
            setSubmitting(false);

            return;
        }

        if (response.status === 422) {
            const json = (await response.json()) as { message?: string; errors?: Record<string, string[]> };
            setError(readErrorMessage(json, 'Could not start AI generation.'));
            setSubmitting(false);

            return;
        }

        if (!response.ok) {
            let message = 'Could not start AI generation.';

            try {
                const json = (await response.json()) as { message?: string; errors?: Record<string, string[]> };
                message = readErrorMessage(json, message);
            } catch {
                // ignore parse errors
            }

            setError(message);
            setSubmitting(false);

            return;
        }

        const json = (await response.json()) as JobResponse;

        if (typeof json.remaining === 'number') {
            setRemaining(json.remaining);
        }

        if (json.job.status === 'completed') {
            const pages = json.job.result_summary?.pages ?? [];
            setGeneratedPages(pages);
            setSubmitting(false);
            onComplete?.(pages);

            return;
        }

        if (json.job.status === 'failed') {
            setError(json.job.error ?? 'Documentation generation failed.');
            setSubmitting(false);

            return;
        }

        await pollJob(json.job.id, json.requires_queue_worker ?? false);
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    setError(null);
                    setGeneratedPages([]);
                    setSubmitting(false);
                    setPolling(false);
                }
            }}
        >
            {compact ? (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <DialogTrigger asChild>
                            <Button variant={variant} size="icon" className="size-8">
                                <Sparkles className="size-4" />
                                <span className="sr-only">Generate with AI</span>
                            </Button>
                        </DialogTrigger>
                    </TooltipTrigger>
                    <TooltipContent>Generate with AI</TooltipContent>
                </Tooltip>
            ) : (
                <DialogTrigger asChild>
                    <Button variant={variant} size={size}>
                        <Sparkles className="size-3.5" />
                        Generate with AI
                    </Button>
                </DialogTrigger>
            )}
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Generate documentation with AI</DialogTitle>
                    <DialogDescription>
                        We fetch public content from your website and draft Welcome, Getting started,
                        Setup, Features, and FAQ pages for this project.
                    </DialogDescription>
                </DialogHeader>

                {!ai.configured ? (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                        Add AI configuration in <strong>Platform → Settings</strong> to enable AI generation.
                    </div>
                ) : (
                    <div className="grid gap-4">
                        {limitLabel && (
                            <p className="text-sm text-muted-foreground">{limitLabel}</p>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="ai-website-url">Website URL</Label>
                            <Input
                                id="ai-website-url"
                                type="url"
                                placeholder="https://example.com"
                                value={url}
                                onChange={(event) => setUrl(event.target.value)}
                                disabled={submitting}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="ai-product-description">
                                What does your product do? (optional)
                            </Label>
                            <Textarea
                                id="ai-product-description"
                                rows={3}
                                placeholder="Briefly describe your product or audience."
                                value={productDescription}
                                onChange={(event) => setProductDescription(event.target.value)}
                                disabled={submitting}
                            />
                        </div>

                        <label className="flex items-start gap-3 rounded-lg border px-3 py-3 text-sm">
                            <Checkbox
                                checked={publishImmediately}
                                onCheckedChange={(checked) => setPublishImmediately(checked === true)}
                                disabled={submitting}
                            />
                            <span>
                                <span className="font-medium">Publish immediately</span>
                                <span className="mt-1 block text-muted-foreground">
                                    Leave unchecked to save generated pages as drafts for review.
                                </span>
                            </span>
                        </label>

                        {error && (
                            <p className="text-sm text-destructive">{error}</p>
                        )}

                        {generatedPages.length > 0 && (
                            <div className="rounded-lg border bg-muted/20 px-4 py-3 text-sm">
                                <p className="mb-2 font-medium">Generated pages</p>
                                <ul className="space-y-1">
                                    {generatedPages.map((page) => (
                                        <li key={page.id}>
                                            <Link
                                                href={`/projects/${projectId}/editor?page=${page.id}`}
                                                className="text-primary underline-offset-4 hover:underline"
                                            >
                                                {page.title}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>
                )}

                <DialogFooter>
                    <Button
                        type="button"
                        onClick={() => void submit()}
                        disabled={!ai.configured || submitting || url.trim() === ''}
                    >
                        {(submitting || polling) && <Spinner />}
                        {polling ? 'Generating…' : 'Generate docs'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
