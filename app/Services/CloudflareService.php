<?php

namespace App\Services;

use App\Models\CustomDomain;
use App\Models\Project;
use App\Support\PlatformCloudflareConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
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
            $zone = $this->resultFrom($response);

            return [
                'ok' => true,
                'message' => 'Connected to zone '.($zone['name'] ?? PlatformCloudflareConfig::zoneId()).'.',
                'zone_name' => $zone['name'] ?? null,
            ];
        } catch (RequestException|ConnectionException|RuntimeException $exception) {
            return [
                'ok' => false,
                'message' => $this->messageFromException($exception),
            ];
        }
    }

    /**
     * Create or reuse the Cloudflare custom hostname, then return the latest SSL/ownership payload.
     *
     * @return array{
     *     id: string,
     *     ssl_status: string|null,
     *     status: string|null,
     *     ownership_txt_name: string|null,
     *     ownership_txt_value: string|null,
     *     ssl_txt_name: string|null,
     *     ssl_txt_value: string|null
     * }
     */
    public function ensureCustomHostname(CustomDomain $domain): array
    {
        if (filled($domain->cloudflare_hostname_id)) {
            try {
                $remote = $this->fetchCustomHostname($domain);

                if (filled($remote['id'])) {
                    return $remote;
                }
            } catch (RequestException $exception) {
                if ($exception->response?->status() !== 404) {
                    throw $exception;
                }
            }
        }

        $existing = $this->findCustomHostname($domain->hostname);

        if ($existing !== null && filled($existing['id'])) {
            return $existing;
        }

        return $this->registerCustomHostname($domain);
    }

    /**
     * @return array{
     *     id: string,
     *     ssl_status: string|null,
     *     status: string|null,
     *     ownership_txt_name: string|null,
     *     ownership_txt_value: string|null,
     *     ssl_txt_name: string|null,
     *     ssl_txt_value: string|null
     * }
     */
    public function registerCustomHostname(CustomDomain $domain): array
    {
        try {
            $response = $this->client()->post('/zones/'.PlatformCloudflareConfig::zoneId().'/custom_hostnames', [
                'hostname' => $domain->hostname,
                'ssl' => [
                    'method' => 'http',
                    'type' => 'dv',
                    'wildcard' => false,
                ],
            ]);

            return $this->mapHostnameResult($this->resultFrom($response), $domain);
        } catch (RequestException $exception) {
            if (in_array($exception->response?->status(), [409, 400], true)) {
                $existing = $this->findCustomHostname($domain->hostname);

                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $exception;
        }
    }

    /**
     * @return array{
     *     id: string,
     *     ssl_status: string|null,
     *     status: string|null,
     *     ownership_txt_name: string|null,
     *     ownership_txt_value: string|null,
     *     ssl_txt_name: string|null,
     *     ssl_txt_value: string|null
     * }
     */
    public function fetchCustomHostname(CustomDomain $domain): array
    {
        if (! filled($domain->cloudflare_hostname_id)) {
            $existing = $this->findCustomHostname($domain->hostname);

            if ($existing !== null) {
                return $existing;
            }

            return $this->mapHostnameResult([], $domain);
        }

        $response = $this->client()->get(
            '/zones/'.PlatformCloudflareConfig::zoneId().'/custom_hostnames/'.$domain->cloudflare_hostname_id,
        );

        return $this->mapHostnameResult($this->resultFrom($response), $domain);
    }

    /**
     * @return array{
     *     id: string,
     *     ssl_status: string|null,
     *     status: string|null,
     *     ownership_txt_name: string|null,
     *     ownership_txt_value: string|null,
     *     ssl_txt_name: string|null,
     *     ssl_txt_value: string|null
     * }|null
     */
    public function findCustomHostname(string $hostname): ?array
    {
        $response = $this->client()->get('/zones/'.PlatformCloudflareConfig::zoneId().'/custom_hostnames', [
            'hostname' => $hostname,
        ]);

        $result = $this->resultFrom($response);
        $first = is_array($result[0] ?? null) ? $result[0] : (is_array($result) && isset($result['id']) ? $result : null);

        if (! is_array($first) || ! filled($first['id'] ?? null)) {
            return null;
        }

        return $this->mapHostnameResult($first);
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

        $result = $this->resultFrom($response);

        return (string) ($result['id'] ?? '');
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

    /**
     * @param  array<string, mixed>  $result
     * @return array{
     *     id: string,
     *     ssl_status: string|null,
     *     status: string|null,
     *     ownership_txt_name: string|null,
     *     ownership_txt_value: string|null,
     *     ssl_txt_name: string|null,
     *     ssl_txt_value: string|null
     * }
     */
    private function mapHostnameResult(array $result, ?CustomDomain $domain = null): array
    {
        $ssl = is_array($result['ssl'] ?? null) ? $result['ssl'] : [];
        $ownership = is_array($result['ownership_verification'] ?? null) ? $result['ownership_verification'] : [];
        [$sslTxtName, $sslTxtValue] = $this->sslTxtFrom($ssl);

        $sslStatus = isset($ssl['status']) ? strtolower((string) $ssl['status']) : null;

        return [
            'id' => (string) ($result['id'] ?? $domain?->cloudflare_hostname_id ?? ''),
            'ssl_status' => $sslStatus,
            'status' => isset($result['status']) ? (string) $result['status'] : null,
            'ownership_txt_name' => filled($ownership['name'] ?? null) ? (string) $ownership['name'] : $domain?->ownership_txt_name,
            'ownership_txt_value' => filled($ownership['value'] ?? null) ? (string) $ownership['value'] : $domain?->ownership_txt_value,
            'ssl_txt_name' => $sslTxtName ?? $domain?->ssl_txt_name,
            'ssl_txt_value' => $sslTxtValue ?? $domain?->ssl_txt_value,
        ];
    }

    /**
     * @param  array<string, mixed>  $ssl
     * @return array{0: string|null, 1: string|null}
     */
    private function sslTxtFrom(array $ssl): array
    {
        $name = isset($ssl['txt_name']) ? (string) $ssl['txt_name'] : null;
        $value = isset($ssl['txt_value']) ? (string) $ssl['txt_value'] : null;

        $records = $ssl['validation_records'] ?? [];

        if (is_array($records)) {
            foreach ($records as $record) {
                if (! is_array($record)) {
                    continue;
                }

                $recordName = $record['txt_name'] ?? null;
                $recordValue = $record['txt_value'] ?? null;

                if (filled($recordName) && filled($recordValue)) {
                    return [(string) $recordName, (string) $recordValue];
                }
            }
        }

        return [$name, $value];
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    private function resultFrom(Response $response): array
    {
        if ($response->json('success') === false) {
            throw new RuntimeException($this->messageFromResponse($response));
        }

        $result = $response->json('result');

        return is_array($result) ? $result : [];
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
            ->timeout(20)
            ->throw();
    }

    private function messageFromException(RequestException|ConnectionException|RuntimeException $exception): string
    {
        if ($exception instanceof RequestException) {
            return $this->messageFromResponse($exception->response) ?: $exception->getMessage();
        }

        return $exception->getMessage();
    }

    private function messageFromResponse(?Response $response): string
    {
        $errors = $response?->json('errors');

        if (is_array($errors) && isset($errors[0]['message'])) {
            return (string) $errors[0]['message'];
        }

        return (string) ($response?->json('errors.0.message') ?: '');
    }
}
