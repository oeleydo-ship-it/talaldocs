import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ExternalLink,
    Megaphone,
    MoreHorizontal,
    Newspaper,
    Pencil,
    Plus,
    ScrollText,
    Trash2,
} from 'lucide-react';
import { useMemo, useState, type FormEvent } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type PostType = 'announcement' | 'changelog';
type PostStatus = 'draft' | 'published';
type TypeFilter = 'all' | PostType;
type StatusFilter = 'all' | PostStatus;

type PostItem = {
    id: number;
    type: PostType;
    title: string;
    slug: string;
    excerpt: string | null;
    markdown: string | null;
    status: PostStatus;
    published_at: string | null;
    updated_at: string | null;
};

type Props = {
    project: {
        id: number;
        name: string;
        public_url: string;
        docs_base_path: string;
    };
    posts: PostItem[];
    canEdit: boolean;
};

type FormState = {
    type: PostType;
    title: string;
    slug: string;
    excerpt: string;
    markdown: string;
    publish: boolean;
};

const emptyForm = (type: PostType = 'announcement'): FormState => ({
    type,
    title: '',
    slug: '',
    excerpt: '',
    markdown: '',
    publish: false,
});

function typeLabel(type: PostType): string {
    return type === 'announcement' ? 'Announcement' : 'Changelog';
}

