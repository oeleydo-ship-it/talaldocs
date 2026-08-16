import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Status = {
    connected: boolean;
    driver: string;
    migrations_ready: boolean;
    needs_install: boolean;
    error: string | null;
};

type Props = {
    status: Status;
    supportedDrivers: string[];
    appName: string;
};

export default function Install({ status, supportedDrivers, appName }: Props) {
    const driverLabel =
        status.driver === 'pgsql'
            ? 'PostgreSQL'
            : status.driver === 'mysql' || status.driver === 'mariadb'
              ? 'MySQL'
              : status.driver === 'sqlite'
                ? 'SQLite'
                : status.driver;

    return (
        <>
            <Head title="Install" />

            <div className="mb-6 space-y-2 rounded-lg border bg-muted/30 p-4 text-sm">
                <p className="font-medium text-foreground">Database</p>
                <p className="text-muted-foreground">
                    Driver: <span className="text-foreground">{driverLabel}</span>
                    {' · '}
                    {status.connected ? 'Connected' : 'Not connected'}
                    {status.migrations_ready ? ' · Ready' : status.connected ? ' · Migrations will run on submit' : ''}
                </p>
                <p className="text-muted-foreground">
                    Supported: {supportedDrivers.filter((d) => d !== 'sqlite').join(', ').toUpperCase()}
                    (SQLite optional for local). Set <code className="text-xs">DB_CONNECTION</code> in{' '}
                    <code className="text-xs">.env</code>.
                </p>
                {!status.connected && status.error ? (
                    <p className="text-destructive">{status.error}</p>
                ) : null}
            </div>

            <Form
                action="/install"
                method="post"
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <InputError message={errors.database} />

                        <div className="grid gap-2">
                            <Label htmlFor="app_name">App name</Label>
                            <Input
                                id="app_name"
                                name="app_name"
                                defaultValue={appName}
                                placeholder="Docs"
                                autoComplete="organization"
                            />
                            <InputError message={errors.app_name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="name">Superadmin name</Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                autoFocus
                                autoComplete="name"
                                placeholder="Full name"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Superadmin email</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoComplete="email"
                                placeholder="admin@example.com"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Password</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="new-password"
                                placeholder="Password"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">Confirm password</Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                                placeholder="Confirm password"
                            />
                        </div>

                        <Button type="submit" className="w-full" disabled={processing || !status.connected} tabIndex={0}>
                            {processing && <Spinner />}
                            Create superadmin & finish install
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

Install.layout = {
    title: 'Install',
    description: 'Create the first platform superadmin to finish setup.',
};
