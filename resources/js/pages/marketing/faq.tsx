import { Link } from '@inertiajs/react';
import { MarketingFaqList } from '@/components/marketing/marketing-faq-list';
import { MarketingHead } from '@/components/marketing/marketing-head';
import { Button } from '@/components/ui/button';
import { useAppName } from '@/lib/app-branding';
import { marketingFaqItems } from '@/lib/marketing-faq';

export default function MarketingFaq() {
    const appName = useAppName();

    return (
        <>
            <MarketingHead
                title="FAQ"
                description={`Answers about ${appName} plans, domains, AI, templates, announcements, visibility, branding, and Enterprise support.`}
                path="/faq"
            />

            <section className="mx-auto max-w-3xl px-4 py-16 sm:px-6">
                <div className="text-center">
                    <p className="text-sm font-medium text-primary">FAQ</p>
                    <h1 className="mt-2 text-4xl font-semibold tracking-tight">Frequently asked questions</h1>
                    <p className="mt-4 text-muted-foreground">
                        Everything you need to know about getting started with {appName}, upgrading, and publishing
                        documentation.
                    </p>
                </div>

                <MarketingFaqList items={marketingFaqItems(appName)} className="mt-10" />

                <div className="mt-10 rounded-xl border bg-muted/30 p-6 text-center">
                    <p className="font-medium">Still have questions?</p>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Our team can help with Enterprise plans, security reviews, and migrations.
                    </p>
                    <Button asChild className="mt-4">
                        <Link href="/contact">Contact us</Link>
                    </Button>
                </div>
            </section>
        </>
    );
}
