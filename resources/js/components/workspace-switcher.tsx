import { Building2, Check, ChevronDown } from 'lucide-react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type WorkspaceSummary = { id: number; name: string; slug: string };

export function WorkspaceSwitcher() {
    const { workspace, workspaces } = usePage<{
        workspace: WorkspaceSummary | null;
        workspaces?: WorkspaceSummary[];
    }>().props;

    const items = workspaces ?? (workspace ? [workspace] : []);

    if (!workspace || items.length === 0) {
        return null;
    }

    const switchWorkspace = (id: number) => {
        if (id === workspace.id) {
            return;
        }

        router.post(`/workspace/switch/${id}`);
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className="h-9 w-full justify-between border-sidebar-border/70 bg-sidebar-accent/30 font-medium shadow-none hover:bg-sidebar-accent/50"
                >
                    <span className="flex min-w-0 items-center gap-2">
                        <Building2 className="size-4 shrink-0 text-muted-foreground" />
                        <span className="truncate">{workspace.name}</span>
                    </span>
                    <ChevronDown className="size-4 shrink-0 opacity-50" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-56">
                <DropdownMenuLabel>Workspaces</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {items.map((item) => (
                    <DropdownMenuItem
                        key={item.id}
                        onClick={() => switchWorkspace(item.id)}
                        className="flex items-center justify-between gap-2"
                    >
                        <span className="truncate">{item.name}</span>
                        {item.id === workspace.id && <Check className="size-4 shrink-0 text-primary" />}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
