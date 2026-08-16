import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Blocks, Copy, Pencil, Trash2 } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

type Block = {
    id: number;
    name: string;
    slug: string;
    markdown: string | null;
    preview_html: string | null;
    shortcode: string;
};

type Props = {
    blocks: Block[];
    canEdit: boolean;
};

function looksLikeJsonDump(content: string): boolean {
    const trimmed = content.trim();
    if (!trimmed.startsWith('{') && !trimmed.startsWith('[')) {
        return false;
    }
    try {
        JSON.parse(trimmed);
        return true;
    } catch {
        return false;
    }
}

function BlockPreview({ block }: { block: Block }) {
    if (block.preview_html) {
        return (
            <div
                className="prose prose-sm dark:prose-invert max-w-none line-clamp-4 text-sm"
                dangerouslySetInnerHTML={{ __html: block.preview_html }}
            />
        );
    }

    const text = (block.markdown ?? '').trim();
    if (!text) {
        return <p className="text-sm italic text-muted-foreground">No content yet.</p>;
    }

    return <p className="line-clamp-3 text-sm text-muted-foreground">{text}</p>;
}

export default function BlocksIndex({ blocks, canEdit }: Props) {
    const { flash } = usePage<{ flash?: { warning?: string } }>().props;
    const form = useForm({ name: '', markdown: '' });
    const [editing, setEditing] = useState<Block | null>(null);
    const editForm = useForm({ name: '', markdown: '' });

    const openEdit = (block: Block) => {
        setEditing(block);
        editForm.setData({ name: block.name, markdown: block.markdown ?? '' });
    };

    const copyShortcode = async (shortcode: string) => {
        await navigator.clipboard.writeText(shortcode);
    };

    return (
        <>
            <Head title="Reusable blocks" />
            <PageContainer>
                <PageHeader
                    title="Reusable blocks"
                    description={
                        <>
                            Reusable Markdown snippets you can embed in any page with{' '}
                            <code className="rounded-md bg-muted px-1.5 py-0.5 text-xs">
                                {'{{block:slug}}'}
                            </code>
                            . This is optional — most teams start with Projects → Editor.
                        </>
                    }
                />

                {flash?.warning && (
                    <Alert>
                        <AlertTitle>Content tip</AlertTitle>
                        <AlertDescription>{flash.warning}</AlertDescription>
                    </Alert>
                )}

                {canEdit && (
                    <Card className="shadow-sm">
                        <CardHeader>
                            <CardTitle>New block</CardTitle>
                            <CardDescription>
                                Write human-readable Markdown (callouts, disclaimers, shared API intro text).
                                After saving, reference it as{' '}
                                <code className="rounded bg-muted px-1 py-0.5 text-xs">{'{{block:your-slug}}'}</code>.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                className="space-y-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    form.post('/blocks', {
                                        onSuccess: () => form.reset(),
                                    });
                                }}
                            >
                                <Input
                                    placeholder="Callout title"
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                />
                                <Textarea
                                    placeholder={'## Important\n\nYour reusable Markdown goes here.'}
                                    rows={6}
                                    value={form.data.markdown}
                                    onChange={(event) => form.setData('markdown', event.target.value)}
                                />
                                {looksLikeJsonDump(form.data.markdown) && (
                                    <Alert variant="destructive">
                                        <AlertDescription>
                                            This looks like pasted JSON from an API response. Blocks should be
                                            Markdown, not raw API dumps.
                                        </AlertDescription>
                                    </Alert>
                                )}
                                {form.errors.markdown && (
                                    <p className="text-sm text-destructive">{form.errors.markdown}</p>
                                )}
                                <Button type="submit" disabled={form.processing}>
                                    Create block
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {blocks.length === 0 ? (
                    <EmptyState
                        icon={Blocks}
                        title="No blocks yet"
                        description="Blocks are optional. Create one when you have content you want to reuse across multiple pages."
                    />
                ) : (
                    <div className="grid gap-3">
                        {blocks.map((block) => (
                            <Card key={block.id} className="shadow-sm">
                                <CardHeader className="pb-3">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <CardTitle className="text-base">{block.name}</CardTitle>
                                            <CardDescription className="mt-1 flex flex-wrap items-center gap-2">
                                                <code className="rounded bg-muted px-1.5 py-0.5 text-xs">
                                                    {block.shortcode}
                                                </code>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    className="h-7 px-2"
                                                    onClick={() => copyShortcode(block.shortcode)}
                                                >
                                                    <Copy className="size-3.5" />
                                                    Copy shortcode
                                                </Button>
                                            </CardDescription>
                                        </div>
                                        {canEdit && (
                                            <div className="flex gap-1">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => openEdit(block)}
                                                >
                                                    <Pencil className="size-3.5" />
                                                    Edit
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => router.delete(`/blocks/${block.id}`)}
                                                >
                                                    <Trash2 className="size-3.5" />
                                                    Delete
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <BlockPreview block={block} />
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </PageContainer>

            <Dialog open={editing !== null} onOpenChange={(open) => !open && setEditing(null)}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Edit block</DialogTitle>
                        <DialogDescription>
                            Update Markdown content. The shortcode stays{' '}
                            <code className="rounded bg-muted px-1 py-0.5 text-xs">
                                {editing?.shortcode}
                            </code>
                            .
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (!editing) {
                                return;
                            }
                            editForm.patch(`/blocks/${editing.id}`, {
                                onSuccess: () => setEditing(null),
                            });
                        }}
                    >
                        <Input
                            value={editForm.data.name}
                            onChange={(event) => editForm.setData('name', event.target.value)}
                        />
                        <Textarea
                            rows={8}
                            value={editForm.data.markdown}
                            onChange={(event) => editForm.setData('markdown', event.target.value)}
                        />
                        {looksLikeJsonDump(editForm.data.markdown) && (
                            <Alert variant="destructive">
                                <AlertDescription>
                                    This looks like pasted JSON. Use Markdown instead.
                                </AlertDescription>
                            </Alert>
                        )}
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEditing(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={editForm.processing}>
                                Save changes
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

BlocksIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Blocks', href: '/blocks' },
    ],
};
