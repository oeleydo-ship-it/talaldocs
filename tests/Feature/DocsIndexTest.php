<?php

use App\Enums\PageStatus;
use App\Models\DocIndexChunk;
use App\Models\Page;
use App\Models\Project;
use App\Models\User;
use App\Services\DocsAskService;
use App\Services\DocsIndexService;
use App\Support\PagePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('indexes published pages into knowledge chunks', function (): void {
    $owner = User::factory()->onboarded()->create();
    $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

    $page = Page::factory()->create([
        'project_id' => $project->id,
        'workspace_id' => $project->workspace_id,
        'published_markdown' => "## Install\n\nRun npm install to get started.\n\n## Configure\n\nEdit the config file.",
        'markdown' => "## Install\n\nRun npm install to get started.\n\n## Configure\n\nEdit the config file.",
    ]);

    app(PagePublisher::class)->publish($page, $owner);

    expect(DocIndexChunk::query()->where('page_id', $page->id)->count())->toBeGreaterThan(0);
    expect(DocIndexChunk::query()->where('page_id', $page->id)->pluck('heading')->filter()->all())
        ->toContain('Install', 'Configure');
});

it('uses the knowledge index for ask ai retrieval', function (): void {
    config([
        'ai.api_key' => 'test-key',
        'ai.base_url' => 'https://api.openai.com/v1',
        'ai.model' => 'gpt-4o-mini',
    ]);

    $owner = User::factory()->onboarded()->create();
    $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

    $page = Page::factory()->create([
        'project_id' => $project->id,
        'workspace_id' => $project->workspace_id,
        'title' => 'Installation guide',
        'slug' => 'installation',
        'published_markdown' => "## Install\n\nRun `npm install` to install dependencies.",
        'markdown' => "## Install\n\nRun `npm install` to install dependencies.",
    ]);

    app(PagePublisher::class)->publish($page, $owner);

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

    $this->postJson(route('docs.ask', $project->subdomain), [
        'question' => 'How do I install dependencies?',
    ])->assertOk()
        ->assertJsonPath('sources.0.slug', 'installation');

    Http::assertSent(function ($request): bool {
        $body = $request->data();
        $content = $body['messages'][1]['content'] ?? '';

        return str_contains($content, 'Run npm install to install dependencies.');
    });
});

it('rebuilds the project knowledge index from settings', function (): void {
    $owner = User::factory()->onboarded()->create();
    $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

    Page::factory()->published()->create([
        'project_id' => $project->id,
        'workspace_id' => $project->workspace_id,
        'title' => 'Welcome',
        'published_markdown' => '# Welcome\n\nHello world.',
    ]);

    expect(DocIndexChunk::query()->where('project_id', $project->id)->count())->toBe(0);

    $this->actingAs($owner)
        ->post(route('projects.ai-index', $project->id))
        ->assertRedirect();

    expect(DocIndexChunk::query()->where('project_id', $project->id)->count())->toBeGreaterThan(0);
    expect(app(DocsIndexService::class)->stats($project->fresh())['pages'])->toBe(1);
});

it('removes knowledge chunks when a page is unpublished', function (): void {
    $owner = User::factory()->onboarded()->create();
    $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

    $page = Page::factory()->published()->create([
        'project_id' => $project->id,
        'workspace_id' => $project->workspace_id,
        'published_markdown' => 'Published content for AI.',
    ]);

    app(DocsIndexService::class)->indexPage($page);
    expect(DocIndexChunk::query()->where('page_id', $page->id)->count())->toBeGreaterThan(0);

    app(PagePublisher::class)->unpublish($page, $owner);

    expect(DocIndexChunk::query()->where('page_id', $page->id)->count())->toBe(0);
});
