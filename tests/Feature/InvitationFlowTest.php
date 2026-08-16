<?php

namespace Tests\Feature;

use App\Mail\WorkspaceInvitationMail;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvitationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_resend_cancel_and_accept(): void
    {
        Mail::fake();

        $owner = User::factory()->onboarded()->create();
        $workspace = Workspace::query()->findOrFail($owner->current_workspace_id);

        $this->actingAs($owner)
            ->post(route('members.invite'), [
                'email' => 'invitee@example.com',
                'role' => 'editor',
            ])
            ->assertRedirect();

        Mail::assertSent(WorkspaceInvitationMail::class);

        $invitation = Invitation::query()->withoutGlobalScopes()->where('email', 'invitee@example.com')->firstOrFail();
        $originalToken = $invitation->token;

        $this->actingAs($owner)
            ->get(route('invitations.show', $originalToken))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('invitations/show'));

        $this->actingAs($owner)
            ->post(route('members.invitations.resend', $invitation->id))
            ->assertRedirect();

        Mail::assertSent(WorkspaceInvitationMail::class, 2);

        $invitation->refresh();
        $this->assertNotSame($originalToken, $invitation->token);

        $invitee = User::factory()->create(['email' => 'invitee@example.com']);

        $this->actingAs($invitee)
            ->post(route('invitations.accept', $invitation->token))
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($workspace->fresh()->hasMember($invitee));
        $this->assertSame($workspace->id, $invitee->fresh()->current_workspace_id);

        $this->actingAs($owner)
            ->post(route('members.invite'), [
                'email' => 'another@example.com',
                'role' => 'viewer',
            ])
            ->assertRedirect();

        $pending = Invitation::query()->withoutGlobalScopes()->where('email', 'another@example.com')->firstOrFail();

        $this->actingAs($owner)
            ->delete(route('members.invitations.cancel', $pending->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('invitations', ['id' => $pending->id]);
    }

    public function test_accept_rejects_wrong_email(): void
    {
        $owner = User::factory()->onboarded()->create();

        $this->actingAs($owner)
            ->post(route('members.invite'), [
                'email' => 'invitee@example.com',
                'role' => 'editor',
            ]);

        $invitation = Invitation::query()->withoutGlobalScopes()->where('email', 'invitee@example.com')->firstOrFail();
        $other = User::factory()->create(['email' => 'wrong@example.com']);

        $this->actingAs($other)
            ->post(route('invitations.accept', $invitation->token))
            ->assertForbidden();

        $this->assertFalse(WorkspaceMember::query()->where('user_id', $other->id)->exists());
    }
}
