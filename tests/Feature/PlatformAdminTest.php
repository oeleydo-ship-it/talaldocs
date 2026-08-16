<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function platformAdmin(): User
{
    return User::factory()->create([
        'name' => 'Platform Admin',
        'email' => 'admin@anytdocs.test',
        'email_verified_at' => now(),
        'is_platform_admin' => true,
    ]);
}

test('non platform admins cannot access platform area', function () {
    $user = User::factory()->onboarded()->create();

    $this->actingAs($user)
        ->get('/platform')
        ->assertForbidden();

    $this->actingAs($user)
        ->get('/platform/settings')
        ->assertForbidden();
});

test('platform admins can access platform settings', function () {
    $admin = platformAdmin();

    $this->actingAs($admin)
        ->get('/platform/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/dashboard')
            ->where('tab', 'settings')
            ->has('platformSettings')
            ->has('aiSettings'));
});

test('platform admins can view platform dashboard', function () {
    $admin = platformAdmin();

    $this->actingAs($admin)
        ->get('/platform')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/dashboard')
            ->has('stats'));
});

test('platform admin can suspend and restore a user', function () {
    $admin = platformAdmin();
    $target = User::factory()->onboarded()->create();

    $this->actingAs($admin)
        ->post("/platform/users/{$target->id}/suspend")
        ->assertRedirect();

    expect($target->fresh()->suspended_at)->not->toBeNull();

    $this->actingAs($admin)
        ->post("/platform/users/{$target->id}/restore")
        ->assertRedirect();

    expect($target->fresh()->suspended_at)->toBeNull();
});

test('platform admin can suspend and restore a workspace', function () {
    $admin = platformAdmin();
    $owner = User::factory()->onboarded()->create();
    $workspace = Workspace::query()->findOrFail($owner->current_workspace_id);

    $this->actingAs($admin)
        ->post("/platform/workspaces/{$workspace->id}/suspend")
        ->assertRedirect();

    expect($workspace->fresh()->suspended_at)->not->toBeNull();

    $this->actingAs($admin)
        ->post("/platform/workspaces/{$workspace->id}/restore")
        ->assertRedirect();

    expect($workspace->fresh()->suspended_at)->toBeNull();
});
