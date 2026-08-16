<?php

namespace Tests\Feature;

use App\Enums\DomainStatus;
use App\Enums\ProjectVisibility;
use App\Jobs\VerifyCustomDomainJob;
use App\Models\CustomDomain;
use App\Models\Page;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Support\StripeWebhookHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MilestoneNextTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_webhook_updates_workspace_plan_when_signature_valid(): void
    {
        $owner = User::factory()->onboarded()->create();
        $workspace = Workspace::query()->findOrFail($owner->current_workspace_id);
        $pro = Plan::query()->where('slug', 'pro')->firstOrFail();

        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'customer' => 'cus_test_123',
                    'metadata' => [
                        'workspace_id' => (string) $workspace->id,
                        'plan_id' => (string) $pro->id,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $secret = 'whsec_test_secret';
        config(['services.stripe.webhook_secret' => $secret]);

        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $header = 't='.$timestamp.',v1='.$signature;

        $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            [
                'HTTP_Stripe-Signature' => $header,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload,
        )->assertOk();

        $workspace->refresh();
        $this->assertSame($pro->id, $workspace->plan_id);
        $this->assertSame('cus_test_123', $workspace->stripe_id);
    }

    public function test_stripe_webhook_rejects_invalid_signature(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $this->postJson(route('stripe.webhook'), ['type' => 'ping'], [
            'Stripe-Signature' => 't=1,v1=invalid',
        ])->assertStatus(400);
    }

    public function test_custom_domain_verify_job_transitions_through_verifying(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $domain = CustomDomain::query()->create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'hostname' => 'docs.example.test',
            'verification_token' => 'abc123',
            'status' => DomainStatus::Pending,
        ]);

        (new VerifyCustomDomainJob($domain->id))->handle(app(\App\Services\CloudflareService::class));

        $domain->refresh();
        $this->assertContains($domain->status, [DomainStatus::Failed, DomainStatus::Active]);
        $this->assertNotNull($domain->last_checked_at);
    }

    public function test_adding_custom_domain_dispatches_verification_job(): void
    {
        Queue::fake();

        $owner = User::factory()->onboarded()->create();
        $workspace = Workspace::query()->findOrFail($owner->current_workspace_id);
        $workspace->forceFill(['plan_id' => Plan::query()->where('slug', 'pro')->value('id')])->save();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $workspace->id)->firstOrFail();

        $this->actingAs($owner)
            ->post(route('projects.domains.store', $project->id), [
                'hostname' => 'docs.customer.example',
            ])
            ->assertRedirect();

        Queue::assertPushed(VerifyCustomDomainJob::class);
        $this->assertDatabaseHas('custom_domains', [
            'hostname' => 'docs.customer.example',
            'status' => DomainStatus::Pending->value,
        ]);
    }

    public function test_page_archive_and_restore(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();
        $page = Page::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
        ]);

        $this->actingAs($owner)
            ->delete(route('pages.destroy', [$project->id, $page->id]))
            ->assertRedirect();

        $this->assertSoftDeleted('pages', ['id' => $page->id]);

        $this->actingAs($owner)
            ->post(route('pages.archive-restore', [$project->id, $page->id]))
            ->assertRedirect(route('projects.editor', [
                'project' => $project->id,
                'version' => $page->documentation_version_id,
                'language' => $page->language_id,
                'page' => $page->id,
            ]));

        $this->assertDatabaseHas('pages', ['id' => $page->id, 'deleted_at' => null]);
    }

    public function test_password_protected_docs_require_unlock(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();
        $page = Page::factory()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'slug' => 'secret-guide',
        ]);

        $project->forceFill([
            'visibility' => ProjectVisibility::Password,
            'password' => 'docs-secret',
        ])->save();

        $this->get($project->docsBasePath().'/latest/en/'.$page->slug)
            ->assertOk()
            ->assertInertia(fn ($assert) => $assert->component('docs/password'));

        $this->post(route('docs.unlock', $project->subdomain), ['password' => 'docs-secret'])
            ->assertRedirect();

        $this->get($project->docsBasePath().'/latest/en/'.$page->slug)
            ->assertOk()
            ->assertInertia(fn ($assert) => $assert->component('docs/show'));
    }

    public function test_analytics_export_returns_csv(): void
    {
        $owner = User::factory()->onboarded()->create();

        $this->actingAs($owner)
            ->get(route('analytics.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_api_key_middleware_rejects_missing_key(): void
    {
        Route::middleware(\App\Http\Middleware\AuthenticateApiKey::class)->get('/testing/api-probe', fn () => response()->json(['ok' => true]));

        $this->getJson('/testing/api-probe')->assertUnauthorized();
    }

    public function test_stripe_handler_downgrades_on_subscription_deleted(): void
    {
        $owner = User::factory()->onboarded()->create();
        $workspace = Workspace::query()->findOrFail($owner->current_workspace_id);
        $pro = Plan::query()->where('slug', 'pro')->firstOrFail();
        $free = Plan::query()->where('slug', 'free')->firstOrFail();

        $workspace->forceFill(['plan_id' => $pro->id, 'stripe_id' => 'cus_downgrade'])->save();

        app(StripeWebhookHandler::class)->handle([
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'customer' => 'cus_downgrade',
                ],
            ],
        ]);

        $this->assertSame($free->id, $workspace->fresh()->plan_id);
    }
}
