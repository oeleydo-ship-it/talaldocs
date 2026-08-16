import { Head, Link } from '@inertiajs/react';
import { DocsPublicShell } from '@/components/docs/docs-public-shell';
import type { DocsShellProps } from '@/pages/docs/types';

type Section = {
    key: string;
    title: string;
    description: string;
    viewAllUrl: string;
    items: Array<{ title: string; slug?: string; url: string; excerpt?: string | null; published_at?: string | null }>;
};

type Props = DocsShellProps & {
    sections: Section[];
};

export default function DocsDirectory(props: Props) {
    const { project, sections } = props;

    return (
        <>
            <Head title={`Directory · ${project.name}`} />
            <DocsPublicShell {...props} title="Directory">
                <div className="space-y-12">
                    {sections.map((section) => (
                        <section key={section.key} className="space-y-4">
                            <div className="flex flex-wrap items-end justify-between gap-3 border-b pb-3">
                                <div>
                                    <h2 className="text-xl font-semibold" style={{ fontFamily: project.heading_font }}>
                                        {section.title}
                                    </h2>
                                    <p className="text-sm text-muted-foreground">{section.description}</p>
                                </div>
                                <Link href={section.viewAllUrl} className="text-sm font-medium text-primary hover:underline">
                                    View all
                                </Link>
                            </div>
                            {section.items.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Nothing published yet.</p>
                            ) : (
                                <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    {section.items.map((item) => (
                                        <li key={item.url}>
                                            <Link
                                                href={item.url}
                                                className="block rounded-lg border bg-card/40 px-4 py-3 transition-colors hover:border-foreground/20 hover:bg-muted/40"
                                            >
                                                <p className="font-medium">{item.title}</p>
                                                {item.excerpt ? (
                                                    <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                                        {item.excerpt}
                                                    </p>
                                                ) : null}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    ))}
                </div>
            </DocsPublicShell>
        </>
    );
}
