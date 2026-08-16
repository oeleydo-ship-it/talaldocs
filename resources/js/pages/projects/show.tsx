import { Head, Link, router } from '@inertiajs/react';
import {
    ExternalLink,
    FileText,
    FolderOpen,
    Globe,
    Languages,
    Megaphone,
    Settings,
    Users,
} from 'lucide-react';
import { GenerateWithAiDialog } from '@/components/generate-with-ai-dialog';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { VisibilityBadge } from '@/components/visibility-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type Props = {
    project: {
        id: number;
        name: string;
        slug: string;
        subdomain: string;
        visibility: string;
        public_url: string;
        pages_count: number;
        published_pages_count: number;
        versions_count: number;
        languages_count: number;
    };
    appDomain: string;
    ai: { configured: boolean; remaining: number | null; unlimited: boolean };
};

export default function ProjectShow({ project, appDomain, ai }: Props) {
    return (
        <>
            <Head title={project.name} />
            <PageContainer>
                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="border-b bg-muted/30 px-6 py-8">
                        <PageHeader
                            title={project.name}
                            description={
                                <span className="inline-flex items-center gap-1.5">
                                    <Globe className="size-3.5" />
                                    {project.subdomain}.{appDomain}
                                </span>
                            }
                            actions={
                                <>
                                    <VisibilityBadge visibility={project.visibility} />
                                    <Button asChild>
                                        <Link href={`/projects/${project.id}/editor`}>
                                            <FolderOpen className="size-4" />
                                            Open editor
                                        </Link>
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <Link href={`/projects/${project.id}/settings`}>
                                            <Settings className="size-4" />
                                            Settings
                                        </Link>
                                    </Button>
                                    <GenerateWithAiDialog
                                        projectId={project.id}
                                        ai={ai}
                                        variant="secondary"
                                        size="default"
                                        onComplete={() => router.reload()}
                                    />
                                    <Button variant="outline" asChild>
                                        <a href={project.public_url} target="_blank" rel="noreferrer">
                                            <ExternalLink className="size-4" />
                                            Public site
                                        </a>
                                    </Button>
                                </>
                            }
                        />
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard title="Pages" value={project.pages_count} icon={FileText} />
                    <StatCard title="Published" value={project.published_pages_count} icon={Globe} />
                    <StatCard title="Versions" value={project.versions_count} icon={FolderOpen} />
                    <StatCard title="Languages" value={project.languages_count} icon={Languages} />
                </div>

                <Card className="shadow-sm">
                    <CardHeader className="flex flex-row items-start justify-between gap-4 space-y-0">
                        <div className="space-y-1.5">
                            <CardTitle className="flex items-center gap-2">
                                <Megaphone className="size-4" />
                                Posts
                            </CardTitle>
                            <CardDescription>
                                Manage announcements and changelog entries for this project. Published posts
                                appear on the public docs directory, announcements, and changelog pages.
                            </CardDescription>
                        </div>
                        <Button asChild>
                            <Link href={`/projects/${project.id}/posts`}>
                                Manage posts
                            </Link>
                        </Button>
                    </CardHeader>
                </Card>

                <Card className="shadow-sm">
                    <CardHeader>
                        <CardTitle>Get started</CardTitle>
                        <CardDescription>
                            Write pages in the editor, publish them, and share the public docs URL.
                            Workspace members can collaborate based on their role.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="rounded-lg border bg-muted/20 px-4 py-3 text-sm text-muted-foreground">
                            {project.public_url}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button asChild>
                                <Link href={`/projects/${project.id}/editor`}>Manage pages</Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={`/projects/${project.id}/settings`}>
                                    Branding & domains
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={`/projects/${project.id}/posts`}>
                                    <Megaphone className="size-4" />
                                    Posts
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href="/members">
                                    <Users className="size-4" />
                                    Members
                                </Link>
                            </Button>
                            <Button variant="ghost" asChild>
                                <Link href="/projects">Back to projects</Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </PageContainer>
        </>
    );
}

ProjectShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Projects', href: '/projects' },
        { title: 'Project', href: '#' },
    ],
};
