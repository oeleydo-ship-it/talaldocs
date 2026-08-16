import type { LucideIcon } from 'lucide-react';
import { FileText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description: string;
    action?: ReactNode;
    icon?: LucideIcon;
    className?: string;
};

export function EmptyState({
    title,
    description,
    action,
    icon: Icon = FileText,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center rounded-xl border border-dashed border-border/80 bg-muted/30 px-6 py-16 text-center shadow-sm',
                className,
            )}
        >
            <div className="mb-4 inline-flex size-12 items-center justify-center rounded-xl border bg-background shadow-sm">
                <Icon className="size-5 text-muted-foreground" />
            </div>
            <h3 className="text-lg font-medium tracking-tight">{title}</h3>
            <p className="mt-2 max-w-md text-sm text-muted-foreground">{description}</p>
            {action && <div className="mt-6">{action}</div>}
        </div>
    );
}
