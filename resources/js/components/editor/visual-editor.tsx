import { useEffect, useRef } from 'react';
import TurndownService from 'turndown';
import { Bold, Heading2, Italic, Link, List, ListOrdered } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    html: string;
    disabled?: boolean;
    onChange: (markdown: string) => void;
};

const turndown = new TurndownService({
    headingStyle: 'atx',
    codeBlockStyle: 'fenced',
    emDelimiter: '*',
});

turndown.addRule('callouts', {
    filter: (node) => node.nodeName === 'ASIDE' && node.classList.contains('callout'),
    replacement: (_content, node) => {
        const element = node as HTMLElement;
        const type = element.dataset.calloutType ?? 'info';
        const inner = element.querySelector('.callout-body')?.innerHTML ?? element.innerHTML;
        const text = turndown.turndown(inner);
        return `\n\n:::${type}\n${text}\n:::\n\n`;
    },
});

function exec(command: string, value?: string) {
    document.execCommand(command, false, value);
}

export function VisualEditor({ html, disabled, onChange }: Props) {
    const editorRef = useRef<HTMLDivElement>(null);
    const syncingRef = useRef(false);

    useEffect(() => {
        const element = editorRef.current;

        if (!element || syncingRef.current) {
            return;
        }

        if (element.innerHTML !== html) {
            element.innerHTML = html;
        }
    }, [html]);

    const emitChange = () => {
        const element = editorRef.current;

        if (!element || disabled) {
            return;
        }

        syncingRef.current = true;
        onChange(turndown.turndown(element.innerHTML));
        window.requestAnimationFrame(() => {
            syncingRef.current = false;
        });
    };

    const insertLink = () => {
        const url = window.prompt('Link URL');

        if (url) {
            exec('createLink', url);
            emitChange();
        }
    };

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            {!disabled && (
                <div className="flex flex-wrap gap-1 border-b bg-muted/20 p-2">
                    <Button type="button" size="sm" variant="ghost" onClick={() => { exec('bold'); emitChange(); }}>
                        <Bold className="size-4" />
                    </Button>
                    <Button type="button" size="sm" variant="ghost" onClick={() => { exec('italic'); emitChange(); }}>
                        <Italic className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            exec('formatBlock', 'h2');
                            emitChange();
                        }}
                    >
                        <Heading2 className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            exec('insertUnorderedList');
                            emitChange();
                        }}
                    >
                        <List className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            exec('insertOrderedList');
                            emitChange();
                        }}
                    >
                        <ListOrdered className="size-4" />
                    </Button>
                    <Button type="button" size="sm" variant="ghost" onClick={insertLink}>
                        <Link className="size-4" />
                    </Button>
                </div>
            )}
            <div
                ref={editorRef}
                contentEditable={!disabled}
                suppressContentEditableWarning
                className="prose dark:prose-invert min-h-0 flex-1 overflow-auto p-6 focus:outline-none"
                onInput={emitChange}
                onBlur={emitChange}
            />
        </div>
    );
}
