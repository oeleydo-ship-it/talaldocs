import { useCallback, useEffect, useState } from 'react';

export type AskSource = { title: string; slug: string; url: string };

export type AskResult = {
    answer: string;
    answer_html: string;
    sources: AskSource[];
};

export function useDocsAsk(askUrl: string, enabled: boolean) {
    const [open, setOpen] = useState(false);
    const [question, setQuestion] = useState('');
    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState<AskResult | null>(null);
    const [error, setError] = useState<string | null>(null);

    const openAsk = useCallback(() => {
        setOpen(true);
    }, []);

    const closeAsk = useCallback(() => {
        setOpen(false);
        setQuestion('');
        setResult(null);
        setError(null);
        setLoading(false);
    }, []);

    const submit = useCallback(
        async (value?: string) => {
            const trimmed = (value ?? question).trim();

            if (trimmed.length < 3) {
                setError('Please enter a question with at least 3 characters.');
                return;
            }

            if (!enabled) {
                setError('AI Ask is not configured for this site.');
                return;
            }

            setQuestion(trimmed);
            setLoading(true);
            setError(null);
            setResult(null);

            try {
                const response = await fetch(askUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN':
                            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ question: trimmed }),
                });

                const payload = (await response.json()) as AskResult & { message?: string };

                if (!response.ok) {
                    setError(payload.message ?? 'Something went wrong. Please try again.');
                    return;
                }

                setResult(payload);
            } catch {
                setError('Unable to reach the server. Please try again.');
            } finally {
                setLoading(false);
            }
        },
        [askUrl, enabled, question],
    );

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape' && open) {
                closeAsk();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [closeAsk, open]);

    return {
        open,
        question,
        loading,
        result,
        error,
        enabled,
        setQuestion,
        openAsk,
        closeAsk,
        submit,
    };
}
