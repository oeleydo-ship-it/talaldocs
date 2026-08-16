<?php

use App\Models\Page;
use App\Models\PageRevision;
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

/**
 * @return array{0: Project, 1: Page}
 */
function generatablePage(User $owner, string $markdown = '', string $title = 'Authentication'): array
{
    $project = Project::query()
        ->withoutGlobalScopes()
        ->where('workspace_id', $owner->current_workspace_id)
        ->firstOrFail();

    $page = Page::factory()->create([
        'project_id' => $project->id,
        'workspace_id' => $project->workspace_id,
        'title' => $title,
        'markdown' => $markdown,
    ]);

    return [$project, $page];
}

function fakeGenerateResponse(
    string $markdown = "# Authentication\n\nUse API keys in the `Authorization` header.",
    ?string $title = null,
    ?string $subtitle = null,
): void {
    Http::fake([
        'https://api.openai.com/v1/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'summary' => 'Explains API key authentication.',
                            'title' => $title,
                            'subtitle' => $subtitle,
                            'markdown' => $markdown,
                        ]),
                    ],
                ],
            ],
        ]),
    ]);
}

it('generates a page proposal without mutating it', function (): void {
    fakeGenerateResponse();

    $owner = User::factory()->onboarded()->create();
    [$project, $page] = generatablePage($owner);

    $response = $this->actingAs($owner)
        ->postJson(route('pages.ai.generate', [$project->id, $page->id]), [
            'description' => 'Explain how API key authentication works for developers.',
        ])
        ->assertOk()
        ->assertJsonPath('proposal.markdown', "# Authentication\n\nUse API keys in the `Authorization` header.")
        ->assertJsonPath('proposal.summary', 'Explains API key authentication.');

    expect($response->json('html'))->toContain('Authentication');

    expect($page->fresh()?->markdown)->toBe('')
        ->and(PageRevision::query()->withoutGlobalScopes()->where('page_id', $page->id)->count())->toBe(0);
});

it('applies generated content and records a restorable revision', function (): void {
    fakeGenerateResponse();

    $owner = User::factory()->onboarded()->create();
    [$project, $page] = generatablePage($owner, '# Old draft', 'Auth');

    $this->actingAs($owner)
        ->postJson(route('pages.ai.apply-generation', [$project->id, $page->id]), [
            'markdown' => "# Authentication\n\nUse API keys in the `Authorization` header.",
            'title' => 'Authentication',
            'subtitle' => 'Secure your API requests',
        ])
        ->assertOk()
        ->assertJsonPath('markdown', "# Authentication\n\nUse API keys in the `Authorization` header.")
        ->assertJsonPath('title', 'Authentication')
        ->assertJsonPath('subtitle', 'Secure your API requests');

    $revision = PageRevision::query()
        ->withoutGlobalScopes()
        ->where('page_id', $page->id)
        ->where('event', 'ai_generate')
        ->firstOrFail();

    expect($page->fresh()?->markdown)->toBe("# Authentication\n\nUse API keys in the `Authorization` header.")
        ->and($revision->markdown)->toBe('# Old draft');

    $this->actingAs($owner)
        ->post(route('pages.restore', [$project->id, $page->id, $revision->id]))
        ->assertRedirect();

    expect($page->fresh()?->markdown)->toBe('# Old draft');
});

it('rejects an empty generation description', function (): void {
    $owner = User::factory()->onboarded()->create();
    [$project, $page] = generatablePage($owner);

    $this->actingAs($owner)
        ->postJson(route('pages.ai.generate', [$project->id, $page->id]), [
            'description' => 'short',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('description');
});

it('surfaces provider errors from page generation', function (): void {
    Http::fake([
        'https://api.openai.com/v1/*' => Http::response([
            'error' => ['message' => 'Incorrect API key provided'],
        ], 401),
    ]);

    $owner = User::factory()->onboarded()->create();
    [$project, $page] = generatablePage($owner);

    $this->actingAs($owner)
        ->postJson(route('pages.ai.generate', [$project->id, $page->id]), [
            'description' => 'Explain how API key authentication works for developers.',
        ])
        ->assertStatus(502)
        ->assertJsonPath('message', '401 Unauthorized: Incorrect API key provided');
});

it('reports when ai is not configured', function (): void {
    config(['ai.api_key' => '']);

    $owner = User::factory()->onboarded()->create();
    [$project, $page] = generatablePage($owner);

    $this->actingAs($owner)
        ->postJson(route('pages.ai.generate', [$project->id, $page->id]), [
            'description' => 'Explain how API key authentication works for developers.',
        ])
        ->assertStatus(503);
});

it('blocks generation when the workspace has used its free ai allowance', function (): void {
    fakeGenerateResponse();
    config(['ai.free_monthly_limit' => 0]);

    $owner = User::factory()->onboarded()->create();
    [$project, $page] = generatablePage($owner);

    $this->actingAs($owner)
        ->postJson(route('pages.ai.generate', [$project->id, $page->id]), [
            'description' => 'Explain how API key authentication works for developers.',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('plan');
});

it('denies generating and applying to a page in another workspace', function (): void {
    fakeGenerateResponse();

    $owner = User::factory()->onboarded()->create();
    [$project, $page] = generatablePage($owner, '# Private');

    $intruder = User::factory()->onboarded()->create();

    $this->actingAs($intruder)
        ->postJson(route('pages.ai.generate', [$project->id, $page->id]), [
            'description' => 'Explain how API key authentication works for developers.',
        ])
        ->assertNotFound();

    $this->actingAs($intruder)
        ->postJson(route('pages.ai.apply-generation', [$project->id, $page->id]), [
            'markdown' => '# Hijacked',
        ])
        ->assertNotFound();

    expect($page->fresh()?->markdown)->toBe('# Private');
});

it('requires authentication to generate a page', function (): void {
    $owner = User::factory()->onboarded()->create();
    [$project, $page] = generatablePage($owner);

    $this->postJson(route('pages.ai.generate', [$project->id, $page->id]), [
        'description' => 'Explain how API key authentication works for developers.',
    ])->assertUnauthorized();
});

it('suggests title and subtitle for empty pages', function (): void {
    fakeGenerateResponse(
        markdown: "# Getting started\n\nWelcome aboard.",
        title: 'Getting started',
        subtitle: 'Your first steps',
    );

    $owner = User::factory()->onboarded()->create();
    [$project, $page] = generatablePage($owner, '', 'New page');

    $this->actingAs($owner)
        ->postJson(route('pages.ai.generate', [$project->id, $page->id]), [
            'description' => 'Write a friendly onboarding page for new users.',
            'audience' => 'end_users',
            'tone' => 'friendly',
        ])
        ->assertOk()
        ->assertJsonPath('proposal.title', 'Getting started')
        ->assertJsonPath('proposal.subtitle', 'Your first steps');
});
