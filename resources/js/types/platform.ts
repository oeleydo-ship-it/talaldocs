export type PlatformBranding = {
    app_name: string;
    logo_url: string | null;
    favicon_url: string | null;
    tagline: string | null;
    hide_app_name_next_to_logo: boolean;
};

export type PublicDemoDraft = {
    id: string;
    title: string;
    subtitle: string;
    badge: string;
    url: string;
    sort_order: number;
    enabled: boolean;
};

export type PublicContentCopy = {
    home: {
        eyebrow: string;
        heading: string;
        tagline: string;
    };
    examples: {
        eyebrow: string;
        heading: string;
        intro: string;
        demos_heading: string;
        demos_description: string;
    };
    features: {
        eyebrow: string;
        heading: string;
        intro: string;
    };
};

export type PublicContentSettings = {
    copy: PublicContentCopy;
    defaults: PublicContentCopy;
    demos: Array<PublicDemoDraft & { name?: string; subdomain?: string; layout?: string }>;
    demos_managed: boolean;
};
