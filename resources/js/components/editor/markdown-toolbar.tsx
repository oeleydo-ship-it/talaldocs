import { Button } from '@/components/ui/button';
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
import { Textarea } from '@/components/ui/textarea';
import {
    buildCalloutSnippet,
    buildImageSnippet,
    buildLinkSnippet,
    buildVideoSnippet,
} from '@/lib/media-embed';
import {
    AlertTriangle,
    Bold,
    Code,
    Heading1,
    Heading2,
    ImageIcon,
    Info,
    Lightbulb,
    Link2,
    List,
    ListOrdered,
    Minus,
    Quote,
    Table,
    Video,
} from 'lucide-react';
import type { RefObject } from 'react';
import { useState } from 'react';

type Props = {
    textareaRef: RefObject<HTMLTextAreaElement | null>;
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    onUploadImage?: (file: File) => Promise<void>;
};

function wrapSelection(
    textarea: HTMLTextAreaElement,
    value: string,
    onChange: (value: string) => void,
    before: string,
    after = before,
    placeholder = 'text',
) {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selected = value.slice(start, end) || placeholder;
    const next = value.slice(0, start) + before + selected + after + value.slice(end);
    onChange(next);

    window.requestAnimationFrame(() => {
        textarea.focus();
        const cursor = start + before.length + selected.length + after.length;
        textarea.setSelectionRange(cursor, cursor);
    });
}

function insertAtCursor(
    textarea: HTMLTextAreaElement,
    value: string,
    onChange: (value: string) => void,
    snippet: string,
) {
    const start = textarea.selectionStart;
    const before = value.slice(0, start);
    const after = value.slice(start);
    const prefix = before.endsWith('\n') || before === '' ? '' : '\n\n';
    const suffix = after.startsWith('\n') || after === '' ? '' : '\n\n';
    const next = before + prefix + snippet + suffix + after;
    onChange(next);

    window.requestAnimationFrame(() => {
        textarea.focus();
        const cursor = (before + prefix + snippet + suffix).length;
        textarea.setSelectionRange(cursor, cursor);
    });
}

function insertLine(
    textarea: HTMLTextAreaElement,
    value: string,
    onChange: (value: string) => void,
    line: string,
) {
    const start = textarea.selectionStart;
    const before = value.slice(0, start);
    const after = value.slice(start);
    const prefix = before.endsWith('\n') || before === '' ? '' : '\n';
    const next = before + prefix + line + '\n' + after;
    onChange(next);

    window.requestAnimationFrame(() => {
        textarea.focus();
        const cursor = (before + prefix + line + '\n').length;
        textarea.setSelectionRange(cursor, cursor);
    });
}

