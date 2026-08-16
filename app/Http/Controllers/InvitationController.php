<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $invitation = Invitation::query()->withoutGlobalScopes()->with('workspace:id,name,slug')->where('token', $token)->firstOrFail();

        if (! $invitation->isOpen()) {
            return Inertia::render('invitations/expired', [
                'email' => $invitation->email,
            ]);
        }

        $user = $request->user();

        return Inertia::render('invitations/show', [
            'invitation' => [
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'workspace' => $invitation->workspace?->only(['id', 'name', 'slug']),
                'expires_at' => $invitation->expires_at?->toIso8601String(),
            ],
            'token' => $token,
            'authenticated' => $user !== null,
            'emailMatches' => $user !== null && strcasecmp($user->email, $invitation->email) === 0,
        ]);
    }
}
