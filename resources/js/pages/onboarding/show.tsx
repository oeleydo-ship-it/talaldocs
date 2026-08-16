import { Form, Head, usePage } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    name: string;
    companyName: string | null;
    appDomain: string;
    aiConfigured: boolean;
};

type Availability = {
    available: boolean;
    reserved: boolean;
    valid: boolean;
};

export default function Onboarding({
    name,
    companyName,
    appDomain,
    aiConfigured,
}: Props) {
    const csrf = usePage().props.csrf as string | undefined;
    const [workspaceName, setWorkspaceName] = useState('');
    const [subdomain, setSubdomain] = useState('');
    const [importFromWebsite, setImportFromWebsite] = useState(false);
    const [websiteUrl, setWebsiteUrl] = useState('');
    const [productDescription, setProductDescription] = useState('');
    const [publishAiPages, setPublishAiPages] = useState(false);
    const [availability, setAvailability] = useState<Availability | null>(null);
    const [checking, setChecking] = useState(false);

    const suggestion = useMemo(
        () =>
            workspaceName
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, ''),
        [workspaceName],
    );

    useEffect(() => {
        if (!subdomain && suggestion) {
            setSubdomain(suggestion);
        }
    }, [suggestion, subdomain]);

    useEffect(() => {
        if (!subdomain) {
            setAvailability(null);
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setChecking(true);
            try {
                const response = await fetch(
                    `/onboarding/subdomain-availability?subdomain=${encodeURIComponent(subdomain)}`,
                    {
                        signal: controller.signal,
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                        },
                    },
                );
                if (response.ok) {
                    setAvailability(await response.json());
                }
            } finally {
                setChecking(false);
            }
        }, 300);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [subdomain, csrf]);

    let subdomainHelp = 'Your public docs will be available at this subdomain.';
    if (checking) {
        subdomainHelp = 'Checking availability…';
    } else if (availability?.reserved) {
        subdomainHelp = 'That subdomain is reserved. Choose another.';
    } else if (availability && !availability.valid) {
        subdomainHelp =
            'Use 3–63 lowercase letters, numbers, and hyphens.';
    } else if (availability && !availability.available) {
        subdomainHelp = 'That subdomain is already taken.';
    } else if (availability?.available) {
        subdomainHelp = `${subdomain}.${appDomain} is available.`;
    }

    return (
        <>
            <Head title="Create your workspace" />

            <Form
                action="/onboarding"
                method="post"
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Your name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={name}
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="company_name">Company name</Label>
                                <Input
                                    id="company_name"
                                    name="company_name"
                                    defaultValue={companyName ?? ''}
                                    required
                                    placeholder="Acme"
                                />
                                <InputError message={errors.company_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="workspace_name">
                                    Workspace name
                                </Label>
                                <Input
                                    id="workspace_name"
                                    name="workspace_name"
                                    required
                                    placeholder="Acme Docs"
                                    value={workspaceName}
                                    onChange={(event) =>
                                        setWorkspaceName(event.target.value)
                                    }
                                />
                                <InputError message={errors.workspace_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="subdomain">
                                    Preferred subdomain
                                </Label>
                                <div className="flex items-center gap-2">
                                    <Input
                                        id="subdomain"
                                        name="subdomain"
                                        required
                                        value={subdomain}
                                        onChange={(event) =>
                                            setSubdomain(
                                                event.target.value.toLowerCase(),
                                            )
                                        }
                                        placeholder="acme-docs"
                                    />
                                    <span className="shrink-0 text-sm text-muted-foreground">
                                        .{appDomain}
                                    </span>
                                </div>
                                <p
                                    className={
                                        availability && !availability.available
                                            ? 'text-sm text-destructive'
                                            : 'text-sm text-muted-foreground'
                                    }
                                >
                                    {subdomainHelp}
                                </p>
                                <InputError message={errors.subdomain} />
                            </div>

                            {aiConfigured && (
                                <div className="rounded-xl border bg-muted/20 p-4">
                                    <div className="mb-3 flex items-center gap-2">
                                        <Sparkles className="size-4 text-primary" />
                                        <p className="font-medium">Import from your website</p>
                                    </div>
                                    <p className="mb-4 text-sm text-muted-foreground">
                                        Optional: paste your product site and we&apos;ll draft docs in
                                        your first project after setup.
                                    </p>
                                    <label className="mb-4 flex items-start gap-3 text-sm">
                                        <Checkbox
                                            checked={importFromWebsite}
                                            onCheckedChange={(checked) =>
                                                setImportFromWebsite(checked === true)
                                            }
                                        />
                                        <span>Generate documentation from my website</span>
                                    </label>
                                    {importFromWebsite && (
                                        <div className="grid gap-4">
                                            <div className="grid gap-2">
                                                <Label htmlFor="website_url">Website URL</Label>
                                                <Input
                                                    id="website_url"
                                                    name="website_url"
                                                    type="url"
                                                    placeholder="https://example.com"
                                                    value={websiteUrl}
                                                    onChange={(event) =>
                                                        setWebsiteUrl(event.target.value)
                                                    }
                                                />
                                                <InputError message={errors.website_url} />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="product_description">
                                                    What does your product do?
                                                </Label>
                                                <Textarea
                                                    id="product_description"
                                                    name="product_description"
                                                    rows={3}
                                                    value={productDescription}
                                                    onChange={(event) =>
                                                        setProductDescription(event.target.value)
                                                    }
                                                />
                                                <InputError message={errors.product_description} />
                                            </div>
                                            <input
                                                type="hidden"
                                                name="publish_ai_pages"
                                                value={publishAiPages ? '1' : '0'}
                                            />
                                            <label className="flex items-start gap-3 text-sm">
                                                <Checkbox
                                                    checked={publishAiPages}
                                                    onCheckedChange={(checked) =>
                                                        setPublishAiPages(checked === true)
                                                    }
                                                />
                                                <span>Publish generated pages immediately</span>
                                            </label>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                            data-test="complete-onboarding"
                        >
                            {processing && <Spinner />}
                            Create workspace
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

Onboarding.layout = {
    title: 'Set up your docs',
    description:
        'Create your first workspace and reserve a unique documentation subdomain.',
};
