<?php

use App\Models\Page;
use App\Models\PlatformSetting;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('allows platform admin to save public marketing copy and a custom demo url', function (): void {
    $admin = User::factory()->create(['is_platform_admin' => true]);

    $this->actingAs($admin)
        ->post('/platform/settings/public', [
            'examples' => [
                'eyebrow' => 'Examples',
                'heading' => 'See our live docs',
                'intro' => 'Custom examples intro.',
                'demos_heading' => 'Live demo sites',
                'demos_description' => 'Hand-picked customer docs.',
            ],
            'home' => [
                'eyebrow' => 'Docs for teams',
                'heading' => 'Ship docs customers read.',
                'tagline' => 'Custom home tagline.',
            ],
            'features' => [
                'eyebrow' => 'Platform',
                'heading' => 'Everything in one place',
                'intro' => 'Custom features intro.',
            ],
            'demos_managed' => true,
            'demos' => [
                [
                    'id' => 'demo-support',
                    'title' => 'Getting started',
                    'subtitle' => 'supportdocs',
                    'badge' => 'classic',
                    'url' => 'https://docs.uplary.com/',
                    'sort_order' => 0,
                    'enabled' => true,
                ],
                [
                    'title' => 'Internal path demo',
                    'subtitle' => 'guides',
                    'badge' => 'gitbook',
                    'url' => '/docs/guides',
                    'sort_order' => 1,
                    'enabled' => true,
                ],
            ],
        ])
        ->assertRedirect(route('platform.dashboard', ['tab' => 'settings', 'section' => 'public']));

    $content = PlatformSetting::instance()->fresh()->public_content;

    expect($content['examples']['heading'])->toBe('See our live docs')
        ->and($content['home']['heading'])->toBe('Ship docs customers read.')
        ->and($content['features']['intro'])->toBe('Custom features intro.')
        ->and($content['demos_managed'])->toBeTrue()
        ->and($content['demos'][0]['url'])->toBe('https://docs.uplary.com/')
        ->and($content['demos'][1]['url'])->toBe('/docs/guides')
        ->and($content['demos'][1]['badge'])->toBe('gitbook');
});

it('normalizes hostname demo urls to https', function (): void {
    $admin = User::factory()->create(['is_platform_admin' => true]);

    $this->actingAs($admin)
        ->post('/platform/settings/public', [
            'demos_managed' => true,
            'demos' => [
                [
                    'title' => 'Uplary docs',
                    'subtitle' => 'docs.uplary.com',
                    'badge' => 'classic',
                    'url' => 'docs.uplary.com',
                    'enabled' => true,
                ],
            ],
        ])
        ->assertRedirect();

    expect(PlatformSetting::instance()->fresh()->public_content['demos'][0]['url'])
        ->toBe('https://docs.uplary.com');
});

it('rejects invalid demo urls', function (): void {
    $admin = User::factory()->create(['is_platform_admin' => true]);

    $this->actingAs($admin)
        ->from('/platform?tab=settings&section=public')
        ->post('/platform/settings/public', [
            'demos_managed' => true,
            'demos' => [
                [
                    'title' => 'Broken',
                    'url' => 'not a url',
                    'enabled' => true,
                ],
            ],
        ])
        ->assertSessionHasErrors('demos.0.url');
});

it('renders custom demo urls and examples copy on the public examples page', function (): void {
    $admin = User::factory()->create(['is_platform_admin' => true]);

    $this->actingAs($admin)
        ->post('/platform/settings/public', [
            'examples' => [
                'heading' => 'See our live docs',
                'demos_heading' => 'Customer sites',
                'demos_description' => 'Production documentation.',
            ],
            'demos_managed' => true,
            'demos' => [
                [
                    'id' => 'live-1',
                    'title' => 'Getting started',
                    'subtitle' => 'supportdocs',
                    'badge' => 'classic',
                    'url' => 'https://docs.uplary.com/',
                    'sort_order' => 0,
                    'enabled' => true,
                ],
                [
                    'title' => 'Hidden demo',
                    'subtitle' => 'secret',
                    'badge' => 'classic',
                    'url' => 'https://hidden.example.com',
                    'sort_order' => 1,
                    'enabled' => false,
                ],
            ],
        ])
        ->assertRedirect();

    $this->get(route('marketing.examples'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('marketing/examples')
            ->where('publicContent.examples.heading', 'See our live docs')
            ->where('publicContent.examples.demos_heading', 'Customer sites')
            ->where('demos.0.name', 'Getting started')
            ->where('demos.0.subdomain', 'supportdocs')
            ->where('demos.0.url', 'https://docs.uplary.com/')
            ->where('demos.0.layout', 'classic')
            ->has('demos', 1)
        );
});

it('falls back to published projects when demo list is not managed', function (): void {
    $owner = User::factory()->onboarded()->create();
    $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

    Page::factory()->published()->create([
        'project_id' => $project->id,
        'workspace_id' => $project->workspace_id,
        'title' => 'Welcome',
        'slug' => 'welcome',
    ]);

    $this->get(route('marketing.examples'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('marketing/examples')
            ->where('demos.0.url', $project->docsBasePath())
            ->where('demos.0.name', $project->name)
            ->where('publicContent.examples.heading', 'Docs that feel premium out of the box')
        );
});

it('forbids non-admins from saving public content', function (): void {
    $user = User::factory()->onboarded()->create();

    $this->actingAs($user)
        ->post('/platform/settings/public', [
            'demos_managed' => true,
            'demos' => [],
        ])
        ->assertForbidden();
});
