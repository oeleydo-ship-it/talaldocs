import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card, CardContent, CardDescription, CardHeader } from '@/components/ui/card';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    value: ReactNode;
    description?: ReactNode;
    icon?: LucideIcon;
    className?: string;
};

export function StatCard({ title, value, description, icon: Icon, className }: Props) {
    return (
        <Card className={cn('shadow-sm', className)}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardDescription className="text-sm font-medium text-foreground/80">
                    {title}
                </CardDescription>
                {Icon && <Icon className="size-4 text-muted-foreground" />}
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-semibold tracking-tight">{value}</div>
                {description && (
                    <div className="mt-1 text-xs text-muted-foreground">{description}</div>
                )}
            </CardContent>
        </Card>
    );
}
