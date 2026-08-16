/**
 * Shared marketing feature copy so homepage, features, and FAQ stay consistent.
 * Keep descriptions product-accurate; plan gating belongs on the pricing page.
 */

export type MarketingFeature = {
    title: string;
    description: string;
    /** Stable key for grouping / icons in page components */
    key: string;
};

export const marketingAuthoringFeatures: MarketingFeature[] = [
    {
        key: 'editor',
        title: 'Markdown editor',
        description:
            'Live preview, autosave, revisions, callouts, images, and video embeds — with a visual or source-mode toggle.',
    },
    {
        key: 'blocks',
        title: 'Reusable blocks',
        description: 'Create shared Markdown snippets and insert them across pages with {{block:slug}} placeholders.',
    },
    {
        key: 'import',
        title: 'Markdown import',
        description:
            'Import .md, .mdx, or ZIP archives. Folder paths become nested pages, with conflict handling and optional publish.',
    },
    {
        key: 'subpages',
        title: 'Subpages & nested nav',
        description:
            'Organize docs in a drag-and-drop page tree. Public sidebars reflect parent/child hierarchy automatically.',
    },
];

export const marketingPublishingFeatures: MarketingFeature[] = [
    {
        key: 'templates',
        title: 'Classic & Guide templates',
        description:
            'Ship beautiful public docs with sidebar navigation, TOC, search, dark mode, and mobile-friendly chrome.',
    },
    {
        key: 'domains',
        title: 'Custom domains',
        description:
            'Publish on your project subdomain, then verify your own hostname with CNAME/TXT — Cloudflare-managed DNS when configured.',
    },
    {
        key: 'versions',
        title: 'Versions & locales',
        description:
            'Maintain multiple documentation versions and languages with switchers and hreflang on public sites.',
    },
    {
        key: 'directory',
        title: 'Directory hub',
        description:
            'A public hub that aggregates published docs, announcements, and changelog posts for easy browsing.',
    },
    {
        key: 'posts',
        title: 'Announcements & changelog',
        description:
            'Publish announcement and changelog posts alongside docs — linked from the footer and directory hub.',
    },
    {
        key: 'header',
        title: 'Header navigation & branding',
        description:
            'Logo, colors, fonts, favicon, OG image, and custom header links so docs feel like your product.',
    },
];

export const marketingAiFeatures: MarketingFeature[] = [
    {
        key: 'generate',
        title: 'AI generate',
        description:
            'Turn a brief — or an existing website — into structured draft pages. Per-page generate with audience and tone controls.',
    },
    {
        key: 'review',
        title: 'AI review',
        description:
            'Review pages for accuracy, grammar and clarity, or structure — then apply corrected Markdown with a summary of changes.',
    },
    {
        key: 'ask',
        title: 'Ask AI on public docs',
        description:
            'Visitors ask questions in the docs header and get answers grounded in published content, with links to source pages.',
    },
];

export const marketingWorkspaceFeatures: MarketingFeature[] = [
    {
        key: 'team',
        title: 'Team & members',
        description:
            'Isolated workspaces with owners, admins, editors, and viewers. Invite by email with expiring invitation links.',
    },
    {
        key: 'billing',
        title: 'Billing & plans',
        description:
            'Start free, then upgrade for domains, AI, analytics, versioning, and branding. Limits are enforced on the server.',
    },
    {
        key: 'visibility',
        title: 'Public, private & password',
        description:
            'Choose public docs, members-only private projects, or a shared password unlock for gated documentation.',
    },
    {
        key: 'search',
        title: 'Full-text search',
        description:
            'Search published content from the docs site — inline or via ⌘K / Ctrl+K depending on the template.',
    },
    {
        key: 'analytics',
        title: 'Analytics & feedback',
        description:
            'Track page views and Yes/No helpfulness feedback. Export CSV reports from the workspace analytics dashboard.',
    },
    {
        key: 'whitelabel',
        title: 'White-label branding',
        description:
            'Hide powered-by branding on eligible plans, bring your logo and theme, and host docs on your own domain.',
    },
];

export const allMarketingFeatures: MarketingFeature[] = [
    ...marketingAuthoringFeatures,
    ...marketingPublishingFeatures,
    ...marketingAiFeatures,
    ...marketingWorkspaceFeatures,
];
