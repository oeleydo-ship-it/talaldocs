import { Head, useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

export default function DocsPassword({ project }: { project: { name: string; slug: string; pathKey: string } }) {
    const form = useForm({ password: '' });

    return (
        <>
            <Head title={`Unlock ${project.name}`} />
            <div className="mx-auto flex min-h-screen max-w-md flex-col justify-center gap-4 p-6">
                <Card>
                    <CardHeader className="text-center">
                        <div className="mx-auto mb-2 inline-flex size-12 items-center justify-center rounded-full bg-muted">
                            <Lock className="size-5" />
                        </div>
                        <CardTitle>{project.name} is password protected</CardTitle>
                        <CardDescription>Enter the documentation password to continue reading.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(`/docs/${project.pathKey}/unlock`);
                            }}
                        >
                            <Input
                                type="password"
                                value={form.data.password}
                                onChange={(event) => form.setData('password', event.target.value)}
                                placeholder="Documentation password"
                            />
                            <Button type="submit" className="w-full" disabled={form.processing}>
                                Unlock
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
