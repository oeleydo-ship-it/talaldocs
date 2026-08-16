import { Head } from '@inertiajs/react';
import { useAppName, usePlatformBranding } from '@/lib/app-branding';

type Props = {
    title: string;
    description?: string;
    path?: string;
};

export function MarketingHead({ title, description, path }: Props) {
    const branding = usePlatformBranding();
    const appName = useAppName();
    const fullTitle = title ? `${title} · ${appName}` : appName;
    const canonicalPath = path ?? (typeof window !== 'undefined' ? window.location.pathname : '/');
    const url = typeof window !== 'undefined' ? `${window.location.origin}${canonicalPath}` : canonicalPath;

    return (
        <Head title={fullTitle}>
            {description && <meta head-key="description" name="description" content={description} />}
            <meta head-key="og:title" property="og:title" content={fullTitle} />
            {description && <meta head-key="og:description" property="og:description" content={description} />}
            <meta head-key="og:type" property="og:type" content="website" />
            <meta head-key="og:url" property="og:url" content={url} />
            {branding?.logo_url && (
                <meta head-key="og:image" property="og:image" content={branding.logo_url} />
            )}
            <link head-key="canonical" rel="canonical" href={url} />
        </Head>
    );
}
