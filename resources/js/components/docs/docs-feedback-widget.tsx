import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type Props = {
    pageId: number;
    feedbackUrl: string;
    compact?: boolean;
    className?: string;
};

export function DocsFeedbackWidget({ pageId, feedbackUrl, compact = false, className }: Props) {
    const [feedbackSent, setFeedbackSent] = useState(false);
    const [expanded, setExpanded] = useState(false);
    const feedback = useForm({ page_id: pageId, helpful: true, comment: '' });

    useEffect(() => {
        feedback.setData({ page_id: pageId, helpful: true, comment: '' });
        feedback.clearErrors();
        setFeedbackSent(false);
        setExpanded(false);
    }, [pageId]);

    const submit = (helpful: boolean) => {
        feedback.setData('helpful', helpful);
        feedback.post(feedbackUrl, {
            preserveScroll: true,
            onSuccess: () => {
                setFeedbackSent(true);
                feedback.reset();
                feedback.setData({ page_id: pageId, helpful: true, comment: '' });
            },
        });
    };

    if (feedbackSent) {
        return (
            <p className={cn('text-sm font-medium text-primary', className)}>
                {compact ? 'Thanks!' : 'Thanks for your feedback!'}
            </p>
        );
    }

    if (compact) {
        return (
            <div className={cn('flex items-center gap-2 text-sm', className)}>
                <span className="text-muted-foreground">Was this helpful?</span>
                <button
                    type="button"
                    className="rounded-md px-1.5 py-0.5 text-lg transition-transform hover:scale-110"
                    aria-label="Yes, helpful"
                    onClick={() => submit(true)}
                    disabled={feedback.processing}
                >
                    👍
                </button>
                <button
                    type="button"
                    className="rounded-md px-1.5 py-0.5 text-lg transition-transform hover:scale-110"
                    aria-label="No, not helpful"
                    onClick={() => {
                        setExpanded(true);
                        feedback.setData('helpful', false);
                    }}
                    disabled={feedback.processing}
                >
                    👎
                </button>
                {expanded && (
                    <form
                        className="flex items-center gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submit(false);
                        }}
                    >
                        <Input
                            placeholder="Tell us more (optional)"
                            value={feedback.data.comment}
                            className="h-8 w-40 text-xs"
                            onChange={(event) => feedback.setData('comment', event.target.value)}
                        />
                        <Button type="submit" size="sm" disabled={feedback.processing}>
                            Send
                        </Button>
                    </form>
                )}
            </div>
        );
    }

    return (
        <form
            className={cn('space-y-3 rounded-xl border p-4', className)}
            onSubmit={(event) => {
                event.preventDefault();
                submit(feedback.data.helpful);
            }}
        >
            <p className="text-sm font-medium">Was this page helpful?</p>
            <div className="flex gap-2">
                <Button
                    type="button"
                    variant={feedback.data.helpful === true ? 'default' : 'outline'}
                    size="sm"
                    aria-pressed={feedback.data.helpful === true}
                    onClick={() => {
                        feedback.setData('helpful', true);
                        feedback.clearErrors('helpful');
                    }}
                >
                    Yes
                </Button>
                <Button
                    type="button"
                    variant={feedback.data.helpful === false ? 'default' : 'outline'}
                    size="sm"
                    aria-pressed={feedback.data.helpful === false}
                    onClick={() => {
                        feedback.setData('helpful', false);
                        feedback.clearErrors('helpful');
                    }}
                >
                    No
                </Button>
            </div>
            {feedback.errors.helpful && <p className="text-sm text-destructive">{feedback.errors.helpful}</p>}
            <Input
                placeholder="Optional comment"
                value={feedback.data.comment}
                onChange={(event) => feedback.setData('comment', event.target.value)}
            />
            {feedback.errors.comment && <p className="text-sm text-destructive">{feedback.errors.comment}</p>}
            <Button type="submit" size="sm" disabled={feedback.processing}>
                {feedback.processing ? 'Sending…' : 'Send feedback'}
            </Button>
        </form>
    );
}
