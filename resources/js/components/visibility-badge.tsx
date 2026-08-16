import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type Props = {
    visibility: string;
    className?: string;
};

export function VisibilityBadge({ visibility, className }: Props) {
    const normalized = visibility.toLowerCase();

    return (
        <Badge
            variant="outline"
            className={cn(
                'font-normal capitalize',
                normalized === 'public' && 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
                normalized === 'private' && 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400',
                normalized === 'password' && 'border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-400',
                className,
            )}
        >
            {visibility}
        </Badge>
    );
}
