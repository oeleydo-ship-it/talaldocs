import { Head, Link } from '@inertiajs/react';
import { BarChart3, Download, MessageSquare, ThumbsDown, ThumbsUp } from 'lucide-react';
import { SimpleBarChart, SimpleLineChart } from '@/components/analytics/simple-charts';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type Props = {
    views30d: number;
    viewsByDay: { date: string; views: number }[];
    helpful: number;
    notHelpful: number;
    topPages: { page_id: number; title: string; views: number }[];
    feedback: { id: number; helpful: boolean; comment: string | null; created_at: string }[];
};

export default function AnalyticsShow({ views30d, viewsByDay, helpful, notHelpful, topPages, feedback }: Props) {
    return (
        <>
            <Head title="Analytics" />
            <PageContainer>
                <PageHeader
                    title="Analytics"
                    description="Documentation engagement across your workspace projects. Page views store only a hashed visitor identifier — no IP addresses or PII."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href="/analytics/export">
                                <Download className="size-4" />
                                Export CSV
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-4 md:grid-cols-3">
                    <StatCard title="Views (30d)" value={views30d} icon={BarChart3} />
                    <StatCard title="Helpful" value={helpful} icon={ThumbsUp} />
                    <StatCard title="Not helpful" value={notHelpful} icon={ThumbsDown} />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card className="shadow-sm">
                        <CardHeader>
                            <CardTitle>Daily views</CardTitle>
                            <CardDescription>Last 30 days.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <SimpleLineChart data={viewsByDay} label="Views per day" />
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader>
                            <CardTitle>Top pages</CardTitle>
                            <CardDescription>Most viewed pages in the last 30 days.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <SimpleBarChart
                                data={topPages.map((page) => ({ title: page.title, views: page.views }))}
                                label="Page views"
                            />
                        </CardContent>
                    </Card>
                </div>

                <Card className="shadow-sm">
                    <CardHeader>
                        <CardTitle>Top pages</CardTitle>
                        <CardDescription>Tabular breakdown.</CardDescription>
                    </CardHeader>
                    <CardContent className="px-0 pb-0">
                        {topPages.length === 0 ? (
                            <p className="px-6 pb-6 text-sm text-muted-foreground">No views yet.</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead>Page</TableHead>
                                        <TableHead className="text-right">Views</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {topPages.map((page) => (
                                        <TableRow key={page.page_id}>
                                            <TableCell className="font-medium">{page.title}</TableCell>
                                            <TableCell className="text-right text-muted-foreground">
                                                {page.views}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Card className="shadow-sm">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <MessageSquare className="size-4" />
                            Recent feedback
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {feedback.length === 0 && (
                            <p className="text-sm text-muted-foreground">No feedback yet.</p>
                        )}
                        {feedback.map((item) => (
                            <div
                                key={item.id}
                                className="flex flex-wrap items-start gap-2 rounded-lg border bg-muted/20 p-3 text-sm"
                            >
                                <Badge variant={item.helpful ? 'default' : 'secondary'}>
                                    {item.helpful ? 'Helpful' : 'Not helpful'}
                                </Badge>
                                {item.comment && (
                                    <span className="text-muted-foreground">{item.comment}</span>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </PageContainer>
        </>
    );
}

AnalyticsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Analytics', href: '/analytics' },
    ],
};
