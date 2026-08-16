import { Head } from '@inertiajs/react';
import { googleFontsHref } from '@/lib/docs-branding';

type DocsHeadProps = {
    project: {
        name: string;
        font_family: string;
        heading_font: string;
        favicon_url: string | null;
        og_image_url: string | null;
    };
    page: {
        title: string;
        subtitle: string | null;
    };
    hreflang: { hreflang: string; href: string }[];
    canonicalUrl: string;
};

export function DocsHead({ project, page, hreflang, canonicalUrl }: DocsHeadProps) {
    const description = page.subtitle ?? `${page.title} documentation for ${project.name}`;
    const fontsHref = googleFontsHref([project.font_family, project.heading_font]);

    return (
        <Head title={`${page.title} · ${project.name}`}>
            {hreflang.map((item) => (
                <link key={item.hreflang} rel="alternate" hrefLang={item.hreflang} href={item.href} />
            ))}
            <meta name="description" content={description} />
            <link rel="canonical" href={canonicalUrl} />
            {fontsHref ? <link rel="stylesheet" href={fontsHref} /> : null}
            {project.favicon_url ? <link rel="icon" href={project.favicon_url} /> : null}
            <meta property="og:type" content="website" />
            <meta property="og:title" content={`${page.title} · ${project.name}`} />
            <meta property="og:description" content={description} />
            <meta property="og:url" content={canonicalUrl} />
            {project.og_image_url ? <meta property="og:image" content={project.og_image_url} /> : null}
            <meta name="twitter:card" content={project.og_image_url ? 'summary_large_image' : 'summary'} />
            <meta name="twitter:title" content={`${page.title} · ${project.name}`} />
            <meta name="twitter:description" content={description} />
            {project.og_image_url ? <meta name="twitter:image" content={project.og_image_url} /> : null}
        </Head>
    );
}
