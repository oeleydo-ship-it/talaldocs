import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    children: ReactNode;
    className?: string;
};

export function PageContainer({ children, className }: Props) {
    return (
        <div className={cn('flex h-full flex-1 flex-col gap-6 p-6', className)}>
            {children}
        </div>
    );
}
