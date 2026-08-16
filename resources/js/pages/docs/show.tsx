'use no memo';

import { usePage } from '@inertiajs/react';
import ClassicDocsTemplate from '@/pages/docs/templates/classic';
import GitbookDocsTemplate from '@/pages/docs/templates/gitbook';
import type { PublicDocsProps } from '@/pages/docs/types';

export default function PublicDocsShow() {
    const { props } = usePage<PublicDocsProps>();

    if (props.template === 'gitbook') {
        return <GitbookDocsTemplate {...props} />;
    }

    return <ClassicDocsTemplate {...props} />;
}
