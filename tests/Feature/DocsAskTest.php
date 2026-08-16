<?php

use App\Models\DocumentationVersion;
use App\Models\Language;
use App\Models\Page;
use App\Models\Project;
use App\Models\ProjectLanguage;
use App\Models\User;
use App\Models\Workspace;
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

it('returns 503 when ai is not configured', function (): void {
    config(['ai.api_key' => '']);

    $owner = User::factory()->onboarded()->create();
    $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();
    Page::factory()->published()->create([
        'project_id' => $project->id,
        'workspace_id' => $project->workspace_id,
    ]);

    $this->postJson(route('docs.ask', $project->subdomain), [
        'question' => 'How do I install?',
    ])->assertStatus(503)
        ->assertJsonPath('message', 'AI Ask is not configured. Enable AI in Platform → Settings.');
});

it('validates the question field', function (): void {
    $owner = User::factory()->onboarded()->create();
    $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

    $this->postJson(route('docs.ask', $project->subdomain), [
        'question' => 'ab',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['question']);
});

it('returns an answer with sources from relevant published pages', function (): void {
    $owner = User::factory()->onboarded()->create();
    $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

    Page::factory()->published()->create([
        'project_id' => $project->id,
        'workspace_id' => $project->workspace_id,
        'title' => 'Installation guide',
        'slug' => 'installation',
        'published_markdown' => "# Installation\n\nRun `npm install` to install dependencies.",
    ]);

    Http::fake([
        'https://api.openai.com/v1/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => 'Run `npm install` to install dependencies.',
                    ],
                ],
            ],
        ]),
    ]);

    $response = $this->postJson(route('docs.ask', $project->subdomain), [
        'question' => 'How do I install dependencies?',
    ]);

    $response->assertOk()
        ->assertJsonPath('answer', 'Run `npm install` to install dependencies.')
        ->assertJsonCount(1, 'sources')
        ->assertJsonPath('sources.0.slug', 'installation')
        ->assertJsonPath('sources.0.title', 'Installation guide');
});

it('blocks ask requests for private docs', function (): void {
    $owner = User::factory()->onboarded()->create();
    $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();
    $project->forceFill(['visibility' => \App\Enums\ProjectVisibility::Private])->save();

    $this->postJson(route('docs.ask', $project->subdomain), [
        'question' => 'What is this project?',
    ])->assertForbidden();
});

it('scopes retrieval to default version and locale', function (): void {
    Language::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true]);

    $workspace = Workspace::factory()->create();
    $language = Language::query()->where('code', 'en')->firstOrFail();

    $project = Project::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Scoped docs',
        'slug' => 'scoped-docs',
        'subdomain' => 'scoped-docs',
        'visibility' => \App\Enums\ProjectVisibility::Public,
        'use_path_urls' => false,
    ]);

    $defaultVersion = DocumentationVersion::query()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'name' => 'Latest',
        'slug' => 'latest',
        'is_default' => true,
    ]);

    $otherVersion = DocumentationVersion::query()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'name' => 'Legacy',
        'slug' => 'legacy',
        'is_default' => false,
    ]);

    ProjectLanguage::query()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'language_id' => $language->id,
        'is_default' => true,
    ]);

    Page::query()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'documentation_version_id' => $defaultVersion->id,
        'language_id' => $language->id,
        'title' => 'Default version page',
        'slug' => 'default-page',
        'published_markdown' => '# Default version page',
        'published_html' => '<h1>Default version page</h1>',
        'status' => \App\Enums\PageStatus::Published,
        'published_at' => now(),
        'position' => 0,
    ]);

    Page::query()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'documentation_version_id' => $otherVersion->id,
        'language_id' => $language->id,
        'title' => 'Legacy version page',
        'slug' => 'legacy-page',
        'published_markdown' => '# Legacy version page',
        'published_html' => '<h1>Legacy version page</h1>',
        'status' => \App\Enums\PageStatus::Published,
        'published_at' => now(),
        'position' => 0,
    ]);

    Http::fake([
        'https://api.openai.com/v1/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => 'Answer from default docs.',
                    ],
                ],
            ],
        ]),
    ]);

    $response = $this->postJson(route('docs.ask', $project->subdomain), [
        'question' => 'default version page',
    ]);

    $response->assertOk()
        ->assertJsonPath('sources.0.slug', 'default-page');

    expect(collect($response->json('sources'))->pluck('slug'))->not->toContain('legacy-page');
});

it('passes ai ask enabled flag to public docs page', function (): void {
    $owner = User::factory()->onboarded()->create();
    $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();
    $page = Page::factory()->published()->create([
        'project_id' => $project->id,
        'workspace_id' => $project->workspace_id,
        'slug' => 'welcome',
    ]);

    $this->get($project->docsBasePath().'/latest/en/'.$page->slug)
        ->assertOk()
        ->assertInertia(fn ($assert) => $assert
            ->where('aiAskEnabled', true)
            ->where('askUrl', $project->docsBasePath().'/ask')
        );
});

it('sends temperature one for kimi ask requests', function (): void {
    config([
        'ai.provider' => 'kimi',
        'ai.api_key' => 'kimi-test-key',
        'ai.base_url' => 'https://api.moonshot.ai/v1',
        'ai.model' => 'kimi-k3',
    ]);

    $owner = User::factory()->onboarded()->create();
    $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

    Page::factory()->published()->create([
        'project_id' => $project->id,
        'workspace_id' => $project->workspace_id,
        'title' => 'Welcome',
        'slug' => 'welcome',
        'published_markdown' => "# Welcome\n\nThis is the welcome page.",
    ]);

    Http::fake([
        'https://api.moonshot.ai/v1/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => 'This is the welcome page.',
                    ],
                ],
            ],
        ]),
    ]);

    $this->postJson(route('docs.ask', $project->subdomain), [
        'question' => 'What is on the welcome page?',
    ])->assertOk();

    Http::assertSent(fn ($request) => $request->url() === 'https://api.moonshot.ai/v1/chat/completions'
        && $request['model'] === 'kimi-k3'
        && (float) $request['temperature'] === 1.0);
});
