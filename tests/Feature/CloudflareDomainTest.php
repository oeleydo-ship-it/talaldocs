<?php

namespace Tests\Feature;

use App\Enums\DomainStatus;
use App\Jobs\VerifyCustomDomainJob;
use App\Models\CustomDomain;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
        Http::fake([
            'api.cloudflare.com/client/v4/zones/zone_test/custom_hostnames' => Http::response([
                'success' => true,
                'result' => [
                    'id' => 'cf-hostname-1',
                    'ssl' => ['status' => 'pending_validation'],
                    'ownership_verification' => [
                        'name' => '_cf-custom-hostname.docs.customer.example',
                        'value' => 'ownership-token',
                    ],
                ],
            ], 200),
        ]);

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
    }
}
