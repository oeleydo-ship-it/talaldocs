import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    BookOpen,
    CreditCard,
    FolderOpen,
    Plus,
    Sparkles,
    Zap,
} from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { VisibilityBadge } from '@/components/visibility-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type Project = {
    id: number;
    name: string;
    slug: string;
    subdomain: string;
    public_url: string;
    visibility: string;
};

type Workspace = {
    id: number;
    name: string;
    slug: string;
    company_name: string | null;
    role: string | null;
    plan?: string | null;
};

type Props = {
    workspace: Workspace;
    projects: Project[];
};

export default function Dashboard({ workspace, projects }: Props) {
    const isNewUser = projects.length === 0;

    return (
        <>
            <Head title="Dashboard" />
            <PageContainer>
                <PageHeader
                    title={isNewUser ? `Welcome to ${workspace.name}` : workspace.name}
                    description={
                        <>
                            {workspace.company_name ? `${workspace.company_name} · ` : ''}
                            You are an {workspace.role} of this workspace.
                        </>
                    }
                    actions={
                        <Button asChild>
                            <Link href="/projects">
                                <Plus className="size-4" />
                                New project
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-4 md:grid-cols-3">
                    <StatCard
                        title="Projects"
                        value={projects.length}
                        description="Documentation sites in this workspace"
                        icon={BookOpen}
                    />
                    <StatCard
                        title="Plan"
                        value={<Badge variant="secondary">{workspace.plan ?? 'Free'}</Badge>}
                        description="Limits enforced on the server"
                        icon={CreditCard}
                    />
                    <StatCard
                        title="Quick action"
                        value={
                            <Button asChild size="sm" className="mt-1">
                                <Link
                                    href={
                                        projects[0]
                                            ? `/projects/${projects[0].id}/editor`
                                            : '/projects'
                                    }
                                >
                                    <Zap className="size-4" />
                                    Open editor
                                </Link>
                            </Button>
                        }
                        description="Jump back into writing"
                        icon={Sparkles}
                    />
                </div>

                {isNewUser ? (
                    <EmptyState
                        icon={FolderOpen}
                        title="No projects yet"
                        description="Create your first documentation project to start writing and publishing."
                        action={
                            <Button asChild>
                                <Link href="/projects">
                                    <Plus className="size-4" />
                                    Create project
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0">
                            <div>
                                <CardTitle>Recent projects</CardTitle>
                                <CardDescription>
                                    Published sites are live at your subdomain or custom domain.
                                </CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href="/projects">
                                    View all
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="divide-y rounded-lg border bg-muted/20">
                            {projects.slice(0, 5).map((project) => (
                                <div
                                    key={project.id}
                                    className="flex flex-col justify-between gap-3 p-4 transition-colors first:rounded-t-lg last:rounded-b-lg hover:bg-muted/40 sm:flex-row sm:items-center"
                                >
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <BookOpen className="size-4 shrink-0 text-muted-foreground" />
                                            <p className="font-medium">{project.name}</p>
                                            <VisibilityBadge visibility={project.visibility} />
                                        </div>
                                        <p className="mt-1 truncate text-sm text-muted-foreground">
                                            {project.public_url}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 gap-2">
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={`/projects/${project.id}`}>Overview</Link>
                                        </Button>
                                        <Button size="sm" asChild>
                                            <Link href={`/projects/${project.id}/editor`}>
                                                Editor
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </PageContainer>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
};
