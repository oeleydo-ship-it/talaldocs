<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.installer_enabled_in_tests' => true]);
});

test('guests are redirected to install when no platform admin exists', function () {
    $this->get('/')->assertRedirect(route('install.show'));
});

test('install page is shown when setup is required', function () {
    $this->get(route('install.show'))
        ->assertOk()
        ->assertSee('Create the first platform superadmin', false)
        ->assertSee('Superadmin email', false);
});

test('superadmin can be created through the installer', function () {
    $response = $this->from(route('install.show'))->post(route('install.store'), [
        'name' => 'Platform Owner',
        'email' => 'owner@example.com',
        'password' => 'Password1!secret',
        'password_confirmation' => 'Password1!secret',
        'app_name' => 'Talal Docs',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('platform.dashboard'));

    $this->assertDatabaseHas('users', [
        'email' => 'owner@example.com',
        'is_platform_admin' => 1,
    ]);

    $user = User::query()->where('email', 'owner@example.com')->firstOrFail();

    expect($user->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
    $this->get('/')->assertOk();
});

test('installer is skipped when a platform admin already exists', function () {
    User::factory()->create([
        'email' => 'admin@example.com',
        'is_platform_admin' => true,
        'email_verified_at' => now(),
    ]);

    $this->get('/')->assertOk();
    $this->get(route('install.show'))->assertRedirect(route('home'));
});
