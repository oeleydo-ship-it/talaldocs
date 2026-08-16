<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Concerns\ResolvesWorkspaceProject;
use App\Mail\WorkspaceInvitationMail;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\WorkspaceMember;
use App\Support\Audit;
use App\Support\PlanGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    use ResolvesWorkspaceProject;

    public function __construct(private PlanGate $plans, private Audit $audit) {}

    public function index(Request $request): Response
    {
        $workspace = $this->workspace($request);
        $this->authorize('view', $workspace);

        $members = WorkspaceMember::query()
            ->with('user:id,name,email')
            ->where('workspace_id', $workspace->id)
            ->get();

        $invitations = Invitation::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('accepted_at')
            ->latest()
            ->get()
            ->map(fn (Invitation $invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'token' => $invitation->token,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
                'created_at' => $invitation->created_at?->toIso8601String(),
            ]);

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->where('workspace_id', $workspace->id)
            ->latest('id')
            ->limit(50)
            ->get();

        return Inertia::render('members/index', [
            'members' => $members->map(fn (WorkspaceMember $member): array => [
                'id' => $member->id,
                'role' => $member->role->value,
                'user' => $member->user,
            ]),
            'invitations' => $invitations,
            'auditLogs' => $logs->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'user' => $log->user?->name,
                'created_at' => $log->created_at?->toIso8601String(),
                'metadata' => $log->metadata,
            ]),
            'canManage' => $request->user()?->can('manageMembers', $workspace) ?? false,
            'roles' => array_map(fn (WorkspaceRole $role): string => $role->value, WorkspaceRole::cases()),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request);
        $this->authorize('manageMembers', $workspace);
        $this->plans->assertCanInvite($workspace);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::enum(WorkspaceRole::class)],
        ]);

        abort_if(WorkspaceRole::from($data['role']) === WorkspaceRole::Owner, 422);

        $invitation = Invitation::query()->create([
            'workspace_id' => $workspace->id,
            'email' => strtolower($data['email']),
            'role' => $data['role'],
            'token' => Str::random(48),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(14),
        ]);

        Mail::to($invitation->email)->send(new WorkspaceInvitationMail($invitation));
        $this->audit->record($workspace->id, 'member.invited', $request->user(), $invitation, [
            'email' => $invitation->email,
        ]);

        return back();
    }

    public function updateRole(Request $request, int $member): RedirectResponse
    {
        $workspace = $this->workspace($request);
        $this->authorize('manageMembers', $workspace);

        $data = $request->validate([
            'role' => ['required', Rule::enum(WorkspaceRole::class)],
        ]);

        $record = WorkspaceMember::query()->where('workspace_id', $workspace->id)->findOrFail($member);
        abort_if($record->role === WorkspaceRole::Owner, 422);
        abort_if(WorkspaceRole::from($data['role']) === WorkspaceRole::Owner, 422);

        $record->update(['role' => $data['role']]);
        $this->audit->record($workspace->id, 'member.role', $request->user(), $record);

        return back();
    }

    public function destroy(Request $request, int $member): RedirectResponse
    {
        $workspace = $this->workspace($request);
        $this->authorize('manageMembers', $workspace);
        $record = WorkspaceMember::query()->where('workspace_id', $workspace->id)->findOrFail($member);
        abort_if($record->role === WorkspaceRole::Owner, 422);
        $record->delete();
        $this->audit->record($workspace->id, 'member.removed', $request->user(), $record);

        return back();
    }

    public function resendInvitation(Request $request, int $invitation): RedirectResponse
    {
        $workspace = $this->workspace($request);
        $this->authorize('manageMembers', $workspace);

        $record = Invitation::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('accepted_at')
            ->findOrFail($invitation);

        $record->forceFill([
            'token' => Str::random(48),
            'expires_at' => now()->addDays(14),
        ])->save();

        Mail::to($record->email)->send(new WorkspaceInvitationMail($record));
        $this->audit->record($workspace->id, 'member.invitation_resent', $request->user(), $record, [
            'email' => $record->email,
        ]);

        return back()->with('status', 'Invitation resent.');
    }

    public function cancelInvitation(Request $request, int $invitation): RedirectResponse
    {
        $workspace = $this->workspace($request);
        $this->authorize('manageMembers', $workspace);

        $record = Invitation::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('accepted_at')
            ->findOrFail($invitation);

        $this->audit->record($workspace->id, 'member.invitation_cancelled', $request->user(), $record, [
            'email' => $record->email,
        ]);

        $record->delete();

        return back()->with('status', 'Invitation cancelled.');
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::query()->withoutGlobalScopes()->where('token', $token)->firstOrFail();
        abort_unless($invitation->isOpen(), 410);

        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless(strcasecmp($user->email, $invitation->email) === 0, 403);

        WorkspaceMember::query()->firstOrCreate([
            'workspace_id' => $invitation->workspace_id,
            'user_id' => $user->id,
        ], [
            'role' => $invitation->role,
        ]);

        $invitation->forceFill(['accepted_at' => now()])->save();
        $user->forceFill([
            'current_workspace_id' => $invitation->workspace_id,
            'onboarded_at' => $user->onboarded_at ?? now(),
        ])->save();

        return redirect()->route('dashboard')->with('status', 'You joined the workspace.');
    }
}
