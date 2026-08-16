<?php

use App\Enums\AiGenerationJobStatus;
use App\Jobs\GenerateDocumentationFromWebsiteJob;
use App\Models\AiGenerationJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'ai.api_key' => 'test-key',
        'ai.base_url' => 'https://api.openai.com/v1',
        'ai.model' => 'gpt-4o-mini',
        'queue.default' => 'sync',
    ]);
});

it('requires authentication to start and poll ai generation', function (): void {
    $owner = User::factory()->onboarded()->create();
    $project = \App\Models\Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

    $this->postJson(route('projects.ai.generate', $project->id), [
        'url' => 'https://example.com',
    ])->assertUnauthorized();

    $job = AiGenerationJob::query()->create([
        'workspace_id' => $project->workspace_id,
        'project_id' => $project->id,
        'user_id' => $owner->id,
        'source_url' => 'https://example.com',
        'status' => AiGenerationJobStatus::Processing,
    ]);

    $this->getJson(route('projects.ai.show', [$project->id, $job->id]))
        ->assertUnauthorized();
});

it('returns ai job status for polling without throttling', function (): void {
    $owner = User::factory()->onboarded()->create();
    $project = \App\Models\Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

    $job = AiGenerationJob::query()->create([
        'workspace_id' => $project->workspace_id,
        'project_id' => $project->id,
        'user_id' => $owner->id,
        'source_url' => 'https://example.com',
        'status' => AiGenerationJobStatus::Processing,
    ]);

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($owner)
            ->getJson(route('projects.ai.show', [$project->id, $job->id]))
            ->assertOk()
            ->assertJsonPath('job.id', $job->id)
            ->assertJsonPath('job.status', 'processing');
    }
});

it('starts documentation generation and completes via sync queue', function (): void {
    Http::fake([
        'https://example.com' => Http::response('<html><head><title>Example</title></head><body><h1>Hello</h1><p>Product info.</p></body></html>'),
        'https://example.com/robots.txt' => Http::response("User-agent: *\nAllow: /"),
        'https://api.openai.com/v1/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'project_summary' => 'Example product docs',
                            'pages' => [
                                [
                                    'title' => 'Welcome',
                                    'slug' => 'welcome',
                                    'markdown' => "# Welcome\n\nHello from Example.",
                                ],
                            ],
                        ]),
                    ],
                ],
            ],
        ]),
    ]);

    $owner = User::factory()->onboarded()->create();
    $project = \App\Models\Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

    $response = $this->actingAs($owner)->postJson(route('projects.ai.generate', $project->id), [
        'url' => 'https://example.com',
        'product_description' => 'An example SaaS product.',
        'publish_immediately' => false,
    ]);

    $response->assertAccepted();
    $jobId = $response->json('job.id');

    $this->actingAs($owner)
        ->getJson(route('projects.ai.show', [$project->id, $jobId]))
        ->assertOk()
        ->assertJsonPath('job.status', 'completed')
        ->assertJsonPath('job.result_summary.pages.0.title', 'Welcome');

    expect(\App\Models\Page::query()->withoutGlobalScopes()->where('project_id', $project->id)->count())->toBeGreaterThan(0);
});

it('queues documentation generation when queue driver is database', function (): void {
    Queue::fake();
    config(['queue.default' => 'database']);

    Http::fake([
        'https://example.com' => Http::response('<html><head><title>Example</title></head><body><p>Hi</p></body></html>'),
        'https://example.com/robots.txt' => Http::response("User-agent: *\nAllow: /"),
    ]);

    $owner = User::factory()->onboarded()->create();
    $project = \App\Models\Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

    $this->actingAs($owner)->postJson(route('projects.ai.generate', $project->id), [
        'url' => 'https://example.com',
    ])
        ->assertAccepted()
        ->assertJsonPath('requires_queue_worker', true);

    Queue::assertPushed(GenerateDocumentationFromWebsiteJob::class);
});

it('returns queue worker hint metadata while a job is pending', function (): void {
    config(['queue.default' => 'database']);

    $owner = User::factory()->onboarded()->create();
    $project = \App\Models\Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

    $job = AiGenerationJob::query()->create([
        'workspace_id' => $project->workspace_id,
        'project_id' => $project->id,
        'user_id' => $owner->id,
        'source_url' => 'https://example.com',
        'status' => AiGenerationJobStatus::Pending,
    ]);

    $job->forceFill(['created_at' => now()->subMinute()])->save();

    $this->actingAs($owner)
        ->getJson(route('projects.ai.show', [$project->id, $job->id]))
        ->assertOk()
        ->assertJsonPath('requires_queue_worker', true)
        ->assertJsonPath('pending_stale', true)
        ->assertJsonPath('job.status', 'pending');
});

it('surfaces ai provider failures on the job record', function (): void {
    Http::fake([
        'https://example.com' => Http::response('<html><head><title>Example</title></head><body><h1>Hello</h1></body></html>'),
        'https://example.com/robots.txt' => Http::response("User-agent: *\nAllow: /"),
        'https://api.openai.com/v1/*' => Http::response([
            'error' => ['message' => 'Incorrect API key provided'],
        ], 401),
    ]);

    $owner = User::factory()->onboarded()->create();
    $project = \App\Models\Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

    $response = $this->actingAs($owner)->postJson(route('projects.ai.generate', $project->id), [
        'url' => 'https://example.com',
    ]);

    $response->assertAccepted();
    $jobId = $response->json('job.id');

    $this->actingAs($owner)
        ->getJson(route('projects.ai.show', [$project->id, $jobId]))
        ->assertOk()
        ->assertJsonPath('job.status', 'failed')
        ->assertJsonPath('job.error', '401 Unauthorized: Incorrect API key provided');
});
