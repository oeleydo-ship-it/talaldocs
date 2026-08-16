import { Head, Link, router, useForm } from '@inertiajs/react';
import { ExternalLink, FolderOpen, Globe, Plus, Settings, Trash2, Users } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { VisibilityBadge } from '@/components/visibility-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type Project = {
    id: number;
    name: string;
    slug: string;
    subdomain: string;
    public_url: string;
    visibility: string;
    created_at: string | null;
};

type Props = {
    workspace: {
        id: number;
        name: string;
    };
    projects: Project[];
    canCreate: boolean;
    projectLimit: number | null;
};

export default function ProjectsIndex({ workspace, projects, canCreate, projectLimit }: Props) {
    const form = useForm({ name: '', subdomain: '' });

    return (
        <>
            <Head title="Projects" />
            <PageContainer>
                <PageHeader
                    title="Projects"
                    description={
                        <>
                            Documentation sites in {workspace.name}
                            {projectLimit !== null && ` · ${projects.length}/${projectLimit} used`}
                        </>
                    }
                    actions={
                        <Button variant="outline" asChild>
                            <Link href="/members">
                                <Users className="size-4" />
                                Manage members
                            </Link>
                        </Button>
                    }
                />

                {canCreate ? (
                    <Card className="shadow-sm">
                        <CardHeader>
                            <CardTitle>Create a project</CardTitle>
                            <CardDescription>
                                Each project gets its own editor, versions, languages, and public docs URL.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                className="flex flex-wrap gap-2"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    form.post('/projects', {
                                        onSuccess: () => form.reset(),
                                    });
                                }}
                            >
                                <Input
                                    placeholder="API docs"
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                    className="max-w-xs"
                                />
                                <Input
                                    placeholder="subdomain"
                                    value={form.data.subdomain}
                                    onChange={(event) =>
                                        form.setData('subdomain', event.target.value)
                                    }
                                    className="max-w-xs"
                                />
                                <Button type="submit" disabled={form.processing}>
                                    <Plus className="size-4" />
                                    Create
                                </Button>
                            </form>
                            {form.errors.plan && (
                                <p className="mt-2 text-sm text-destructive">{form.errors.plan}</p>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="border-amber-500/30 bg-amber-500/5 shadow-sm">
                        <CardContent className="py-6 text-sm text-muted-foreground">
                            Your plan limit is reached.{' '}
                            <Link href="/billing" className="font-medium text-foreground underline">
                                Upgrade billing
                            </Link>{' '}
                            to add more projects.
                        </CardContent>
                    </Card>
                )}

                {projects.length === 0 ? (
                    <EmptyState
                        icon={FolderOpen}
                        title="Create your first project"
                        description="Projects hold your documentation pages, branding, and public site URL."
                        action={
                            canCreate ? (
                                <Button
                                    onClick={() =>
                                        document
                                            .querySelector<HTMLInputElement>(
                                                'input[placeholder="API docs"]',
                                            )
                                            ?.focus()
                                    }
                                >
                                    <Plus className="size-4" />
                                    Start below
                                </Button>
                            ) : (
                                <Button asChild>
                                    <Link href="/billing">Upgrade plan</Link>
                                </Button>
                            )
                        }
                    />
                ) : (
                    <Card className="overflow-hidden shadow-sm">
                        <CardHeader>
                            <CardTitle>All projects</CardTitle>
                            <CardDescription>
                                Manage documentation sites, open the editor, or visit the public URL.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-0 pb-0">
                            <Table>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead>Project</TableHead>
                                        <TableHead className="hidden md:table-cell">Subdomain</TableHead>
                                        <TableHead>Visibility</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {projects.map((project) => (
                                        <TableRow key={project.id}>
                                            <TableCell>
                                                <div className="min-w-0">
                                                    <Link
                                                        href={`/projects/${project.id}`}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {project.name}
                                                    </Link>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {project.public_url}
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell className="hidden md:table-cell">
                                                <span className="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
                                                    <Globe className="size-3.5" />
                                                    {project.subdomain}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <VisibilityBadge visibility={project.visibility} />
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap justify-end gap-1">
                                                    <Button size="sm" asChild>
                                                        <Link href={`/projects/${project.id}/editor`}>
                                                            Editor
                                                        </Link>
                                                    </Button>
                                                    <Button size="sm" variant="outline" asChild>
                                                        <Link href={`/projects/${project.id}/settings`}>
                                                            <Settings className="size-3.5" />
                                                        </Link>
                                                    </Button>
                                                    <Button size="sm" variant="ghost" asChild>
                                                        <a
                                                            href={project.public_url}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            <ExternalLink className="size-3.5" />
                                                        </a>
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() => {
                                                            if (
                                                                confirm(
                                                                    `Delete "${project.name}"? This archives the project and its pages.`,
                                                                )
                                                            ) {
                                                                router.delete(`/projects/${project.id}`);
                                                            }
                                                        }}
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </PageContainer>
        </>
    );
}

ProjectsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Projects', href: '/projects' },
    ],
};
