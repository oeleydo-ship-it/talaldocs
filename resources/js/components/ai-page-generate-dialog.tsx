import { usePage } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
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
    title: string | null;
    subtitle: string | null;
};

type GenerateResponse = {
    current_markdown: string | null;
    proposal: Proposal;
    html: string;
};

const AUDIENCES = [
    { value: 'developers', label: 'Developers' },
    { value: 'end_users', label: 'End users' },
];

const TONES = [
    { value: 'technical', label: 'Technical' },
    { value: 'friendly', label: 'Friendly' },
];

type Props = {
    projectId: number;
    pageId: number;
    pageTitle: string;
    hasContent: boolean;
    ai: AiMeta;
    onApplied: (payload: {
        markdown: string;
        html: string | null;
        title?: string | null;
        subtitle?: string | null;
    }) => void;
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

export function AiPageGenerateDialog({
    projectId,
    pageId,
    pageTitle,
    hasContent,
    ai,
    onApplied,
    compact = false,
}: Props) {
    const csrf = String(usePage().props.csrf ?? '');
    const [open, setOpen] = useState(false);
    const [description, setDescription] = useState('');
    const [audience, setAudience] = useState('developers');
    const [tone, setTone] = useState('technical');
    const [running, setRunning] = useState(false);
    const [applying, setApplying] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [result, setResult] = useState<GenerateResponse | null>(null);
    const [previewTab, setPreviewTab] = useState('preview');

    const reset = () => {
        setError(null);
        setResult(null);
        setRunning(false);
        setApplying(false);
        setPreviewTab('preview');
    };

    const runGenerate = async () => {
        if (description.trim().length < 10) {
            setError('Describe what this page should cover (at least 10 characters).');

            return;
        }

        setError(null);
        setResult(null);
        setRunning(true);

        const response = await fetch(`/projects/${projectId}/pages/${pageId}/ai/generate`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: jsonFetchHeaders(csrf),
            body: JSON.stringify({ description: description.trim(), audience, tone }),
        });

        if (!response.ok) {
            setError(
                await readErrorMessage(
                    response,
                    response.status === 429
                        ? 'Too many AI requests. Wait a moment and try again.'
                        : 'The page could not be generated.',
                ),
            );
            setRunning(false);

            return;
        }

        setResult((await response.json()) as GenerateResponse);
        setRunning(false);
    };

    const applyProposal = async () => {
        if (!result) {
            return;
        }

        setApplying(true);
        setError(null);

        const body: {
            markdown: string;
            title?: string;
            subtitle?: string;
        } = {
            markdown: result.proposal.markdown,
        };

        if (result.proposal.title) {
            body.title = result.proposal.title;
        }

        if (result.proposal.subtitle) {
            body.subtitle = result.proposal.subtitle;
        }

        const response = await fetch(`/projects/${projectId}/pages/${pageId}/ai/apply-generation`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: jsonFetchHeaders(csrf),
            body: JSON.stringify(body),
        });

        if (!response.ok) {
            setError(await readErrorMessage(response, 'The generated page could not be saved.'));
            setApplying(false);

            return;
        }

        const json = (await response.json()) as {
            markdown?: string;
            html?: string | null;
            title?: string | null;
            subtitle?: string | null;
        };

        onApplied({
            markdown: json.markdown ?? result.proposal.markdown,
            html: json.html ?? result.html,
            title: json.title ?? result.proposal.title,
            subtitle: json.subtitle ?? result.proposal.subtitle,
        });
        setApplying(false);
        setOpen(false);
        reset();
    };

    const diffRows = result ? diffLines(result.current_markdown ?? '', result.proposal.markdown) : [];
    const hasChanges = result !== null && !diffRows.every((row) => row.type === 'same');

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
                                <Sparkles className="size-3.5" />
                                <span className="hidden sm:inline">Generate</span>
                            </Button>
                        </DialogTrigger>
                    </TooltipTrigger>
                    <TooltipContent>Generate page with AI</TooltipContent>
                </Tooltip>
            ) : (
                <DialogTrigger asChild>
                    <Button size="sm" variant="outline">
                        <Sparkles className="size-3.5" />
                        Generate page
                    </Button>
                </DialogTrigger>
            )}
            <DialogContent className="flex max-h-[min(90dvh,920px)] flex-col gap-0 overflow-hidden p-0 sm:max-w-3xl">
                <DialogHeader className="shrink-0 space-y-2 border-b px-6 py-4 pr-12">
                    <DialogTitle>Generate “{pageTitle}” with AI</DialogTitle>
                    <DialogDescription>
                        Describe what this page should explain. The AI writes a full draft in Markdown.
                        Nothing is saved until you apply it, and applying creates a revision you can restore.
                    </DialogDescription>
                </DialogHeader>

                <div className="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                    {!ai.configured ? (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                            Add AI configuration in <strong>Platform → Settings</strong> to enable AI generation.
                        </div>
                    ) : (
                        <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="ai-generate-description">What should this page cover?</Label>
                            <Textarea
                                id="ai-generate-description"
                                value={description}
                                onChange={(event) => setDescription(event.target.value)}
                                placeholder="Explain the authentication flow, required environment variables, and example API calls..."
                                rows={4}
                                disabled={running || applying}
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="ai-generate-audience">Audience</Label>
                                <Select
                                    value={audience}
                                    onValueChange={setAudience}
                                    disabled={running || applying}
                                >
                                    <SelectTrigger id="ai-generate-audience">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {AUDIENCES.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="ai-generate-tone">Tone</Label>
                                <Select value={tone} onValueChange={setTone} disabled={running || applying}>
                                    <SelectTrigger id="ai-generate-tone">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {TONES.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {error && <p className="text-sm text-destructive">{error}</p>}

                        {result && (
                            <div className="grid gap-3">
                                {result.proposal.summary && (
                                    <p className="text-sm text-muted-foreground">{result.proposal.summary}</p>
                                )}

                                {(result.proposal.title || result.proposal.subtitle) && (
                                    <div className="rounded-lg border bg-muted/20 px-4 py-3 text-sm">
                                        {result.proposal.title && (
                                            <p>
                                                <span className="font-medium">Suggested title:</span>{' '}
                                                {result.proposal.title}
                                            </p>
                                        )}
                                        {result.proposal.subtitle && (
                                            <p className="mt-1 text-muted-foreground">
                                                {result.proposal.subtitle}
                                            </p>
                                        )}
                                    </div>
                                )}

                                {hasContent && hasChanges && (
                                    <p className="text-sm text-amber-800 dark:text-amber-200">
                                        Applying will replace the current page content.
                                    </p>
                                )}

                                <Tabs value={previewTab} onValueChange={setPreviewTab}>
                                    <TabsList>
                                        <TabsTrigger value="preview">Preview</TabsTrigger>
                                        <TabsTrigger value="markdown">Markdown</TabsTrigger>
                                        {hasContent && <TabsTrigger value="changes">Changes</TabsTrigger>}
                                    </TabsList>
                                    <TabsContent value="preview">
                                        <div
                                            className="prose dark:prose-invert max-h-72 max-w-none overflow-auto rounded-md border bg-background p-4 text-sm"
                                            dangerouslySetInnerHTML={{ __html: result.html }}
                                        />
                                    </TabsContent>
                                    <TabsContent value="markdown">
                                        <pre className="max-h-72 overflow-auto rounded-md border bg-muted/20 p-4 text-xs leading-5 whitespace-pre-wrap">
                                            {result.proposal.markdown}
                                        </pre>
                                    </TabsContent>
                                    {hasContent && (
                                        <TabsContent value="changes">
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
                                        </TabsContent>
                                    )}
                                </Tabs>
                            </div>
                        )}
                    </div>
                    )}
                </div>

                <DialogFooter className="shrink-0 border-t bg-background px-6 py-4">
                    {result && (
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
                        onClick={() => void runGenerate()}
                        disabled={!ai.configured || running || applying || description.trim().length < 10}
                    >
                        {running && <Spinner />}
                        {result ? 'Generate again' : 'Generate page'}
                    </Button>
                    {result && (
                        <Button type="button" onClick={() => void applyProposal()} disabled={applying}>
                            {applying && <Spinner />}
                            Apply to page
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
