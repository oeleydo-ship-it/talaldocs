export type NavTreeItem = {
    id: number;
    title: string;
    slug: string;
    children: NavTreeItem[];
};

export type PageItem = { id: number; title: string; slug: string; parent_id: number | null };

export type HeaderLink = { label: string; url: string };

export type SidebarGroup = {
    title: string;
    items: { id: number; title: string; slug: string }[];
};

export type Breadcrumb = { title: string; slug: string | null };

export type DocsBrandingProject = {
    name: string;
    slug: string;
    pathKey: string;
    primary_color: string;
    accent_color: string;
    font_family: string;
    heading_font: string;
    logo_url: string | null;
    favicon_url: string | null;
    og_image_url: string | null;
    github_edit_url: string | null;
};

export type DocsTemplateName = 'classic' | 'gitbook';

export type DocsShellProps = {
    project: DocsBrandingProject;
    /** Matches project docs_template so hub pages share branding with classic/gitbook. */
    template: DocsTemplateName;
    showPoweredBy: boolean;
    headerLinks: HeaderLink[];
    docsHomeUrl: string;
    directoryUrl: string;
    announcementsUrl: string;
    changelogUrl: string;
    /** Version/locale path for search/ask result links (e.g. /docs/acme/latest/en). */
    docsPagesBasePath: string;
    searchUrl: string;
    askUrl: string;
    aiAskEnabled: boolean;
};

export type PublicPostListItem = {
    title: string;
    slug: string;
    excerpt: string | null;
    url: string;
    published_at: string | null;
};

export type PublicDocsProps = DocsShellProps & {
    docsLayout: 'centered' | 'wide';
    canonicalUrl: string;
    version: { id: number; name: string; slug: string };
    locale: { id: number; code: string; name: string };
    versions: { id: number; name: string; slug: string }[];
    languages: { code: string; name: string }[];
    pages: PageItem[];
    navTree: NavTreeItem[];
    sidebarGroups: SidebarGroup[];
    breadcrumbs: Breadcrumb[];
    page: {
        id: number;
        title: string;
        subtitle: string | null;
        slug: string;
        html: string;
        updated_at: string | null;
    };
    toc: { id: string; text: string; level: number }[];
    prev: { title: string; slug: string } | null;
    next: { title: string; slug: string } | null;
    hreflang: { hreflang: string; href: string }[];
    basePath: string;
    feedbackUrl: string;
    aiAskEnabled: boolean;
    askUrl: string;
    searchUrl: string;
};

export const docLinkOptions = { preserveState: false, preserveScroll: false } as const;

export function githubEditUrl(base: string | null, slug: string): string | null {
    if (!base) {
        return null;
    }

    if (base.includes('{slug}')) {
        return base.replace('{slug}', slug);
    }

    return `${base.replace(/\/$/, '')}/${slug}`;
}
