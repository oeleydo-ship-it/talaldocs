import { Head } from '@inertiajs/react';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type Log = {
    id: number;
    action: string;
    user: string | null;
    subject_type: string | null;
    subject_id: number | null;
    metadata: Record<string, unknown> | null;
    created_at: string | null;
};

type Props = { logs: Log[] };

export default function AuditIndex({ logs }: Props) {
    return (
        <>
            <Head title="Audit log" />
            <PageContainer>
                <PageHeader
                    title="Audit log"
                    description="Recent workspace activity for compliance and troubleshooting."
                />

                <Card className="shadow-sm">
                    <CardHeader>
                        <CardTitle>Events</CardTitle>
                        <CardDescription>Last 100 actions in this workspace.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {logs.length === 0 && (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                No audit events yet.
                            </p>
                        )}
                        {logs.map((log) => (
                            <div
                                key={log.id}
                                className="rounded-lg border bg-muted/20 p-4 text-sm transition-colors hover:bg-muted/40"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="outline" className="font-normal">
                                        {log.action}
                                    </Badge>
                                    <span className="text-muted-foreground">{log.user ?? 'System'}</span>
                                </div>
                                <p className="mt-1.5 text-xs text-muted-foreground">
                                    {log.created_at ? new Date(log.created_at).toLocaleString() : ''}
                                    {log.subject_type ? ` · ${log.subject_type} #${log.subject_id}` : ''}
                                </p>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </PageContainer>
        </>
    );
}

AuditIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Audit log', href: '/audit' },
    ],
};
