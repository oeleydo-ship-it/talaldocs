/**
 * Shared FAQ copy for marketing home + FAQ page.
 * Prefer marketingFaqItems(appName) so copy uses the configured product name.
 */

import { DEFAULT_APP_NAME } from '@/lib/app-branding';

export type MarketingFaqItem = {
    question: string;
    answer: string;
};

export function marketingFaqItems(appName: string = DEFAULT_APP_NAME): MarketingFaqItem[] {
    return [
        {
            question: `What is ${appName}?`,
            answer: `${appName} is a documentation platform for software teams. Each workspace gets an isolated editor, nested pages, versioned content, and a public docs site on your subdomain — with custom domains, AI, analytics, and advanced branding on paid plans.`,
        },
        {
            question: 'Can I use my own domain?',
            answer: 'Yes. Free plans publish on your project subdomain. Pro and higher plans support verified custom domains with HTTPS (CNAME + ownership TXT). When Cloudflare SSL for SaaS is configured on the platform, hostnames and certificates can be managed automatically.',
        },
        {
            question: 'How does AI documentation work?',
            answer: 'On eligible plans you can generate pages from a prompt or website URL, review existing pages for accuracy, grammar, or structure, and let visitors use Ask AI on your public docs. Published content is indexed automatically so answers stay grounded in your documentation.',
        },
        {
            question: 'Is there a free plan?',
            answer: 'Yes. The Free plan includes one project, up to three workspace members, and a public docs site on your subdomain — no credit card required.',
        },
        {
            question: 'Can I invite my team?',
            answer: 'Workspaces support owners, admins, editors, and viewers. Invite teammates by email with expiring invitation links and role-based access to projects and settings.',
        },
        {
            question: 'Do you support multiple doc versions or languages?',
            answer: 'Business and Enterprise plans include multiple documentation versions and locales with public switchers and hreflang support. Free and Pro include a single default version and language.',
        },
        {
            question: 'What public templates are available?',
            answer: 'Every project can use the Classic or Guide template. Both include search, table of contents, dark mode, mobile navigation, and optional Ask AI. Switch templates anytime without rewriting content.',
        },
        {
            question: 'Can I publish announcements or a changelog?',
            answer: 'Yes. Create announcement and changelog posts in the project, publish them to dedicated public URLs, and surface them from the docs footer and directory hub.',
        },
        {
            question: 'What is the directory hub?',
            answer: 'The directory is a public browse page that aggregates published documentation, announcements, and changelog posts so readers can discover updates in one place.',
        },
        {
            question: 'Can docs be private or password protected?',
            answer: 'Yes. Projects can be public, private to workspace members, or unlocked with a shared password. Search engines are only allowed to index public documentation.',
        },
        {
            question: 'Can I import existing Markdown?',
            answer: 'Yes. Import .md, .markdown, .mdx files or a ZIP of docs. Folder paths become nested pages, with options to skip, update, or rename conflicts — and optionally publish on import.',
        },
        {
            question: 'Can I remove platform branding?',
            answer: 'Eligible plans can hide the powered-by footer, apply your logo, colors, and fonts, and host docs on a custom domain so the site feels fully white-labeled.',
        },
        {
            question: 'How do I contact sales for Enterprise?',
            answer: 'Use the contact form or email our support address for security reviews, migration help, SSO requirements, or custom limits.',
        },
    ];
}

/** @deprecated Prefer marketingFaqItems(appName) so copy uses the configured product name. */
export const MARKETING_FAQ_ITEMS = marketingFaqItems();
