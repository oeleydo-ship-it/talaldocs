<?php

namespace Tests\Feature;

use App\Enums\DomainStatus;
use App\Models\CustomDomain;
use App\Models\Language;
use App\Models\Page;
use App\Models\Project;
use App\Models\ProjectLanguage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomHostRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://anytdocs.test',
            'anytdocs.domain' => 'anytdocs.test',
            'anytdocs.cname_target' => 'fallback.anytdocs.test',
            'anytdocs.cloudflare.fallback_origin' => 'fallback.anytdocs.test',
        ]);
    }

    public function test_verified_custom_host_root_serves_project_docs(): void
    {
        [$project, $page] = $this->publishedProject('supportdocs', 'Uplary welcome');
        $this->attachDomain($project, 'docs.uplary.test', DomainStatus::Active);

        $this->get('https://docs.uplary.test/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docs/show')
                ->where('page.slug', 'welcome')
                ->where('page.title', 'Uplary welcome')
                ->where('directoryUrl', '/directory')
                ->where('docsHomeUrl', '/latest/en/welcome')
            );

        $this->get('https://docs.uplary.test/latest/en/welcome')
            ->assertOk()
            ->assertInertia(fn (Assert $assert) => $assert
                ->component('docs/show')
                ->where('page.slug', $page->slug)
            );

        $this->get('https://docs.uplary.test/directory')
            ->assertOk()
            ->assertInertia(fn (Assert $assert) => $assert->component('docs/directory'));

        $this->get('https://docs.uplary.test/docs/supportdocs/latest/en/welcome')
            ->assertOk()
            ->assertInertia(fn (Assert $assert) => $assert->component('docs/show'));
    }

    public function test_app_domain_root_serves_marketing(): void
    {
        [$project] = $this->publishedProject('supportdocs', 'Uplary welcome');
        $this->attachDomain($project, 'docs.uplary.test', DomainStatus::Active);

        $this->get('https://anytdocs.test/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('marketing/home'));

        $this->get('https://anytdocs.test/docs/supportdocs/latest/en/welcome')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docs/show')
                ->where('page.title', 'Uplary welcome')
            );
    }

    public function test_unverified_custom_host_does_not_serve_docs(): void
    {
        [$project] = $this->publishedProject('supportdocs', 'Uplary welcome');
        $this->attachDomain($project, 'docs.uplary.test', DomainStatus::Pending);

        $this->get('https://docs.uplary.test/')
            ->assertStatus(503)
            ->assertSee('Custom domain is pending')
            ->assertDontSee('Uplary welcome', false);

        $this->get('https://docs.uplary.test/docs/supportdocs/latest/en/welcome')
            ->assertStatus(503)
            ->assertSee('Custom domain is pending');
    }

    public function test_failed_custom_host_does_not_serve_docs(): void
    {
        [$project] = $this->publishedProject('supportdocs', 'Uplary welcome');
        $this->attachDomain($project, 'docs.uplary.test', DomainStatus::Failed);

        $this->get('https://docs.uplary.test/')
            ->assertNotFound()
            ->assertSee('Custom domain is not verified');
    }

    public function test_tenant_subdomain_root_serves_project_docs(): void
    {
        [$project] = $this->publishedProject('supportdocs', 'Uplary welcome');

        $this->get('https://supportdocs.anytdocs.test/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docs/show')
                ->where('page.title', 'Uplary welcome')
                ->where('directoryUrl', '/docs/supportdocs/directory')
            );
    }

    public function test_fallback_origin_uses_forwarded_custom_host(): void
    {
        [$project] = $this->publishedProject('supportdocs', 'Uplary welcome');
        $this->attachDomain($project, 'docs.uplary.test', DomainStatus::Active);

        $this->get('https://fallback.anytdocs.test/', [
            'X-Forwarded-Host' => 'docs.uplary.test',
        ])->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docs/show')
                ->where('page.title', 'Uplary welcome')
            );
    }

    public function test_fallback_origin_without_forwarded_host_does_not_take_over_root(): void
    {
        [$project] = $this->publishedProject('supportdocs', 'Uplary welcome');
        $this->attachDomain($project, 'docs.uplary.test', DomainStatus::Active);

        $this->get('https://fallback.anytdocs.test/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('marketing/home'));
    }

    /**
     * @return array{0: Project, 1: Page}
     */
    private function publishedProject(string $subdomain, string $title): array
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();
        $project->forceFill(['subdomain' => $subdomain])->save();

        $language = Language::query()->firstOrCreate(
            ['code' => 'en'],
            ['name' => 'English', 'is_default' => true],
        );

        ProjectLanguage::query()->firstOrCreate(
            [
                'workspace_id' => $project->workspace_id,
                'project_id' => $project->id,
                'language_id' => $language->id,
            ],
            ['is_default' => true],
        );

        $page = Page::factory()->published()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'title' => $title,
            'slug' => 'welcome',
            'published_html' => '<h1>'.$title.'</h1>',
        ]);

        return [$project->refresh(), $page];
    }

    private function attachDomain(Project $project, string $hostname, DomainStatus $status): CustomDomain
    {
        return CustomDomain::query()->create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'hostname' => $hostname,
            'verification_token' => 'test-token',
            'status' => $status,
            'is_primary' => $status === DomainStatus::Active,
            'verified_at' => $status === DomainStatus::Active ? now() : null,
        ]);
    }
}
