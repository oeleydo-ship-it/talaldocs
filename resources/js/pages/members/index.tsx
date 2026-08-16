import { Head, router, useForm } from '@inertiajs/react';
import { Copy, Mail, RotateCcw, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { toast } from 'sonner';

type Invitation = {
    id: number;
    email: string;
    role: string;
    token: string;
    expires_at: string | null;
    created_at: string | null;
};

type Props = {
    members: { id: number; role: string; user: { id: number; name: string; email: string } | null }[];
    invitations: Invitation[];
    auditLogs: { id: number; action: string; user: string | null; created_at: string | null }[];
    canManage: boolean;
    roles: string[];
};

export default function MembersIndex({ members, invitations, auditLogs, canManage, roles }: Props) {
    const invite = useForm({ email: '', role: 'editor' });

    const copyInviteLink = (token: string) => {
        const url = `${window.location.origin}/invitations/${token}`;
        void navigator.clipboard.writeText(url).then(() => {
            toast.success('Invite link copied');
        });
    };

    return (
        <>
            <Head title="Members" />
            <PageContainer>
                <PageHeader
                    title="Members"
                    description="Roles, invitations, and the workspace audit log."
                />

                {canManage && (
                    <Card className="shadow-sm">
                        <CardHeader>
                            <CardTitle>Invite</CardTitle>
                            <CardDescription>
                                Invites are emailed (logged locally when MAIL_MAILER=log).
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                className="flex flex-wrap gap-2"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    invite.post('/members/invitations');
                                }}
                            >
                                <Input
                                    type="email"
                                    placeholder="teammate@company.com"
                                    value={invite.data.email}
                                    onChange={(event) => invite.setData('email', event.target.value)}
                                    className="max-w-sm"
                                />
                                <select
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                                    value={invite.data.role}
                                    onChange={(event) => invite.setData('role', event.target.value)}
                                >
                                    {roles
                                        .filter((role) => role !== 'owner')
                                        .map((role) => (
                                            <option key={role} value={role}>
                                                {role}
                                            </option>
                                        ))}
                                </select>
                                <Button type="submit" disabled={invite.processing}>
                                    <Mail className="size-4" />
                                    Send invite
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <Card className="shadow-sm">
                    <CardHeader>
                        <CardTitle>Team</CardTitle>
                        <CardDescription>Workspace members and their roles.</CardDescription>
                    </CardHeader>
                    <CardContent className="divide-y rounded-lg border bg-muted/20">
                        {members.map((member) => (
                            <div
                                key={member.id}
                                className="flex flex-wrap items-center justify-between gap-3 p-4 first:rounded-t-lg last:rounded-b-lg"
                            >
                                <div>
                                    <p className="font-medium">{member.user?.name}</p>
                                    <p className="text-sm text-muted-foreground">{member.user?.email}</p>
                                </div>
                                {canManage && member.role !== 'owner' ? (
                                    <div className="flex gap-2">
                                        <select
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm capitalize shadow-xs"
                                            value={member.role}
                                            onChange={(event) =>
                                                router.patch(`/members/${member.id}`, {
                                                    role: event.target.value,
                                                })
                                            }
                                        >
                                            {roles
                                                .filter((role) => role !== 'owner')
                                                .map((role) => (
                                                    <option key={role} value={role}>
                                                        {role}
                                                    </option>
                                                ))}
                                        </select>
                                        <Button
                                            variant="ghost"
                                            className="text-destructive hover:text-destructive"
                                            onClick={() => router.delete(`/members/${member.id}`)}
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                ) : (
                                    <Badge variant="outline" className="capitalize">
                                        {member.role}
                                    </Badge>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card className="shadow-sm">
                        <CardHeader>
                            <CardTitle>Pending invitations</CardTitle>
                            <CardDescription>Resend or cancel open invites.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {invitations.map((invitation) => (
                                <div
                                    key={invitation.id}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-muted/20 px-3 py-2"
                                >
                                    <div>
                                        <p className="font-medium">{invitation.email}</p>
                                        <p className="text-xs capitalize text-muted-foreground">
                                            {invitation.role}
                                            {invitation.expires_at && (
                                                <> · expires {new Date(invitation.expires_at).toLocaleDateString()}</>
                                            )}
                                        </p>
                                    </div>
                                    {canManage && (
                                        <div className="flex gap-1">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                title="Copy invite link"
                                                onClick={() => copyInviteLink(invitation.token)}
                                            >
                                                <Copy className="size-4" />
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                title="Resend invitation"
                                                onClick={() =>
                                                    router.post(`/members/invitations/${invitation.id}/resend`)
                                                }
                                            >
                                                <RotateCcw className="size-4" />
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="text-destructive hover:text-destructive"
                                                title="Cancel invitation"
                                                onClick={() =>
                                                    router.delete(`/members/invitations/${invitation.id}`)
                                                }
                                            >
                                                <X className="size-4" />
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            ))}
                            {invitations.length === 0 && (
                                <p className="text-muted-foreground">No open invitations.</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader>
                            <CardTitle>Recent activity</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {auditLogs.map((log) => (
                                <div
                                    key={log.id}
                                    className="rounded-lg border bg-muted/20 px-3 py-2 text-muted-foreground"
                                >
                                    {log.action} · {log.user ?? 'System'} ·{' '}
                                    {log.created_at ? new Date(log.created_at).toLocaleString() : ''}
                                </div>
                            ))}
                            {auditLogs.length === 0 && (
                                <p className="text-muted-foreground">No recent activity.</p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </PageContainer>
        </>
    );
}

MembersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Members', href: '/members' },
    ],
};