export function MarkdownToolbar({ textareaRef, value, onChange, disabled, onUploadImage }: Props) {
    const [imageOpen, setImageOpen] = useState(false);
    const [videoOpen, setVideoOpen] = useState(false);
    const [linkOpen, setLinkOpen] = useState(false);
    const [imageUrl, setImageUrl] = useState('');
    const [imageAlt, setImageAlt] = useState('');
    const [videoUrl, setVideoUrl] = useState('');
    const [videoMode, setVideoMode] = useState<'auto' | 'file' | 'iframe'>('auto');
    const [rawIframe, setRawIframe] = useState('');
    const [linkText, setLinkText] = useState('');
    const [linkUrl, setLinkUrl] = useState('https://');

    const run = (action: (textarea: HTMLTextAreaElement) => void) => {
        const textarea = textareaRef.current;
        if (!textarea || disabled) {
            return;
        }
        action(textarea);
    };

    const insertSnippet = (snippet: string) => {
        run((textarea) => insertAtCursor(textarea, value, onChange, snippet));
    };

    const tools = [
        {
            label: 'Heading 1',
            icon: Heading1,
            action: () => run((textarea) => insertLine(textarea, value, onChange, '# Heading')),
        },
        {
            label: 'Heading 2',
            icon: Heading2,
            action: () => run((textarea) => insertLine(textarea, value, onChange, '## Heading')),
        },
        {
            label: 'Bold',
            icon: Bold,
            action: () => run((textarea) => wrapSelection(textarea, value, onChange, '**', '**', 'bold')),
        },
        {
            label: 'Bullet list',
            icon: List,
            action: () => run((textarea) => insertLine(textarea, value, onChange, '- Item')),
        },
        {
            label: 'Numbered list',
            icon: ListOrdered,
            action: () => run((textarea) => insertLine(textarea, value, onChange, '1. Item')),
        },
        {
            label: 'Blockquote',
            icon: Quote,
            action: () => run((textarea) => insertLine(textarea, value, onChange, '> Quote text')),
        },
        {
            label: 'Horizontal rule',
            icon: Minus,
            action: () => run((textarea) => insertLine(textarea, value, onChange, '---')),
        },
        {
            label: 'Code',
            icon: Code,
            action: () => run((textarea) => wrapSelection(textarea, value, onChange, '`', '`', 'code')),
        },
        {
            label: 'Link',
            icon: Link2,
            action: () => {
                const textarea = textareaRef.current;
                if (textarea && !disabled) {
                    const selected = value.slice(textarea.selectionStart, textarea.selectionEnd);
                    setLinkText(selected || '');
                }
                setLinkOpen(true);
            },
        },
        {
            label: 'Image',
            icon: ImageIcon,
            action: () => setImageOpen(true),
        },
        {
            label: 'Video embed',
            icon: Video,
            action: () => setVideoOpen(true),
        },
        {
            label: 'Tip callout',
            icon: Lightbulb,
            action: () => insertSnippet(buildCalloutSnippet('tip')),
        },
        {
            label: 'Warning callout',
            icon: AlertTriangle,
            action: () => insertSnippet(buildCalloutSnippet('warning')),
        },
        {
            label: 'Info callout',
            icon: Info,
            action: () => insertSnippet(buildCalloutSnippet('info')),
        },
        {
            label: 'Table',
            icon: Table,
            action: () =>
                run((textarea) =>
                    insertLine(
                        textarea,
                        value,
                        onChange,
                        '| Column | Column |\n| --- | --- |\n| Cell | Cell |',
                    ),
                ),
        },
    ] as const;

    return (
        <>
            <div className="flex flex-wrap gap-1 border-b p-2">
                {tools.map((tool) => (
                    <Button
                        key={tool.label}
                        type="button"
                        size="sm"
                        variant="ghost"
                        disabled={disabled}
                        title={tool.label}
                        onClick={tool.action}
                    >
                        <tool.icon className="size-4" />
                        <span className="sr-only">{tool.label}</span>
                    </Button>
                ))}
            </div>

            <Dialog open={imageOpen} onOpenChange={setImageOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Insert image</DialogTitle>
                        <DialogDescription>Upload a file or paste an external image URL.</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        {onUploadImage && (
                            <div className="space-y-2">
                                <Label>Upload file</Label>
                                <Input
                                    type="file"
                                    accept="image/*"
                                    onChange={(event) => {
                                        const file = event.target.files?.[0];
                                        if (file) {
                                            void onUploadImage(file).then(() => setImageOpen(false));
                                        }
                                        event.currentTarget.value = '';
                                    }}
                                />
                            </div>
                        )}
                        <div className="space-y-2">
                            <Label htmlFor="image-url">Image URL</Label>
                            <Input
                                id="image-url"
                                value={imageUrl}
                                onChange={(event) => setImageUrl(event.target.value)}
                                placeholder="https://example.com/image.png"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="image-alt">Alt text</Label>
                            <Input
                                id="image-alt"
                                value={imageAlt}
                                onChange={(event) => setImageAlt(event.target.value)}
                                placeholder="Description"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            onClick={() => {
                                if (!imageUrl.trim()) {
                                    return;
                                }
                                insertSnippet(buildImageSnippet(imageUrl, imageAlt || 'Image'));
                                setImageUrl('');
                                setImageAlt('');
                                setImageOpen(false);
                            }}
                        >
                            Insert URL
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={videoOpen} onOpenChange={setVideoOpen}>
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Insert video</DialogTitle>
                        <DialogDescription>
                            YouTube, Vimeo, Loom, direct video files, or raw iframe embed code.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="flex gap-2">
                            {(['auto', 'file', 'iframe'] as const).map((mode) => (
                                <Button
                                    key={mode}
                                    type="button"
                                    size="sm"
                                    variant={videoMode === mode ? 'default' : 'outline'}
                                    onClick={() => setVideoMode(mode)}
                                >
                                    {mode === 'auto' ? 'Platform URL' : mode === 'file' ? 'Video file' : 'Raw iframe'}
                                </Button>
                            ))}
                        </div>
                        {videoMode === 'iframe' ? (
                            <div className="space-y-2">
                                <Label htmlFor="raw-iframe">Iframe HTML</Label>
                                <Textarea
                                    id="raw-iframe"
                                    value={rawIframe}
                                    onChange={(event) => setRawIframe(event.target.value)}
                                    rows={5}
                                    placeholder='<iframe src="https://www.youtube.com/embed/..." ...></iframe>'
                                />
                            </div>
                        ) : (
                            <div className="space-y-2">
                                <Label htmlFor="video-url">Video URL</Label>
                                <Input
                                    id="video-url"
                                    value={videoUrl}
                                    onChange={(event) => setVideoUrl(event.target.value)}
                                    placeholder={
                                        videoMode === 'file'
                                            ? 'https://cdn.example.com/demo.mp4'
                                            : 'https://www.youtube.com/watch?v=...'
                                    }
                                />
                            </div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            onClick={() => {
                                const input = videoMode === 'iframe' ? rawIframe : videoUrl;
                                const result = buildVideoSnippet(input, videoMode);
                                if (!result) {
                                    return;
                                }
                                insertSnippet(result.snippet);
                                setVideoUrl('');
                                setRawIframe('');
                                setVideoOpen(false);
                            }}
                        >
                            Insert video
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={linkOpen} onOpenChange={setLinkOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Insert link</DialogTitle>
                        <DialogDescription>Add link text and destination URL.</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="link-text">Text</Label>
                            <Input
                                id="link-text"
                                value={linkText}
                                onChange={(event) => setLinkText(event.target.value)}
                                placeholder="Link label"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="link-url">URL</Label>
                            <Input
                                id="link-url"
                                value={linkUrl}
                                onChange={(event) => setLinkUrl(event.target.value)}
                                placeholder="https://"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            onClick={() => {
                                if (!linkUrl.trim()) {
                                    return;
                                }
                                insertSnippet(buildLinkSnippet(linkText, linkUrl));
                                setLinkText('');
                                setLinkUrl('https://');
                                setLinkOpen(false);
                            }}
                        >
                            Insert link
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
