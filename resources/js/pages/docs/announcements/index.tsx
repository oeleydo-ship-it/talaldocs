import { Head, Link } from '@inertiajs/react';
import { DocsPublicShell } from '@/components/docs/docs-public-shell';
import type { DocsShellProps, PublicPostListItem } from '@/pages/docs/types';

type Props = DocsShellProps & {
    heading: string;
    posts: PublicPostListItem[];
};

export default function PostListPage(props: Props) {
    const { project, heading, posts } = props;

    return (
        <>
            <Head title={`${heading} · ${project.name}`} />
            <DocsPublicShell {...props} title={heading} narrow>
                {posts.length === 0 ? (
                    <p className="text-muted-foreground">No published posts yet.</p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {posts.map((post) => (
                            <li key={post.slug}>
                                <Link
                                    href={post.url}
                                    className="block px-4 py-4 transition-colors hover:bg-muted/40 sm:px-5"
                                >
                                    <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                        <h2 className="text-base font-medium leading-snug sm:text-lg">
                                            {post.title}
                                        </h2>
                                        {post.published_at ? (
                                            <time className="shrink-0 text-xs text-muted-foreground">
                                                {new Date(post.published_at).toLocaleDateString(undefined, {
                                                    year: 'numeric',
                                                    month: 'short',
                                                    day: 'numeric',
                                                })}
                                            </time>
                                        ) : null}
                                    </div>
                                    {post.excerpt ? (
                                        <p className="mt-1.5 line-clamp-2 text-sm leading-relaxed text-muted-foreground">
                                            {post.excerpt}
                                        </p>
                                    ) : null}
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </DocsPublicShell>
        </>
    );
}
