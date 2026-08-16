<?php

namespace App\Services;

use App\Models\CustomDomain;
use App\Models\Project;
use App\Support\PlatformCloudflareConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudflareService
{
    private const API_BASE = 'https://api.cloudflare.com/client/v4';

    public function isConfigured(): bool
    {
        return PlatformCloudflareConfig::isConfigured();
    }

    /**
     * @return array{ok: bool, message: string, zone_name?: string}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Cloudflare is not fully configured.'];
        }

        try {
            $response = $this->client()->get('/zones/'.PlatformCloudflareConfig::zoneId());
            $zone = $response->json('result');

            return [
                'ok' => true,
                'message' => 'Connected to zone '.($zone['name'] ?? PlatformCloudflareConfig::zoneId()).'.',
                'zone_name' => $zone['name'] ?? null,
            ];
        } catch (RequestException|ConnectionException $exception) {
            return [
                'ok' => false,
                'message' => $this->messageFromException($exception),
            ];
        }
    }

    /**
     * @return array{
     *     id: string,
     *     ssl_status: string|null,
     *     ownership_txt_name: string|null,
     *     ownership_txt_value: string|null
     * }
     */
    public function registerCustomHostname(CustomDomain $domain): array
    {
        $response = $this->client()->post('/zones/'.PlatformCloudflareConfig::zoneId().'/custom_hostnames', [
            'hostname' => $domain->hostname,
            'ssl' => [
                'method' => 'txt',
                'type' => 'dv',
                'wildcard' => false,
            ],
        ]);

        $result = $response->json('result') ?? [];

        return [
            'id' => (string) ($result['id'] ?? ''),
            'ssl_status' => $result['ssl']['status'] ?? null,
            'ownership_txt_name' => $result['ownership_verification']['name'] ?? null,
            'ownership_txt_value' => $result['ownership_verification']['value'] ?? null,
        ];
    }

    /**
     * @return array{ssl_status: string|null, status: string|null, ownership_txt_name: string|null, ownership_txt_value: string|null}
     */
    public function fetchCustomHostname(CustomDomain $domain): array
    {
        if (! filled($domain->cloudflare_hostname_id)) {
            return [
                'ssl_status' => $domain->ssl_status,
                'status' => null,
                'ownership_txt_name' => $domain->ownership_txt_name,
                'ownership_txt_value' => $domain->ownership_txt_value,
            ];
        }

        $response = $this->client()->get(
            '/zones/'.PlatformCloudflareConfig::zoneId().'/custom_hostnames/'.$domain->cloudflare_hostname_id,
        );

        $result = $response->json('result') ?? [];

        return [
            'ssl_status' => $result['ssl']['status'] ?? null,
            'status' => $result['status'] ?? null,
            'ownership_txt_name' => $result['ownership_verification']['name'] ?? $domain->ownership_txt_name,
            'ownership_txt_value' => $result['ownership_verification']['value'] ?? $domain->ownership_txt_value,
        ];
    }

    public function deleteCustomHostname(CustomDomain $domain): void
    {
        if (! filled($domain->cloudflare_hostname_id)) {
            return;
        }

        try {
            $this->client()->delete(
                '/zones/'.PlatformCloudflareConfig::zoneId().'/custom_hostnames/'.$domain->cloudflare_hostname_id,
            );
        } catch (RequestException $exception) {
            if ($exception->response?->status() !== 404) {
                throw $exception;
            }
        }
    }

    public function provisionTenantSubdomain(Project $project): ?string
    {
        if (! PlatformCloudflareConfig::autoProvisionSubdomains()) {
            return null;
        }

        $subdomain = strtolower((string) $project->subdomain);
        $fallback = PlatformCloudflareConfig::fallbackOrigin();

        if ($subdomain === '' || $fallback === '') {
            return null;
        }

        $response = $this->client()->post('/zones/'.PlatformCloudflareConfig::zoneId().'/dns_records', [
            'type' => 'CNAME',
            'name' => $subdomain,
            'content' => $fallback,
            'proxied' => true,
            'comment' => config('app.name', 'Docs').' tenant '.$project->id,
        ]);

        return (string) ($response->json('result.id') ?? '');
    }

    public function deleteDnsRecord(?string $recordId): void
    {
        if (! filled($recordId)) {
            return;
        }

        try {
            $this->client()->delete(
                '/zones/'.PlatformCloudflareConfig::zoneId().'/dns_records/'.$recordId,
            );
        } catch (RequestException $exception) {
            if ($exception->response?->status() !== 404) {
                throw $exception;
            }
        }
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        $token = PlatformCloudflareConfig::apiToken();

        if (! filled($token)) {
            throw new RuntimeException('Cloudflare API token is not configured.');
        }

        return Http::baseUrl(self::API_BASE)
            ->acceptJson()
            ->withToken($token)
            ->timeout(20);
    }

    private function messageFromException(RequestException|ConnectionException $exception): string
    {
        if ($exception instanceof RequestException) {
            $errors = $exception->response?->json('errors');

            if (is_array($errors) && isset($errors[0]['message'])) {
                return (string) $errors[0]['message'];
            }
        }

        return $exception->getMessage();
    }
}
