<?php

use App\Models\Page;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'ai.api_key' => 'test-key',
        'ai.base_url' => 'https://api.openai.com/v1',
        'ai.model' => 'gpt-4o-mini',
    ]);
});

it('returns 422 without url', function (): void {
    $owner = User::factory()->onboarded()->create();
    $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

    $this->actingAs($owner)
        ->postJson(route('projects.ai.generate', $project), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
});

it('blocks ssrf for localhost urls', function (): void {
    $owner = User::factory()->onboarded()->create();
    $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

    $this->actingAs($owner)
        ->postJson(route('projects.ai.generate', $project), [
            'url' => 'http://127.0.0.1',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
});

it('creates pages from mocked website and ai responses', function (): void {
    $owner = User::factory()->onboarded()->create();
    $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

    Http::fake([
        'https://example.com/*' => Http::sequence()
            ->push("User-agent: *\nAllow: /\n")
            ->push('<html><head><title>Example Product</title><meta name="description" content="Docs tool"></head><body><h1>Welcome</h1><p>Build docs fast.</p></body></html>'),
        'https://api.openai.com/v1/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'project_summary' => 'Example Product helps teams publish docs.',
                            'pages' => [
                                [
                                    'title' => 'Welcome',
                                    'slug' => 'welcome',
                                    'markdown' => "# Welcome\n\nExample Product helps teams publish docs.",
                                ],
                                [
                                    'title' => 'Getting started',
                                    'slug' => 'getting-started',
                                    'markdown' => "# Getting started\n\nVisit your docs site to begin.",
                                ],
                            ],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
        ]),
    ]);

    $beforeCount = Page::query()->withoutGlobalScopes()->where('project_id', $project->id)->count();

    $response = $this->actingAs($owner)
        ->postJson(route('projects.ai.generate', $project), [
            'url' => 'https://example.com',
            'product_description' => 'A documentation platform',
        ]);

    $response->assertAccepted();

    expect(Page::query()->withoutGlobalScopes()->where('project_id', $project->id)->count())->toBe($beforeCount + 2);
    expect(Page::query()->withoutGlobalScopes()->where('project_id', $project->id)->where('slug', 'getting-started')->exists())->toBeTrue();
});

it('returns 503 when ai is not configured', function (): void {
    config(['ai.api_key' => '']);

    $owner = User::factory()->onboarded()->create();
    $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

    $this->actingAs($owner)
        ->postJson(route('projects.ai.generate', $project), [
            'url' => 'https://example.com',
        ])
        ->assertStatus(503);
});
