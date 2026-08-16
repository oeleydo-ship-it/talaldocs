import { ChevronDown } from 'lucide-react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import type { MarketingFaqItem } from '@/lib/marketing-faq';

type Props = {
    items: MarketingFaqItem[];
    className?: string;
};

export function MarketingFaqList({ items, className }: Props) {
    return (
        <div className={cn('divide-y rounded-xl border bg-card shadow-sm', className)}>
            {items.map((item) => (
                <Collapsible key={item.question}>
                    <CollapsibleTrigger className="group flex w-full items-start justify-between gap-4 px-5 py-4 text-left transition hover:bg-muted/40">
                        <span className="font-medium">{item.question}</span>
                        <ChevronDown className="mt-0.5 size-4 shrink-0 text-muted-foreground transition group-data-[state=open]:rotate-180" />
                    </CollapsibleTrigger>
                    <CollapsibleContent className="px-5 pb-4 text-sm leading-relaxed text-muted-foreground">
                        {item.answer}
                    </CollapsibleContent>
                </Collapsible>
            ))}
        </div>
    );
}
