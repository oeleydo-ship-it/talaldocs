export function parseYoutubeId(input: string): string | null {
    const trimmed = input.trim();
    if (/^[a-zA-Z0-9_-]{11}$/.test(trimmed)) {
        return trimmed;
    }
    const match = trimmed.match(
        /(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/,
    );
    return match?.[1] ?? null;
}

export function parseVimeoId(input: string): string | null {
    const trimmed = input.trim();
    if (/^\d+$/.test(trimmed)) {
        return trimmed;
    }
    const match = trimmed.match(/vimeo\.com\/(?:video\/)?(\d+)/);
    return match?.[1] ?? null;
}

export function parseLoomId(input: string): string | null {
    const trimmed = input.trim();
    const match = trimmed.match(/loom\.com\/(?:share|embed)\/([a-zA-Z0-9]+)/);
    if (match?.[1]) {
        return match[1];
    }
    if (/^[a-zA-Z0-9]+$/.test(trimmed)) {
        return trimmed;
    }
    return null;
}

export type VideoEmbedResult =
    | { kind: 'youtube'; snippet: string }
    | { kind: 'vimeo'; snippet: string }
    | { kind: 'loom'; snippet: string }
    | { kind: 'file'; snippet: string }
    | { kind: 'iframe'; snippet: string }
    | null;

export function buildVideoSnippet(input: string, mode: 'auto' | 'file' | 'iframe' = 'auto'): VideoEmbedResult {
    const trimmed = input.trim();
    if (!trimmed) {
        return null;
    }

    if (mode === 'iframe') {
        return { kind: 'iframe', snippet: `::embed\n${trimmed}\n::` };
    }

    if (mode === 'file') {
        return { kind: 'file', snippet: `::video[url](${trimmed})` };
    }

    const youtube = parseYoutubeId(trimmed);
    if (youtube) {
        return { kind: 'youtube', snippet: `::video[youtube](${youtube})` };
    }

    const vimeo = parseVimeoId(trimmed);
    if (vimeo) {
        return { kind: 'vimeo', snippet: `::video[vimeo](${vimeo})` };
    }

    const loom = parseLoomId(trimmed);
    if (loom) {
        return { kind: 'loom', snippet: `::video[loom](${loom})` };
    }

    if (/\.(mp4|webm|ogg)(\?|$)/i.test(trimmed)) {
        return { kind: 'file', snippet: `::video[url](${trimmed})` };
    }

    return null;
}

export function buildImageSnippet(url: string, alt = 'Image'): string {
    return `![${alt}](${url.trim()})`;
}

export function buildCalloutSnippet(type: 'tip' | 'warning' | 'info', body = 'Your message here'): string {
    return `:::${type}\n${body}\n:::`;
}

export function buildLinkSnippet(text: string, url: string): string {
    return `[${text || 'link'}](${url.trim()})`;
}
