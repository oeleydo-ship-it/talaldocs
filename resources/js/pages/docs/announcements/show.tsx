import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { DocsPublicShell } from '@/components/docs/docs-public-shell';
import { cn } from '@/lib/utils';
import type { DocsShellProps } from '@/pages/docs/types';

type Props = DocsShellProps & {
    post: {
        title: string;
        slug: string;
        excerpt: string | null;
        html: string;
        published_at: string | null;
        type: string;
    };
    listUrl: string;
};

export default function AnnouncementShow(props: Props) {
    const { project, post, listUrl, template = 'classic' } = props;
    const isGitbook = template === 'gitbook';
    const listLabel = post.type === 'changelog' ? 'changelog' : 'announcements';

    return (
        <>
            <Head title={`${post.title} · ${project.name}`} />
            <DocsPublicShell {...props} narrow>
                <article>
                    <Link
                        href={listUrl}
                        className="mb-6 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <ArrowLeft className="size-3.5 shrink-0" aria-hidden />
                        Back to {listLabel}
                    </Link>

                    <header className="mb-8 border-b pb-6">
                        <h1
                            className={cn(
                                'tracking-tight text-balance',
                                isGitbook ? 'text-3xl font-bold sm:text-4xl' : 'text-3xl font-semibold sm:text-[2rem]',
                            )}
                            style={{
                                fontFamily: project.heading_font,
                                color: isGitbook ? undefined : project.primary_color,
                            }}
                        >
                            {post.title}
                        </h1>
                        {post.published_at ? (
                            <time
                                dateTime={post.published_at}
                                className="mt-3 block text-sm text-muted-foreground"
                            >
                                {new Date(post.published_at).toLocaleDateString(undefined, {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric',
                                })}
                            </time>
                        ) : null}
                        {post.excerpt ? (
                            <p className="mt-4 text-lg leading-relaxed text-muted-foreground">{post.excerpt}</p>
                        ) : null}
                    </header>

                    <div
                        className={cn(
                            'docs-content space-y-4 text-[15px] leading-7',
                            isGitbook && 'docs-content-steps',
                        )}
                        dangerouslySetInnerHTML={{ __html: post.html }}
                    />
                </article>
            </DocsPublicShell>
        </>
    );
}
