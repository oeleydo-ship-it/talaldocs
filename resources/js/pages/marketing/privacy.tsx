import { Link } from '@inertiajs/react';
import { MarketingHead } from '@/components/marketing/marketing-head';
import { useAppName } from '@/lib/app-branding';

const sections = [
    {
        title: 'Information we collect',
        body: 'We collect account information you provide (name, email, workspace details), content you create in the editor, usage data such as page views on public docs, and technical logs needed to operate and secure the service.',
    },
    {
        title: 'How we use information',
        body: 'We use your information to provide the documentation platform, authenticate users, enforce plan limits, deliver email notifications you request, improve product reliability, and respond to support inquiries.',
    },
    {
        title: 'AI features',
        body: 'When AI features are enabled, prompts and relevant documentation excerpts may be sent to configured AI providers to generate content or answer visitor questions. You control whether AI is enabled at the platform and project level.',
    },
    {
        title: 'Sharing and subprocessors',
        body: 'We do not sell personal information. We use infrastructure and payment providers to host the service, deliver email, and process subscriptions. Data is shared only as needed to provide those services under contractual safeguards.',
    },
    {
        title: 'Data retention',
        body: 'We retain workspace content while your account is active. You may delete projects and pages from the product. Backup and audit retention periods may apply for security and compliance on certain plans.',
    },
    {
        title: 'Your choices',
        body: 'You can update profile information, manage workspace members, export analytics where available, and contact us to request access, correction, or deletion subject to legal and contractual requirements.',
    },
    {
        title: 'Contact',
        body: 'For privacy questions, use the contact form or email the support address listed on our contact page.',
    },
];

export default function MarketingPrivacy() {
    const appName = useAppName();
    const effectiveDate = 'August 15, 2026';

    return (
        <>
            <MarketingHead
                title="Privacy Policy"
                description={`How ${appName} collects, uses, and protects your information.`}
                path="/privacy"
            />

            <section className="mx-auto max-w-3xl px-4 py-16 sm:px-6">
                <p className="text-sm font-medium text-primary">Legal</p>
                <h1 className="mt-2 text-4xl font-semibold tracking-tight">Privacy Policy</h1>
                <p className="mt-4 text-sm text-muted-foreground">Effective {effectiveDate}</p>
                <p className="mt-6 leading-relaxed text-muted-foreground">
                    This Privacy Policy describes how {appName} (&ldquo;we&rdquo;, &ldquo;us&rdquo;) handles information when you use our documentation platform and public sites hosted on the service.
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
                    See also our{' '}
                    <Link href="/terms" className="font-medium text-foreground underline-offset-4 hover:underline">
                        Terms of Service
                    </Link>
                    .
                </p>
            </section>
        </>
    );
}
