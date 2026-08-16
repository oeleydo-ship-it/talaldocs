export const PLAN_LIMIT_LABELS: Record<string, string> = {
    projects: 'Projects',
    members: 'Workspace members',
    custom_domains: 'Custom domains',
};

export const ORDERED_PLAN_LIMITS = Object.keys(PLAN_LIMIT_LABELS);

export const PLAN_FEATURE_LABELS: Record<string, string> = {
    custom_domain: 'Custom domain',
    advanced_branding: 'Advanced branding',
    analytics: 'Analytics & feedback',
    versioning: 'Version history',
    localization: 'Multi-language docs',
    audit_log: 'Audit log',
    sso: 'SSO / SAML',
    custom_css: 'Custom CSS',
    remove_branding: 'Remove platform branding',
    ai_generation: 'AI documentation',
};

export const ORDERED_PLAN_FEATURES = Object.keys(PLAN_FEATURE_LABELS);

export function formatPlanFeature(key: string): string {
    return PLAN_FEATURE_LABELS[key] ?? key.replaceAll('_', ' ');
}
