import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { login } from '@/routes';

type Props = {
    email: string;
};

export default function InvitationExpired({ email }: Props) {
    return (
        <>
            <Head title="Invitation expired" />
            <Card className="w-full max-w-md shadow-sm">
                <CardHeader>
                    <CardTitle>Invitation no longer available</CardTitle>
                    <CardDescription>
                        The invitation for {email} has expired or was already accepted.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    <p className="text-sm text-muted-foreground">
                        Ask your workspace admin to send a new invitation.
                    </p>
                    <Button asChild variant="outline" className="w-full">
                        <Link href={login()}>Log in</Link>
                    </Button>
                </CardContent>
            </Card>
        </>
    );
}
