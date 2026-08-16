import { usePage } from '@inertiajs/react';
import type { PlatformBranding } from '@/types';

export const DEFAULT_APP_NAME = 'Docs';

export function resolveAppName(branding?: PlatformBranding | null, sharedName?: string | null): string {
    const fromBranding = branding?.app_name?.trim();

    if (fromBranding) {
        return fromBranding;
    }

    const fromShared = sharedName?.trim();

    if (fromShared) {
        return fromShared;
    }

    return DEFAULT_APP_NAME;
}

export function useAppName(): string {
    const page = usePage();
    const branding = page.props.platformBranding as PlatformBranding | undefined;
    const sharedName = typeof page.props.name === 'string' ? page.props.name : null;

    return resolveAppName(branding, sharedName);
}

export function usePlatformBranding(): PlatformBranding | undefined {
    return usePage().props.platformBranding as PlatformBranding | undefined;
}