function publicPath(docsBasePath: string, post: PostItem): string {
    const segment = post.type === 'announcement' ? 'announcements' : 'changelog';
    return `${docsBasePath}/${segment}/${post.slug}`;
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }
    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function ProjectPosts({ project, posts, canEdit }: Props) {
    const [typeFilter, setTypeFilter] = useState<TypeFilter>('all');
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
    const [editorOpen, setEditorOpen] = useState(false);
    const [editing, setEditing] = useState<PostItem | null>(null);
    const [bodyTab, setBodyTab] = useState<'write' | 'preview'>('write');

    const form = useForm<FormState>(emptyForm());

    const counts = useMemo(
        () => ({
            all: posts.length,
            announcement: posts.filter((post) => post.type === 'announcement').length,
            changelog: posts.filter((post) => post.type === 'changelog').length,
            draft: posts.filter((post) => post.status === 'draft').length,
            published: posts.filter((post) => post.status === 'published').length,
        }),
        [posts],
    );

    const filtered = useMemo(() => {
        return posts.filter((post) => {
            if (typeFilter !== 'all' && post.type !== typeFilter) {
                return false;
            }
            if (statusFilter !== 'all' && post.status !== statusFilter) {
                return false;
            }
            return true;
        });
    }, [posts, statusFilter, typeFilter]);

    const openCreate = (type: PostType = 'announcement') => {
        setEditing(null);
        setBodyTab('write');
        form.setData(emptyForm(type));
        form.clearErrors();
        setEditorOpen(true);
    };

    const openEdit = (post: PostItem) => {
        setEditing(post);
        setBodyTab('write');
        form.setData({
            type: post.type,
            title: post.title,
            slug: post.slug,
            excerpt: post.excerpt ?? '',
            markdown: post.markdown ?? '',
            publish: post.status === 'published',
        });
        form.clearErrors();
        setEditorOpen(true);
    };

    const closeEditor = () => {
        setEditorOpen(false);
        setEditing(null);
        form.reset();
        form.clearErrors();
    };

    const togglePublish = (post: PostItem) => {
        router.post(`/projects/${project.id}/posts/${post.id}/publish`, {
            publish: post.status !== 'published',
        });
    };

    const deletePost = (post: PostItem) => {
        if (!confirm(`Delete “${post.title}”?`)) {
            return;
        }

        router.delete(`/projects/${project.id}/posts/${post.id}`);
    };

    const submitEditor = (event: FormEvent) => {
        event.preventDefault();

        if (editing) {
            form.patch(`/projects/${project.id}/posts/${editing.id}`, {
                onSuccess: () => closeEditor(),
            });
            return;
        }

        form.post(`/projects/${project.id}/posts`, {
            onSuccess: () => closeEditor(),
        });
    };

    const emptyTitle =
        typeFilter === 'announcement'
            ? 'No announcements yet'
            : typeFilter === 'changelog'
              ? 'No changelog entries yet'
              : statusFilter === 'draft'
                ? 'No drafts'
                : statusFilter === 'published'
                  ? 'No published posts'
                  : 'No posts yet';

    return (
        <>
            <Head title={`Posts · ${project.name}`} />
            <PageContainer>
                <PageHeader
                    title="Posts"
                    description={
                        <>
                            Announcements and changelog for{' '}
                            <Link
                                href={`/projects/${project.id}`}
                                className="font-medium underline-offset-4 hover:underline"
                            >
                                {project.name}
                            </Link>
                            . Published posts appear on the public docs site.
                        </>
                    }
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a
                                    href={`${project.docs_base_path}/directory`}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink className="size-4" />
                                    Public directory
                                </a>
                            </Button>
                            {canEdit ? (
                                <Button onClick={() => openCreate()}>
                                    <Plus className="size-4" />
                                    New post
                                </Button>
                            ) : null}
                        </>
                    }
                />

                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="inline-flex flex-wrap gap-1 rounded-lg bg-muted/40 p-1">
                        {(
                            [
                                { id: 'all', label: 'All', count: counts.all },
                                { id: 'announcement', label: 'Announcements', count: counts.announcement },
                                { id: 'changelog', label: 'Changelog', count: counts.changelog },
                            ] as const
                        ).map((tab) => (
                            <button
                                key={tab.id}
                                type="button"
                                onClick={() => setTypeFilter(tab.id)}
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                    typeFilter === tab.id
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {tab.label}
                                <span className="tabular-nums text-muted-foreground">{tab.count}</span>
                            </button>
                        ))}
                    </div>

                    <div className="flex items-center gap-2">
                        <Label htmlFor="status-filter" className="sr-only">
                            Status
                        </Label>
                        <Select
                            value={statusFilter}
                            onValueChange={(value) => setStatusFilter(value as StatusFilter)}
                        >
                            <SelectTrigger id="status-filter" className="w-[160px]">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All statuses</SelectItem>
                                <SelectItem value="published">Published ({counts.published})</SelectItem>
                                <SelectItem value="draft">Draft ({counts.draft})</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {filtered.length === 0 ? (
                    <EmptyState
                        icon={Megaphone}
                        title={emptyTitle}
                        description="Write Markdown content, then publish it to announcements or the changelog on your public docs site."
                        action={
                            canEdit ? (
                                <Button
                                    onClick={() =>
                                        openCreate(
                                            typeFilter === 'changelog' ? 'changelog' : 'announcement',
                                        )
                                    }
                                >
                                    <Plus className="size-4" />
                                    New post
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Title</TableHead>
                                    <TableHead className="hidden sm:table-cell">Type</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="hidden md:table-cell">Published</TableHead>
                                    <TableHead className="w-[1%] text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filtered.map((post) => (
                                    <TableRow key={post.id}>
                                        <TableCell>
                                            <div className="space-y-0.5">
                                                <div className="font-medium">{post.title}</div>
                                                <div className="text-xs text-muted-foreground">
                                                    /{post.slug}
                                                    {post.excerpt ? ` · ${post.excerpt}` : ''}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="hidden sm:table-cell">
                                            <Badge variant="outline" className="font-normal capitalize">
                                                {post.type === 'announcement' ? (
                                                    <Newspaper className="size-3" />
                                                ) : (
                                                    <ScrollText className="size-3" />
                                                )}
                                                {typeLabel(post.type)}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    post.status === 'published' ? 'default' : 'secondary'
                                                }
                                                className="capitalize"
                                            >
                                                {post.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="hidden text-muted-foreground md:table-cell">
                                            {formatDate(post.published_at)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {canEdit ? (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => openEdit(post)}
                                                    >
                                                        <Pencil className="size-4" />
                                                        <span className="sr-only sm:not-sr-only">Edit</span>
                                                    </Button>
                                                ) : null}
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button size="icon" variant="ghost" className="size-8">
                                                            <MoreHorizontal className="size-4" />
                                                            <span className="sr-only">More actions</span>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        {canEdit ? (
                                                            <>
                                                                <DropdownMenuItem onClick={() => openEdit(post)}>
                                                                    <Pencil className="size-4" />
                                                                    Edit
                                                                </DropdownMenuItem>
                                                                <DropdownMenuItem
                                                                    onClick={() => togglePublish(post)}
                                                                >
                                                                    {post.status === 'published'
                                                                        ? 'Unpublish'
                                                                        : 'Publish'}
                                                                </DropdownMenuItem>
                                                            </>
                                                        ) : null}
                                                        {post.status === 'published' ? (
                                                            <DropdownMenuItem asChild>
                                                                <a
                                                                    href={publicPath(
                                                                        project.docs_base_path,
                                                                        post,
                                                                    )}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                >
                                                                    <ExternalLink className="size-4" />
                                                                    View public
                                                                </a>
                                                            </DropdownMenuItem>
                                                        ) : null}
                                                        {canEdit ? (
                                                            <>
                                                                <DropdownMenuSeparator />
                                                                <DropdownMenuItem
                                                                    className="text-destructive focus:text-destructive"
                                                                    onClick={() => deletePost(post)}
                                                                >
                                                                    <Trash2 className="size-4" />
                                                                    Delete
                                                                </DropdownMenuItem>
                                                            </>
                                                        ) : null}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </PageContainer>

            <Sheet
                open={editorOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        closeEditor();
                    }
                }}
            >
                <SheetContent
                    side="right"
                    className="flex w-full flex-col gap-0 overflow-y-auto p-0 sm:max-w-2xl"
                >
                    <SheetHeader className="border-b">
                        <SheetTitle>{editing ? 'Edit post' : 'New post'}</SheetTitle>
                        <SheetDescription>
                            Write Markdown for announcements or changelog entries. Publish when ready.
                        </SheetDescription>
                    </SheetHeader>

                    <form className="flex flex-1 flex-col" onSubmit={submitEditor}>
                        <div className="flex-1 space-y-4 p-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="post-type">Type</Label>
                                    <Select
                                        value={form.data.type}
                                        onValueChange={(value) =>
                                            form.setData('type', value as PostType)
                                        }
                                    >
                                        <SelectTrigger id="post-type" className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="announcement">Announcement</SelectItem>
                                            <SelectItem value="changelog">Changelog</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="post-slug">Slug</Label>
                                    <Input
                                        id="post-slug"
                                        placeholder="auto-from-title"
                                        value={form.data.slug}
                                        onChange={(event) => form.setData('slug', event.target.value)}
                                    />
                                    {form.errors.slug ? (
                                        <p className="text-xs text-destructive">{form.errors.slug}</p>
                                    ) : null}
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="post-title">Title</Label>
                                <Input
                                    id="post-title"
                                    value={form.data.title}
                                    onChange={(event) => form.setData('title', event.target.value)}
                                    required
                                />
                                {form.errors.title ? (
                                    <p className="text-xs text-destructive">{form.errors.title}</p>
                                ) : null}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="post-excerpt">Excerpt</Label>
                                <Input
                                    id="post-excerpt"
                                    placeholder="Short summary for listings"
                                    value={form.data.excerpt}
                                    onChange={(event) => form.setData('excerpt', event.target.value)}
                                />
                                {form.errors.excerpt ? (
                                    <p className="text-xs text-destructive">{form.errors.excerpt}</p>
                                ) : null}
                            </div>

                            <div className="space-y-1.5">
                                <div className="flex items-center justify-between gap-2">
                                    <Label htmlFor="post-markdown">Body</Label>
                                    <Tabs
                                        value={bodyTab}
                                        onValueChange={(value) =>
                                            setBodyTab(value as 'write' | 'preview')
                                        }
                                    >
                                        <TabsList className="h-8">
                                            <TabsTrigger value="write" className="px-2 text-xs">
                                                Write
                                            </TabsTrigger>
                                            <TabsTrigger value="preview" className="px-2 text-xs">
                                                Preview
                                            </TabsTrigger>
                                        </TabsList>
                                    </Tabs>
                                </div>
                                {bodyTab === 'write' ? (
                                    <Textarea
                                        id="post-markdown"
                                        rows={14}
                                        className="min-h-[280px] font-mono text-sm"
                                        placeholder={"## Heading\n\nWrite Markdown here…"}
                                        value={form.data.markdown}
                                        onChange={(event) =>
                                            form.setData('markdown', event.target.value)
                                        }
                                    />
                                ) : (
                                    <div className="min-h-[280px] rounded-md border bg-muted/20 px-3 py-2 text-sm whitespace-pre-wrap">
                                        {form.data.markdown.trim() !== '' ? (
                                            form.data.markdown
                                        ) : (
                                            <span className="text-muted-foreground">
                                                Nothing to preview yet.
                                            </span>
                                        )}
                                    </div>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    Markdown is rendered on the public site when you publish.
                                </p>
                                {form.errors.markdown ? (
                                    <p className="text-xs text-destructive">{form.errors.markdown}</p>
                                ) : null}
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={form.data.publish}
                                    onCheckedChange={(checked) =>
                                        form.setData('publish', checked === true)
                                    }
                                />
                                {editing ? 'Published' : 'Publish immediately'}
                            </label>
                        </div>

                        <SheetFooter className="border-t sm:flex-row sm:justify-end">
                            <Button type="button" variant="outline" onClick={closeEditor}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? editing
                                        ? 'Saving…'
                                        : 'Creating…'
                                    : editing
                                      ? 'Save post'
                                      : 'Create post'}
                            </Button>
                        </SheetFooter>
                    </form>
                </SheetContent>
            </Sheet>
        </>
    );
}

ProjectPosts.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Projects', href: '/projects' },
        { title: 'Posts', href: '#' },
    ],
};
