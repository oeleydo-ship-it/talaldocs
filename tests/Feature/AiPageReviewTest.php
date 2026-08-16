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
function reviewablePage(User $owner, string $markdown = "# Setup\n\nRun `npm instal`."): array
{
    $project = Project::query()
        ->withoutGlobalScopes()
        ->where('workspace_id', $owner->current_workspace_id)
        ->firstOrFail();

    $page = Page::factory()->create([
        'project_id' => $project->id,
        'workspace_id' => $project->workspace_id,
        'markdown' => $markdown,
    ]);

    return [$project, $page];
}

function fakeReviewResponse(string $markdown = "# Setup\n\nRun `npm install`."): void
{
    Http::fake([
        'https://api.openai.com/v1/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'summary' => 'One incorrect command.',
                            'notes' => ['`npm instal` is misspelled and would fail.'],
                            'markdown' => $markdown,
                        ]),
                    ],
                ],
            ],
        ]),
    ]);
}

it('proposes a corrected page without mutating it', function (): void {
    fakeReviewResponse();

    $owner = User::factory()->onboarded()->create();
    [$project, $page] = reviewablePage($owner);

    $this->actingAs($owner)
        ->postJson(route('pages.ai.review', [$project->id, $page->id]), ['intent' => 'accuracy'])
        ->assertOk()
        ->assertJsonPath('intent', 'accuracy')
        ->assertJsonPath('proposal.markdown', "# Setup\n\nRun `npm install`.")
        ->assertJsonPath('proposal.notes.0', '`npm instal` is misspelled and would fail.');

    expect($page->fresh()?->markdown)->toBe("# Setup\n\nRun `npm instal`.")
        ->and(PageRevision::query()->withoutGlobalScopes()->where('page_id', $page->id)->count())->toBe(0);
});

it('applies a correction and records a restorable revision of the previous content', function (): void {
    fakeReviewResponse();

    $owner = User::factory()->onboarded()->create();
    [$project, $page] = reviewablePage($owner);

    $this->actingAs($owner)
        ->postJson(route('pages.ai.apply', [$project->id, $page->id]), [
            'markdown' => "# Setup\n\nRun `npm install`.",
            'intent' => 'accuracy',
        ])
        ->assertOk()
        ->assertJsonPath('markdown', "# Setup\n\nRun `npm install`.");

    $revision = PageRevision::query()
        ->withoutGlobalScopes()
        ->where('page_id', $page->id)
        ->where('event', 'ai_review')
        ->firstOrFail();

    expect($page->fresh()?->markdown)->toBe("# Setup\n\nRun `npm install`.")
        ->and($revision->markdown)->toBe("# Setup\n\nRun `npm instal`.");

    $this->actingAs($owner)
        ->post(route('pages.restore', [$project->id, $page->id, $revision->id]))
        ->assertRedirect();

    expect($page->fresh()?->markdown)->toBe("# Setup\n\nRun `npm instal`.");
});

it('rejects an unknown review intent', function (): void {
    $owner = User::factory()->onboarded()->create();
    [$project, $page] = reviewablePage($owner);

    $this->actingAs($owner)
        ->postJson(route('pages.ai.review', [$project->id, $page->id]), ['intent' => 'rewrite-everything'])
        ->assertStatus(422);
});

it('surfaces provider errors from a page review', function (): void {
    Http::fake([
        'https://api.openai.com/v1/*' => Http::response([
            'error' => ['message' => 'Incorrect API key provided'],
        ], 401),
    ]);

    $owner = User::factory()->onboarded()->create();
    [$project, $page] = reviewablePage($owner);

    $this->actingAs($owner)
        ->postJson(route('pages.ai.review', [$project->id, $page->id]), ['intent' => 'accuracy'])
        ->assertStatus(502)
        ->assertJsonPath('message', '401 Unauthorized: Incorrect API key provided');
});

it('reports when ai is not configured', function (): void {
    config(['ai.api_key' => '']);

    $owner = User::factory()->onboarded()->create();
    [$project, $page] = reviewablePage($owner);

    $this->actingAs($owner)
        ->postJson(route('pages.ai.review', [$project->id, $page->id]), ['intent' => 'accuracy'])
        ->assertStatus(503);
});

it('blocks review when the workspace has used its free ai allowance', function (): void {
    fakeReviewResponse();
    config(['ai.free_monthly_limit' => 0]);

    $owner = User::factory()->onboarded()->create();
    [$project, $page] = reviewablePage($owner);

    $this->actingAs($owner)
        ->postJson(route('pages.ai.review', [$project->id, $page->id]), ['intent' => 'accuracy'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('plan');
});

it('denies reviewing and applying to a page in another workspace', function (): void {
    fakeReviewResponse();

    $owner = User::factory()->onboarded()->create();
    [$project, $page] = reviewablePage($owner);

    $intruder = User::factory()->onboarded()->create();

    $this->actingAs($intruder)
        ->postJson(route('pages.ai.review', [$project->id, $page->id]), ['intent' => 'accuracy'])
        ->assertNotFound();

    $this->actingAs($intruder)
        ->postJson(route('pages.ai.apply', [$project->id, $page->id]), ['markdown' => '# Hijacked'])
        ->assertNotFound();

    expect($page->fresh()?->markdown)->toBe("# Setup\n\nRun `npm instal`.");
});

it('requires authentication to review a page', function (): void {
    $owner = User::factory()->onboarded()->create();
    [$project, $page] = reviewablePage($owner);

    $this->postJson(route('pages.ai.review', [$project->id, $page->id]), ['intent' => 'accuracy'])
        ->assertUnauthorized();
});
