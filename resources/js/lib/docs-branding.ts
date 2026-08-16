export function googleFontsHref(families: string[]): string | null {
    const unique = [...new Set(families.map((family) => family.trim()).filter(Boolean))];

    if (unique.length === 0) {
        return null;
    }

    const params = unique
        .map((family) => `family=${encodeURIComponent(family.replace(/\s+/g, '+'))}:wght@400;500;600;700`)
        .join('&');

    return `https://fonts.googleapis.com/css2?${params}&display=swap`;
}

export function docsThemeStyle(project: {
    primary_color: string;
    accent_color: string;
    font_family: string;
}): Record<string, string> {
    return {
        '--docs-primary': project.primary_color,
        '--docs-accent': project.accent_color,
        // Fallbacks avoid browser serif (Times) when Google Fonts have not loaded yet.
        fontFamily: `${project.font_family}, ui-sans-serif, system-ui, sans-serif`,
    };
}
