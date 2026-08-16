import { DocsFooter } from '@/components/docs/docs-footer';
import type { DocsShellProps } from '@/pages/docs/types';

/** Shared footer wiring for every public docs template and hub page. */
export function DocsShellFooter({
    project,
    headerLinks,
    docsHomeUrl,
    directoryUrl,
    announcementsUrl,
    changelogUrl,
    showPoweredBy,
}: Pick<
    DocsShellProps,
    | 'project'
    | 'headerLinks'
    | 'docsHomeUrl'
    | 'directoryUrl'
    | 'announcementsUrl'
    | 'changelogUrl'
    | 'showPoweredBy'
>) {
    return (
        <DocsFooter
            projectName={project.name}
            logoUrl={project.logo_url}
            headingFont={project.heading_font}
            docsHomeUrl={docsHomeUrl}
            directoryUrl={directoryUrl}
            announcementsUrl={announcementsUrl}
            changelogUrl={changelogUrl}
            headerLinks={headerLinks}
            showPoweredBy={showPoweredBy}
        />
    );
}
