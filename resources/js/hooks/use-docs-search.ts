import { useCallback, useEffect, useRef, useState } from 'react';

type SearchResult = { title: string; slug: string; excerpt?: string | null };

type SearchScope = {
    versionId: number;
    languageId: number;
};

export function useDocsSearch(searchUrl: string, scope?: SearchScope) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const debounceRef = useRef<number | null>(null);
    const requestRef = useRef(0);

    const search = useCallback(
        (value: string) => {
            setQuery(value);

            if (debounceRef.current !== null) {
                window.clearTimeout(debounceRef.current);
            }

            if (value.length < 2) {
                setResults([]);
                setLoading(false);
                return;
            }

            setLoading(true);

            debounceRef.current = window.setTimeout(() => {
                const requestId = ++requestRef.current;

                const params = new URLSearchParams({ q: value });

                if (scope?.versionId) {
                    params.set('version_id', String(scope.versionId));
                }

                if (scope?.languageId) {
                    params.set('language_id', String(scope.languageId));
                }

                void fetch(`${searchUrl}?${params.toString()}`)
                    .then(async (response) => {
                        if (requestId !== requestRef.current) {
                            return;
                        }

                        if (response.ok) {
                            setResults((await response.json()) as SearchResult[]);
                        } else {
                            setResults([]);
                        }
                    })
                    .catch(() => {
                        if (requestId === requestRef.current) {
                            setResults([]);
                        }
                    })
                    .finally(() => {
                        if (requestId === requestRef.current) {
                            setLoading(false);
                        }
                    });
            }, 200);
        },
        [scope?.languageId, scope?.versionId, searchUrl],
    );

    const openSearch = useCallback(() => {
        setOpen(true);
    }, []);

    const closeSearch = useCallback(() => {
        setOpen(false);
        setQuery('');
        setResults([]);
        setLoading(false);
        requestRef.current += 1;

        if (debounceRef.current !== null) {
            window.clearTimeout(debounceRef.current);
        }
    }, []);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setOpen((current) => !current);
            }

            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => {
            window.removeEventListener('keydown', onKeyDown);

            if (debounceRef.current !== null) {
                window.clearTimeout(debounceRef.current);
            }
        };
    }, []);

    return {
        query,
        results,
        open,
        loading,
        search,
        openSearch,
        closeSearch,
        setOpen,
    };
}
