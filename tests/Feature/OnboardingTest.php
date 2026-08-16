<?php

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a new user can register, verify, and create a workspace', function () {
    $this->post(route('register.store'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('verification.notice', absolute: false));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
    $user->markEmailAsVerified();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.create'));

    $this->actingAs($user)
        ->get(route('onboarding.subdomain', ['subdomain' => 'ada-docs']))
        ->assertOk()
        ->assertJson([
            'available' => true,
            'reserved' => false,
            'valid' => true,
        ]);

    $this->actingAs($user->fresh())
        ->post(route('onboarding.store'), [
            'name' => 'Ada Lovelace',
            'company_name' => 'Analytical Engines',
            'workspace_name' => 'Ada Docs',
            'subdomain' => 'ada-docs',
        ])
        ->assertRedirect(route('dashboard'));

    $user->refresh();

    expect($user->hasCompletedOnboarding())->toBeTrue()
        ->and($user->workspaces)->toHaveCount(1)
        ->and($user->currentWorkspace?->name)->toBe('Ada Docs');

    $project = Project::query()->withoutGlobalScopes()->where('subdomain', 'ada-docs')->first();

    expect($project)->not->toBeNull()
        ->and($project?->name)->toBe('Getting started');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Ada Docs')
        ->assertSee('Getting started')
        ->assertSee('ada-docs');
});

test('reserved and taken subdomains are rejected', function () {
    $owner = User::factory()->onboarded()->create();
    $existing = Project::query()
        ->withoutGlobalScopes()
        ->where('workspace_id', $owner->current_workspace_id)
        ->firstOrFail();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('onboarding.subdomain', ['subdomain' => 'www']))
        ->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('reserved', true);

    $this->actingAs($user)
        ->post(route('onboarding.store'), [
            'name' => $user->name,
            'company_name' => 'Contoso',
            'workspace_name' => 'Contoso Docs',
            'subdomain' => 'www',
        ])
        ->assertSessionHasErrors('subdomain');

    $this->actingAs($user)
        ->post(route('onboarding.store'), [
            'name' => $user->name,
            'company_name' => 'Contoso',
            'workspace_name' => 'Contoso Docs',
            'subdomain' => $existing->subdomain,
        ])
        ->assertSessionHasErrors('subdomain');
});

test('workspace data cannot be accessed by members of another workspace', function () {
    $alice = User::factory()->onboarded()->create(['name' => 'Alice']);
    $bob = User::factory()->onboarded()->create(['name' => 'Bob']);

    $aliceProject = Project::query()
        ->withoutGlobalScopes()
        ->where('workspace_id', $alice->current_workspace_id)
        ->firstOrFail();

    $this->actingAs($bob)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee($aliceProject->name)
        ->assertDontSee($aliceProject->subdomain);

    $this->actingAs($bob)
        ->get(route('projects.show', $aliceProject))
        ->assertNotFound();

    expect(
        Workspace::query()->findOrFail($alice->current_workspace_id)->hasMember($bob)
    )->toBeFalse();
});
