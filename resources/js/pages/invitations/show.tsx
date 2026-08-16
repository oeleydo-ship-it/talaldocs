import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import TextLink from '@/components/text-link';
import { login, register } from '@/routes';

type Props = {
    invitation: {
        email: string;
        role: string;
        workspace: { id: number; name: string; slug: string } | null;
        expires_at: string | null;
    };
    token: string;
    authenticated: boolean;
    emailMatches: boolean;
};

export default function InvitationShow({ invitation, token, authenticated, emailMatches }: Props) {
    const workspaceName = invitation.workspace?.name ?? 'a workspace';

    return (
        <>
            <Head title="Workspace invitation" />
            <Card className="w-full max-w-md shadow-sm">
                <CardHeader>
                    <CardTitle>Join {workspaceName}</CardTitle>
                    <CardDescription>
                        You were invited as <span className="font-medium capitalize">{invitation.role}</span>.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <p className="text-sm text-muted-foreground">
                        Invitation for <span className="font-medium text-foreground">{invitation.email}</span>
                        {invitation.expires_at && (
                            <> · expires {new Date(invitation.expires_at).toLocaleDateString()}</>
                        )}
                    </p>

                    {authenticated && emailMatches ? (
                        <Form action={`/invitations/${token}/accept`} method="post">
                            <Button type="submit" className="w-full">
                                Accept invitation
                            </Button>
                        </Form>
                    ) : authenticated ? (
                        <div className="space-y-3 text-sm">
                            <p className="text-muted-foreground">
                                Sign in with <span className="font-medium">{invitation.email}</span> to accept this
                                invitation.
                            </p>
                            <Button asChild variant="outline" className="w-full">
                                <Link href={login()}>Switch account</Link>
                            </Button>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-2">
                            <Button asChild className="w-full">
                                <Link href={login()}>Log in to accept</Link>
                            </Button>
                            <Button asChild variant="outline" className="w-full">
                                <Link href={register()}>Create account</Link>
                            </Button>
                            <p className="text-center text-xs text-muted-foreground">
                                Register with <span className="font-medium">{invitation.email}</span> to join.
                            </p>
                        </div>
                    )}

                    <p className="text-center text-xs text-muted-foreground">
                        <TextLink href="/">Back to home</TextLink>
                    </p>
                </CardContent>
            </Card>
        </>
    );
}
