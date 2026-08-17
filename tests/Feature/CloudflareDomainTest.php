<?php

namespace Tests\Feature;

use App\Enums\DomainStatus;
use App\Jobs\VerifyCustomDomainJob;
use App\Models\CustomDomain;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Project;
use App\Models\User;
use App\Support\PlatformCloudflareConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CloudflareDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_save_cloudflare_settings(): void
    {
        $admin = User::factory()->onboarded()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->post(route('platform.settings.cloudflare.update'), [
                'cloudflare_enabled' => true,
                'cloudflare_zone_id' => 'zone_test_123',
                'cloudflare_account_id' => 'acct_test',
                'cloudflare_fallback_origin' => 'fallback.anytdocs.test',
                'cloudflare_auto_subdomains' => true,
                'cloudflare_api_token' => 'cf_token_test',
            ])
            ->assertRedirect();

        $settings = PlatformSetting::instance()->refresh();
        $this->assertTrue($settings->cloudflare_enabled);
        $this->assertSame('zone_test_123', $settings->cloudflare_zone_id);
        $this->assertSame('fallback.anytdocs.test', $settings->cloudflare_fallback_origin);
        $this->assertTrue($settings->cloudflare_auto_subdomains);
        $this->assertSame('cf_token_test', $settings->cloudflare_api_token);
    }

    public function test_adding_domain_registers_cloudflare_hostname_when_configured(): void
    {
        Queue::fake();
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), 'custom_hostnames')) {
                return Http::response(['success' => true, 'result' => []], 200);
            }

            return Http::response([
                'success' => true,
                'result' => [
                    'id' => 'cf-hostname-1',
                    'ssl' => ['status' => 'pending_validation'],
                    'ownership_verification' => [
                        'name' => '_cf-custom-hostname.docs.customer.example',
                        'value' => 'ownership-token',
                    ],
                ],
            ], 200);
        });

        PlatformSetting::instance()->forceFill([
            'cloudflare_enabled' => true,
            'cloudflare_zone_id' => 'zone_test',
            'cloudflare_api_token' => 'cf_token_test',
            'cloudflare_fallback_origin' => 'fallback.anytdocs.test',
        ])->save();

        $owner = User::factory()->onboarded()->create();
        $workspace = $owner->currentWorkspace;
        $workspace->forceFill(['plan_id' => Plan::query()->where('slug', 'pro')->value('id')])->save();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $workspace->id)->firstOrFail();

        $this->actingAs($owner)
            ->post(route('projects.domains.store', $project->id), [
                'hostname' => 'docs.customer.example',
            ])
            ->assertRedirect();

        $domain = CustomDomain::query()->where('hostname', 'docs.customer.example')->first();
        $this->assertNotNull($domain);
        $this->assertSame('cf-hostname-1', $domain->cloudflare_hostname_id);
        $this->assertSame('ownership-token', $domain->ownership_txt_value);
    }

    public function test_verify_job_activates_when_cloudflare_ssl_is_active(): void
    {
        PlatformSetting::instance()->forceFill([
            'cloudflare_enabled' => true,
            'cloudflare_zone_id' => 'zone_test',
            'cloudflare_api_token' => 'cf_token_test',
            'cloudflare_fallback_origin' => 'fallback.anytdocs.test',
        ])->save();

        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $domain = CustomDomain::query()->create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'hostname' => 'docs.example.test',
            'verification_token' => 'abc123',
            'status' => DomainStatus::Pending,
            'cloudflare_hostname_id' => 'cf-hostname-1',
            'ownership_txt_name' => '_cf-custom-hostname.docs.example.test',
            'ownership_txt_value' => 'ownership-token',
        ]);

        Http::fake([
            'api.cloudflare.com/client/v4/zones/zone_test/custom_hostnames/cf-hostname-1' => Http::response([
                'success' => true,
                'result' => [
                    'id' => 'cf-hostname-1',
                    'status' => 'active',
                    'ssl' => ['status' => 'active'],
                    'ownership_verification' => [
                        'name' => '_cf-custom-hostname.docs.example.test',
                        'value' => 'ownership-token',
                    ],
                ],
            ], 200),
        ]);

        (new VerifyCustomDomainJob($domain->id))->handle(app(\App\Services\CloudflareService::class));

        $domain->refresh();
        $this->assertSame(DomainStatus::Active, $domain->status);
        $this->assertSame('active', $domain->ssl_status);
        $this->assertTrue($domain->sslReady());
    }

    public function test_verify_job_does_not_activate_when_cloudflare_ssl_is_pending(): void
    {
        PlatformSetting::instance()->forceFill([
            'cloudflare_enabled' => true,
            'cloudflare_zone_id' => 'zone_test',
            'cloudflare_api_token' => 'cf_token_test',
            'cloudflare_fallback_origin' => 'fallback.anytdocs.test',
        ])->save();

        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $domain = CustomDomain::query()->create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'hostname' => 'docs.example.test',
            'verification_token' => 'abc123',
            'status' => DomainStatus::Pending,
            'cloudflare_hostname_id' => 'cf-hostname-1',
            'ssl_status' => 'pending_validation',
            'ownership_txt_name' => '_cf-custom-hostname.docs.example.test',
            'ownership_txt_value' => 'ownership-token',
        ]);

        Http::fake([
            'api.cloudflare.com/client/v4/zones/zone_test/custom_hostnames/cf-hostname-1' => Http::response([
                'success' => true,
                'result' => [
                    'id' => 'cf-hostname-1',
                    'status' => 'active',
                    'ssl' => ['status' => 'pending_validation'],
                    'ownership_verification' => [
                        'name' => '_cf-custom-hostname.docs.example.test',
                        'value' => 'ownership-token',
                    ],
                ],
            ], 200),
        ]);

        (new VerifyCustomDomainJob($domain->id))->handle(app(\App\Services\CloudflareService::class));

        $domain->refresh();
        $this->assertNotSame(DomainStatus::Active, $domain->status);
        $this->assertSame('pending_validation', $domain->ssl_status);
        $this->assertFalse($domain->sslReady());
        $this->assertStringContainsString('SSL certificate is not active yet', (string) $domain->error_message);
    }

    public function test_cname_target_uses_fallback_origin_even_without_api_token(): void
    {
        PlatformSetting::instance()->forceFill([
            'cloudflare_enabled' => true,
            'cloudflare_zone_id' => null,
            'cloudflare_api_token' => null,
            'cloudflare_fallback_origin' => 'fallback.talaldocs.com',
        ])->save();

        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();
        $project->forceFill(['subdomain' => 'acmetest'])->save();

        $domain = CustomDomain::query()->create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'hostname' => 'docs.uplary.com',
            'verification_token' => 'tokentoken',
            'status' => DomainStatus::Pending,
        ]);
        $domain->setRelation('project', $project);

        $this->assertSame('fallback.talaldocs.com', $domain->cnameTarget());
        $this->assertSame('acmetest.'.strtolower((string) config('anytdocs.domain')), $domain->tenantCnameTarget());
        $this->assertFalse($domain->sslReady());
    }

    public function test_ssl_ready_is_false_without_cloudflare_hostname_even_if_status_looks_active(): void
    {
        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $domain = CustomDomain::query()->create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'hostname' => 'docs.example.test',
            'verification_token' => 'abc123',
            'status' => DomainStatus::Active,
            'is_primary' => true,
            'ssl_status' => 'active',
        ]);

        $this->assertNull($domain->cloudflare_hostname_id);
        $this->assertFalse($domain->sslReady());

        $domain->forceFill(['cloudflare_hostname_id' => 'cf-hostname-1', 'ssl_status' => 'pending_validation'])->save();
        $this->assertFalse($domain->refresh()->sslReady());

        $domain->forceFill(['ssl_status' => 'active'])->save();
        $this->assertTrue($domain->refresh()->sslReady());
    }

    public function test_settings_page_does_not_mark_ssl_ready_without_cloudflare_ssl_active(): void
    {
        PlatformSetting::instance()->forceFill([
            'cloudflare_enabled' => true,
            'cloudflare_zone_id' => 'zone_test',
            'cloudflare_api_token' => 'cf_token_test',
            'cloudflare_fallback_origin' => 'fallback.talaldocs.com',
        ])->save();

        $owner = User::factory()->onboarded()->create();
        $workspace = $owner->currentWorkspace;
        $workspace->forceFill(['plan_id' => Plan::query()->where('slug', 'pro')->value('id')])->save();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $workspace->id)->firstOrFail();

        CustomDomain::query()->create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'hostname' => 'docs.uplary.com',
            'verification_token' => 'tokentoken',
            'status' => DomainStatus::Active,
            'is_primary' => true,
            'ssl_status' => 'active',
        ]);

        $this->actingAs($owner)
            ->get(route('projects.settings', $project->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/settings')
                ->where('domains.0.hostname', 'docs.uplary.com')
                ->where('domains.0.status', 'active')
                ->where('domains.0.ssl_ready', false)
            );
    }

    public function test_verify_job_registers_cloudflare_hostname_when_missing(): void
    {
        PlatformSetting::instance()->forceFill([
            'cloudflare_enabled' => true,
            'cloudflare_zone_id' => 'zone_test',
            'cloudflare_api_token' => 'cf_token_test',
            'cloudflare_fallback_origin' => 'fallback.anytdocs.test',
        ])->save();

        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();

        $domain = CustomDomain::query()->create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'hostname' => 'docs.example.test',
            'verification_token' => 'abc123',
            'status' => DomainStatus::Pending,
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), 'custom_hostnames')) {
                return Http::response(['success' => true, 'result' => []], 200);
            }

            if ($request->method() === 'POST' && str_contains($request->url(), 'custom_hostnames')) {
                return Http::response([
                    'success' => true,
                    'result' => [
                        'id' => 'cf-hostname-created',
                        'status' => 'pending',
                        'ssl' => [
                            'method' => 'http',
                            'type' => 'dv',
                            'status' => 'pending_validation',
                        ],
                        'ownership_verification' => [
                            'name' => '_cf-custom-hostname.docs.example.test',
                            'value' => 'cf-ownership-token',
                        ],
                    ],
                ], 200);
            }

            return Http::response(['success' => true, 'result' => []], 200);
        });

        (new VerifyCustomDomainJob($domain->id))->handle(app(\App\Services\CloudflareService::class));

        $domain->refresh();
        $this->assertSame('cf-hostname-created', $domain->cloudflare_hostname_id);
        $this->assertSame('pending_validation', $domain->ssl_status);
        $this->assertSame('_cf-custom-hostname.docs.example.test', $domain->ownership_txt_name);
        $this->assertSame('cf-ownership-token', $domain->ownership_txt_value);
        $this->assertFalse($domain->sslReady());
        $this->assertNotSame(DomainStatus::Active, $domain->status);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $data = $request->data();

            return $request->method() === 'POST'
                && str_contains($request->url(), '/zones/zone_test/custom_hostnames')
                && ($data['hostname'] ?? null) === 'docs.example.test'
                && data_get($data, 'ssl.method') === 'http'
                && data_get($data, 'ssl.type') === 'dv';
        });
    }

    public function test_cname_target_uses_config_fallback_when_cloudflare_is_not_configured_locally(): void
    {
        PlatformSetting::instance()->forceFill([
            'cloudflare_enabled' => false,
            'cloudflare_zone_id' => null,
            'cloudflare_api_token' => null,
            'cloudflare_fallback_origin' => null,
        ])->save();

        config([
            'anytdocs.domain' => 'talaldocs.com',
            'anytdocs.cname_target' => 'fallback.talaldocs.com',
            'anytdocs.cloudflare.enabled' => false,
            'anytdocs.cloudflare.fallback_origin' => 'fallback.talaldocs.com',
        ]);

        $owner = User::factory()->onboarded()->create();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $owner->current_workspace_id)->firstOrFail();
        $project->forceFill(['subdomain' => 'acmetest'])->save();

        $domain = CustomDomain::query()->create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'hostname' => 'docs.uplary.com',
            'verification_token' => 'tokentoken',
            'status' => DomainStatus::Pending,
        ]);
        $domain->setRelation('project', $project);

        $this->assertSame('fallback.talaldocs.com', PlatformCloudflareConfig::publicCnameTarget());
        $this->assertSame('fallback.talaldocs.com', $domain->cnameTarget());
        $this->assertSame('acmetest.talaldocs.com', $domain->tenantCnameTarget());
        $this->assertNotSame($domain->tenantCnameTarget(), $domain->cnameTarget());
    }

    public function test_settings_page_exposes_fallback_origin_as_cname_target_not_tenant_hostname(): void
    {
        PlatformSetting::instance()->forceFill([
            'cloudflare_enabled' => false,
            'cloudflare_zone_id' => null,
            'cloudflare_api_token' => null,
            'cloudflare_fallback_origin' => 'fallback.talaldocs.com',
        ])->save();

        config(['anytdocs.domain' => 'talaldocs.com']);

        $owner = User::factory()->onboarded()->create();
        $workspace = $owner->currentWorkspace;
        $workspace->forceFill(['plan_id' => Plan::query()->where('slug', 'pro')->value('id')])->save();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $workspace->id)->firstOrFail();
        $project->forceFill(['subdomain' => 'acmetest'])->save();

        CustomDomain::query()->create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'hostname' => 'docs.uplary.com',
            'verification_token' => 'tokentoken',
            'status' => DomainStatus::Pending,
        ]);

        $this->actingAs($owner)
            ->get(route('projects.settings', $project->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/settings')
                ->where('cname_target', 'fallback.talaldocs.com')
                ->where('domains.0.hostname', 'docs.uplary.com')
                ->where('domains.0.cname_target', 'fallback.talaldocs.com')
                ->where('domains.0.tenant_cname_target', 'acmetest.talaldocs.com')
            );
    }

    public function test_adding_domain_payload_uses_fallback_not_tenant_subdomain(): void
    {
        Queue::fake();

        PlatformSetting::instance()->forceFill([
            'cloudflare_enabled' => true,
            'cloudflare_zone_id' => null,
            'cloudflare_api_token' => null,
            'cloudflare_fallback_origin' => 'fallback.talaldocs.com',
        ])->save();

        config(['anytdocs.domain' => 'talaldocs.com']);

        $owner = User::factory()->onboarded()->create();
        $workspace = $owner->currentWorkspace;
        $workspace->forceFill(['plan_id' => Plan::query()->where('slug', 'pro')->value('id')])->save();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $workspace->id)->firstOrFail();
        $project->forceFill(['subdomain' => 'acmetest'])->save();

        $this->actingAs($owner)
            ->from(route('projects.settings', $project->id))
            ->post(route('projects.domains.store', $project->id), [
                'hostname' => 'docs.uplary.com',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->get(route('projects.settings', $project->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('domains.0.hostname', 'docs.uplary.com')
                ->where('domains.0.cname_target', 'fallback.talaldocs.com')
                ->where('domains.0.tenant_cname_target', 'acmetest.talaldocs.com')
            );
    }

    public function test_settings_page_derives_fallback_cname_when_origin_is_not_stored(): void
    {
        PlatformSetting::instance()->forceFill([
            'app_domain' => 'talaldocs.com',
            'cloudflare_enabled' => false,
            'cloudflare_zone_id' => null,
            'cloudflare_api_token' => null,
            'cloudflare_fallback_origin' => null,
        ])->save();

        config([
            'anytdocs.domain' => 'talaldocs.com',
            'anytdocs.cname_target' => null,
            'anytdocs.cloudflare.enabled' => false,
            'anytdocs.cloudflare.fallback_origin' => null,
        ]);

        $owner = User::factory()->onboarded()->create();
        $workspace = $owner->currentWorkspace;
        $workspace->forceFill(['plan_id' => Plan::query()->where('slug', 'pro')->value('id')])->save();
        $project = Project::query()->withoutGlobalScopes()->where('workspace_id', $workspace->id)->firstOrFail();
        $project->forceFill(['subdomain' => 'acmetest'])->save();

        CustomDomain::query()->create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'hostname' => 'docs.jrwebpro.com',
            'verification_token' => 'tokentoken',
            'status' => DomainStatus::Pending,
        ]);

        $this->assertSame('fallback.talaldocs.com', PlatformCloudflareConfig::publicCnameTarget());

        $this->actingAs($owner)
            ->get(route('projects.settings', $project->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/settings')
                ->where('cname_target', 'fallback.talaldocs.com')
                ->where('domains.0.hostname', 'docs.jrwebpro.com')
                ->where('domains.0.cname_target', 'fallback.talaldocs.com')
                ->where('domains.0.tenant_cname_target', 'acmetest.talaldocs.com')
            );
    }
}
