import { router } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { PublicContentSettings, PublicDemoDraft } from '@/types/platform';

function newDemoId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `demo-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

function toDraft(demo: PublicContentSettings['demos'][number], index: number): PublicDemoDraft {
    return {
        id: demo.id || newDemoId(),
        title: demo.title || demo.name || '',
        subtitle: demo.subtitle || demo.subdomain || '',
        badge: demo.badge || demo.layout || 'classic',
        url: demo.url || '',
        sort_order: demo.sort_order ?? index,
        enabled: demo.enabled ?? true,
    };
}

function Field({
    id,
    label,
    value,
    placeholder,
    onChange,
    multiline = false,
}: {
    id: string;
    label: string;
    value: string;
    placeholder?: string;
    onChange: (value: string) => void;
    multiline?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {multiline ? (
                <Textarea
                    id={id}
                    value={value}
                    placeholder={placeholder}
                    onChange={(event) => onChange(event.target.value)}
                    className="min-h-24"
                />
            ) : (
                <Input id={id} value={value} placeholder={placeholder} onChange={(event) => onChange(event.target.value)} />
            )}
        </div>
    );
}

export function PlatformPublicContentForm({ settings }: { settings: PublicContentSettings }) {
    const [home, setHome] = useState(settings.copy.home);
    const [examples, setExamples] = useState(settings.copy.examples);
    const [features, setFeatures] = useState(settings.copy.features);
    const [demosManaged, setDemosManaged] = useState(settings.demos_managed);
    const [demos, setDemos] = useState<PublicDemoDraft[]>(() => settings.demos.map(toDraft));

    const updateDemo = (index: number, patch: Partial<PublicDemoDraft>) => {
        setDemosManaged(true);
        setDemos((current) => current.map((demo, demoIndex) => (demoIndex === index ? { ...demo, ...patch } : demo)));
    };

    const moveDemo = (index: number, direction: -1 | 1) => {
        const next = index + direction;

        if (next < 0 || next >= demos.length) {
            return;
        }

        setDemosManaged(true);
        setDemos((current) => {
            const copy = [...current];
            const [item] = copy.splice(index, 1);
            copy.splice(next, 0, item);

            return copy.map((demo, sortOrder) => ({ ...demo, sort_order: sortOrder }));
        });
    };

    const addDemo = () => {
        setDemosManaged(true);
        setDemos((current) => [
            ...current,
            {
                id: newDemoId(),
                title: '',
                subtitle: '',
                badge: 'classic',
                url: '',
                sort_order: current.length,
                enabled: true,
            },
        ]);
    };

    const removeDemo = (index: number) => {
        setDemosManaged(true);
        setDemos((current) => current.filter((_, demoIndex) => demoIndex !== index).map((demo, sortOrder) => ({ ...demo, sort_order: sortOrder })));
    };

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        router.post(
            '/platform/settings/public',
            {
                home,
                examples,
                features,
                demos_managed: demosManaged,
                demos: demos.map((demo, index) => ({
                    ...demo,
                    sort_order: index,
                })),
            },
            { preserveScroll: true },
        );
    };

    return (
        <form className="grid gap-6" onSubmit={submit}>
            <Card>
                <CardHeader>
                    <CardTitle>Examples page</CardTitle>
                    <CardDescription>
                        Heading and intro on /examples, plus the Live demo sites section. Leave a field blank to keep the
                        default copy.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <Field
                        id="examples_eyebrow"
                        label="Eyebrow"
                        value={examples.eyebrow}
                        placeholder={settings.defaults.examples.eyebrow}
                        onChange={(value) => setExamples((current) => ({ ...current, eyebrow: value }))}
                    />
                    <Field
                        id="examples_heading"
                        label="Heading"
                        value={examples.heading}
                        placeholder={settings.defaults.examples.heading}
                        onChange={(value) => setExamples((current) => ({ ...current, heading: value }))}
                    />
                    <Field
                        id="examples_intro"
                        label="Intro"
                        value={examples.intro}
                        placeholder={settings.defaults.examples.intro}
                        onChange={(value) => setExamples((current) => ({ ...current, intro: value }))}
                        multiline
                    />
                    <Field
                        id="examples_demos_heading"
                        label="Live demo section title"
                        value={examples.demos_heading}
                        placeholder={settings.defaults.examples.demos_heading}
                        onChange={(value) => setExamples((current) => ({ ...current, demos_heading: value }))}
                    />
                    <Field
                        id="examples_demos_description"
                        label="Live demo section description"
                        value={examples.demos_description}
                        placeholder={settings.defaults.examples.demos_description}
                        onChange={(value) => setExamples((current) => ({ ...current, demos_description: value }))}
                        multiline
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Live demo sites</CardTitle>
                    <CardDescription>
                        Each card can link to a custom hostname or any URL. Visitors click View live docs and go there.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <label className="flex items-start gap-3 rounded-lg border px-3 py-3 text-sm">
                        <input
                            type="checkbox"
                            className="mt-0.5"
                            checked={demosManaged}
                            onChange={(event) => setDemosManaged(event.target.checked)}
                        />
                        <span>
                            <span className="font-medium">Use this custom demo list</span>
                            <span className="mt-1 block text-muted-foreground">
                                On: only these cards appear on /examples (hidden cards are omitted). Off: the page lists
                                published projects on this instance.
                            </span>
                        </span>
                    </label>

                    {demos.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No demo cards yet. Add one to feature a live docs site.</p>
                    ) : (
                        <div className="grid gap-4">
                            {demos.map((demo, index) => (
                                <div key={demo.id} className="grid gap-3 rounded-lg border p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="text-sm font-medium">Demo {index + 1}</p>
                                        <div className="flex flex-wrap gap-1">
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                disabled={index === 0}
                                                onClick={() => moveDemo(index, -1)}
                                                aria-label="Move up"
                                            >
                                                <ChevronUp className="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                disabled={index === demos.length - 1}
                                                onClick={() => moveDemo(index, 1)}
                                                aria-label="Move down"
                                            >
                                                <ChevronDown className="size-4" />
                                            </Button>
                                            <Button type="button" size="icon" variant="outline" onClick={() => removeDemo(index)} aria-label="Remove demo">
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </div>
                                    <div className="grid gap-3 md:grid-cols-2">
                                        <Field
                                            id={`demo_title_${demo.id}`}
                                            label="Title"
                                            value={demo.title}
                                            placeholder="Getting started"
                                            onChange={(value) => updateDemo(index, { title: value })}
                                        />
                                        <Field
                                            id={`demo_subtitle_${demo.id}`}
                                            label="Subtitle / workspace label"
                                            value={demo.subtitle}
                                            placeholder="supportdocs"
                                            onChange={(value) => updateDemo(index, { subtitle: value })}
                                        />
                                    </div>
                                    <div className="grid gap-3 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor={`demo_badge_${demo.id}`}>Template badge</Label>
                                            <select
                                                id={`demo_badge_${demo.id}`}
                                                className="rounded-md border bg-transparent px-3 py-2 text-sm"
                                                value={demo.badge === 'gitbook' ? 'gitbook' : 'classic'}
                                                onChange={(event) => updateDemo(index, { badge: event.target.value })}
                                            >
                                                <option value="classic">Classic</option>
                                                <option value="gitbook">Guide</option>
                                            </select>
                                        </div>
                                        <Field
                                            id={`demo_url_${demo.id}`}
                                            label="Destination URL"
                                            value={demo.url}
                                            placeholder="https://docs.example.com/ or /docs/demo"
                                            onChange={(value) => updateDemo(index, { url: value })}
                                        />
                                    </div>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={demo.enabled}
                                            onChange={(event) => updateDemo(index, { enabled: event.target.checked })}
                                        />
                                        Show on Examples page
                                    </label>
                                </div>
                            ))}
                        </div>
                    )}

                    <Button type="button" variant="outline" onClick={addDemo}>
                        <Plus className="size-4" />
                        Add demo site
                    </Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Home hero</CardTitle>
                    <CardDescription>Public homepage eyebrow, heading, and supporting copy.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <Field
                        id="home_eyebrow"
                        label="Eyebrow"
                        value={home.eyebrow}
                        placeholder={settings.defaults.home.eyebrow}
                        onChange={(value) => setHome((current) => ({ ...current, eyebrow: value }))}
                    />
                    <Field
                        id="home_heading"
                        label="Heading"
                        value={home.heading}
                        placeholder={settings.defaults.home.heading}
                        onChange={(value) => setHome((current) => ({ ...current, heading: value }))}
                    />
                    <Field
                        id="home_tagline"
                        label="Tagline"
                        value={home.tagline}
                        placeholder={settings.defaults.home.tagline}
                        onChange={(value) => setHome((current) => ({ ...current, tagline: value }))}
                        multiline
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Features page</CardTitle>
                    <CardDescription>Intro copy at the top of /features. Section grids stay as product defaults.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <Field
                        id="features_eyebrow"
                        label="Eyebrow"
                        value={features.eyebrow}
                        placeholder={settings.defaults.features.eyebrow}
                        onChange={(value) => setFeatures((current) => ({ ...current, eyebrow: value }))}
                    />
                    <Field
                        id="features_heading"
                        label="Heading"
                        value={features.heading}
                        placeholder={settings.defaults.features.heading}
                        onChange={(value) => setFeatures((current) => ({ ...current, heading: value }))}
                    />
                    <Field
                        id="features_intro"
                        label="Intro"
                        value={features.intro}
                        placeholder={settings.defaults.features.intro}
                        onChange={(value) => setFeatures((current) => ({ ...current, intro: value }))}
                        multiline
                    />
                </CardContent>
            </Card>

            <div>
                <Button type="submit">Save public content</Button>
            </div>
        </form>
    );
}
