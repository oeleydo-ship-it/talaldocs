import { Link } from '@inertiajs/react';
import { MarketingHead } from '@/components/marketing/marketing-head';
import { useAppName } from '@/lib/app-branding';

const sections = [
    {
        title: 'Acceptance',
        body: 'By creating an account or using the service, you agree to these Terms of Service. If you use the service on behalf of an organization, you represent that you have authority to bind that organization.',
    },
    {
        title: 'The service',
        body: 'We provide a hosted documentation platform including authoring tools, workspace management, public documentation sites, and optional AI features. Features available to you depend on your plan and platform configuration.',
    },
    {
        title: 'Your content',
        body: 'You retain ownership of content you create. You grant us the rights necessary to host, display, back up, and process your content solely to operate and improve the service.',
    },
    {
        title: 'Acceptable use',
        body: 'You may not use the service to distribute unlawful content, attempt unauthorized access, interfere with other customers, or exceed plan limits through automated abuse. We may suspend accounts that violate these rules.',
    },
    {
        title: 'Billing',
        body: 'Paid plans renew according to the billing terms shown at checkout. Fees are non-refundable except where required by law. Downgrades take effect at the next billing cycle unless otherwise stated.',
    },
    {
        title: 'Availability and support',
        body: 'We strive for reliable uptime but do not guarantee uninterrupted service. Maintenance, third-party outages, or force majeure events may affect availability. Support response times vary by plan.',
    },
    {
        title: 'Disclaimer and liability',
        body: 'The service is provided as-is to the maximum extent permitted by law. Our aggregate liability for any claim relating to the service is limited to the fees you paid us in the twelve months before the claim.',
    },
    {
        title: 'Changes',
        body: 'We may update these terms from time to time. Material changes will be posted on this page with an updated effective date. Continued use after changes constitutes acceptance.',
    },
];

export default function MarketingTerms() {
    const appName = useAppName();
    const effectiveDate = 'August 15, 2026';

    return (
        <>
            <MarketingHead
                title="Terms of Service"
                description={`Terms governing your use of the ${appName} documentation platform.`}
                path="/terms"
            />

            <section className="mx-auto max-w-3xl px-4 py-16 sm:px-6">
                <p className="text-sm font-medium text-primary">Legal</p>
                <h1 className="mt-2 text-4xl font-semibold tracking-tight">Terms of Service</h1>
                <p className="mt-4 text-sm text-muted-foreground">Effective {effectiveDate}</p>
                <p className="mt-6 leading-relaxed text-muted-foreground">
                    These Terms of Service (&ldquo;Terms&rdquo;) govern access to and use of {appName} and related public documentation sites.
                </p>

                <div className="mt-10 space-y-8">
                    {sections.map((section) => (
                        <div key={section.title}>
                            <h2 className="text-xl font-semibold tracking-tight">{section.title}</h2>
                            <p className="mt-3 leading-relaxed text-muted-foreground">{section.body}</p>
                        </div>
                    ))}
                </div>

                <p className="mt-10 text-sm text-muted-foreground">
                    Read our{' '}
                    <Link href="/privacy" className="font-medium text-foreground underline-offset-4 hover:underline">
                        Privacy Policy
                    </Link>{' '}
                    or{' '}
                    <Link href="/contact" className="font-medium text-foreground underline-offset-4 hover:underline">
                        contact us
                    </Link>{' '}
                    with questions.
                </p>
            </section>
        </>
    );
}
