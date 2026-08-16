import { useSyncExternalStore } from 'react';

export type DocsLayout = 'wide' | 'classic';

export type UseDocsLayoutOptions = {
    readonly defaultLayout?: DocsLayout;
    readonly storageKey?: string;
};

export type UseDocsLayoutReturn = {
    readonly layout: DocsLayout;
    readonly updateLayout: (mode: DocsLayout) => void;
    readonly toggleLayout: () => void;
};

const STORAGE_KEY = 'docs-layout';

const listeners = new Set<() => void>();

const getStoredLayout = (key: string, fallback: DocsLayout): DocsLayout => {
    if (typeof window === 'undefined') {
        return fallback;
    }

    const stored = localStorage.getItem(key);

    if (stored === 'classic' || stored === 'wide') {
        return stored;
    }

    return fallback;
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

export function initializeDocsLayout(): void {
    if (typeof window === 'undefined') {
        return;
    }

    getStoredLayout(STORAGE_KEY, 'wide');
}

export function useDocsLayout(options?: UseDocsLayoutOptions): UseDocsLayoutReturn {
    const key = options?.storageKey ?? STORAGE_KEY;
    const fallback = options?.defaultLayout ?? 'wide';

    const layout: DocsLayout = useSyncExternalStore(
        subscribe,
        () => getStoredLayout(key, fallback),
        () => fallback,
    );

    const updateLayout = (mode: DocsLayout): void => {
        localStorage.setItem(key, mode);
        notify();
    };

    const toggleLayout = (): void => {
        updateLayout(layout === 'wide' ? 'classic' : 'wide');
    };

    return { layout, updateLayout, toggleLayout } as const;
}

export function projectDocsLayoutToHookLayout(layout: 'centered' | 'wide'): DocsLayout {
    return layout === 'wide' ? 'wide' : 'classic';
}
