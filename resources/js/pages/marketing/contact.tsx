import { Link, useForm, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { MarketingHead } from '@/components/marketing/marketing-head';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    supportEmail: string | null;
};

export default function MarketingContact({ supportEmail }: Props) {
    const status = String(usePage().props.flash?.status ?? '');
    const form = useForm({
        name: '',
        email: '',
        message: '',
    });

    return (
        <>
            <MarketingHead
                title="Contact"
                description="Questions about Enterprise, security reviews, or migration help? Reach our team."
                path="/contact"
            />

            <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                <div className="mx-auto max-w-xl">
                    <p className="text-sm font-medium text-primary">Contact</p>
                    <h1 className="mt-2 text-4xl font-semibold tracking-tight">Talk to our team</h1>
                    <p className="mt-4 text-muted-foreground">
                        Questions about Enterprise, security reviews, or migration help? Send us a note.
                    </p>
                    {supportEmail && (
                        <p className="mt-3 text-sm text-muted-foreground">
                            Prefer email?{' '}
                            <a href={`mailto:${supportEmail}`} className="font-medium text-foreground underline-offset-4 hover:underline">
                                {supportEmail}
                            </a>
                        </p>
                    )}
                    <Card className="mt-8 shadow-sm">
                        <CardHeader>
                            <CardTitle>Send a message</CardTitle>
                            <CardDescription>We typically respond within one business day.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {status && (
                                <p className="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-900 dark:border-green-900 dark:bg-green-950/40 dark:text-green-100">
                                    {status}
                                </p>
                            )}
                            <form
                                className="space-y-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    form.post('/contact');
                                }}
                            >
                                <div className="space-y-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        aria-invalid={Boolean(form.errors.name)}
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={form.data.email}
                                        onChange={(event) => form.setData('email', event.target.value)}
                                        aria-invalid={Boolean(form.errors.email)}
                                    />
                                    <InputError message={form.errors.email} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="message">Message</Label>
                                    <Textarea
                                        id="message"
                                        rows={5}
                                        value={form.data.message}
                                        onChange={(event) => form.setData('message', event.target.value)}
                                        aria-invalid={Boolean(form.errors.message)}
                                    />
                                    <InputError message={form.errors.message} />
                                </div>
                                <Button type="submit" disabled={form.processing}>
                                    Send message
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                    <p className="mt-6 text-center text-sm text-muted-foreground">
                        Looking for answers first?{' '}
                        <Link href="/faq" className="font-medium text-foreground underline-offset-4 hover:underline">
                            Browse the FAQ
                        </Link>
                    </p>
                </div>
            </section>
        </>
    );
}
