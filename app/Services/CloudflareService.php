<?php

namespace App\Services;

use App\Models\CustomDomain;
use App\Models\Project;
use App\Support\PlatformCloudflareConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudflareService
{
    private const API_BASE = 'https://api.cloudflare.com/client/v4';

    public const AUTHENTICATION_ERROR_MESSAGE = 'Cloudflare API authentication failed. Update the API token in Platform → Settings → DNS. The token needs Custom Hostnames and SSL for SaaS permissions.';

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

            // Zone read can succeed with DNS-only tokens. Custom domains need Custom Hostnames.
            $hostnames = $this->client()->get('/zones/'.PlatformCloudflareConfig::zoneId().'/custom_hostnames', [
                'per_page' => 1,
            ]);
            $this->resultFrom($hostnames);

            return [
                'ok' => true,
                'message' => 'Connected to zone '.($zone['name'] ?? PlatformCloudflareConfig::zoneId()).'. Custom Hostnames API is reachable.',
                'zone_name' => $zone['name'] ?? null,
            ];
        } catch (RequestException|ConnectionException|RuntimeException $exception) {
            return [
                'ok' => false,
                'message' => $this->userMessage($exception),
            ];
        }
    }

    public function userMessage(\Throwable $exception): string
    {
        $mapped = $this->messageFromException($exception);

        return $mapped !== '' ? $mapped : self::AUTHENTICATION_ERROR_MESSAGE;
    }

    public function isAuthenticationFailure(\Throwable $exception): bool
    {
        if ($exception instanceof RequestException && $this->responseIsAuthFailure($exception->response)) {
            return true;
        }

        $previous = $exception->getPrevious();

        if ($previous instanceof RequestException && $this->responseIsAuthFailure($previous->response)) {
            return true;
        }

        $message = $exception->getMessage();

        return $message === self::AUTHENTICATION_ERROR_MESSAGE
            || str_contains($message, 'Authentication error')
            || (str_contains($message, '"code":10000') && str_contains($message, '403'));
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

    private function client(): PendingRequest
    {
        $token = PlatformCloudflareConfig::apiToken();
        $email = PlatformCloudflareConfig::apiEmail();
        $key = PlatformCloudflareConfig::apiKey();

        $request = Http::baseUrl(self::API_BASE)
            ->acceptJson()
            ->timeout(20)
            ->throw();

        if (filled($token)) {
            // Custom Hostnames / SSL for SaaS require an API token sent as Bearer.
            return $request->withHeaders([
                'Authorization' => 'Bearer '.$token,
            ]);
        }

        if (filled($email) && filled($key)) {
            return $request->withHeaders([
                'X-Auth-Email' => $email,
                'X-Auth-Key' => $key,
            ]);
        }

        throw new RuntimeException('Cloudflare API token is not configured.');
    }

    private function messageFromException(\Throwable $exception): string
    {
        if ($this->isAuthenticationFailure($exception)) {
            return self::AUTHENTICATION_ERROR_MESSAGE;
        }

        if ($exception instanceof RequestException) {
            $fromResponse = $this->messageFromResponse($exception->response);

            if ($fromResponse !== '') {
                return $fromResponse;
            }
        }

        $previous = $exception->getPrevious();

        if ($previous instanceof RequestException) {
            $fromResponse = $this->messageFromResponse($previous->response);

            if ($fromResponse !== '') {
                return $fromResponse;
            }
        }

        $message = trim($exception->getMessage());

        if ($message === '' || $this->looksLikeRawHttpDump($message)) {
            return 'Cloudflare request failed.';
        }

        return $message;
    }

    private function messageFromResponse(?Response $response): string
    {
        if ($response === null) {
            return '';
        }

        if ($this->responseIsAuthFailure($response)) {
            return self::AUTHENTICATION_ERROR_MESSAGE;
        }

        $errors = $response->json('errors');

        if (is_array($errors) && isset($errors[0]['message'])) {
            $message = trim((string) $errors[0]['message']);

            if ($message !== '' && ! $this->looksLikeRawHttpDump($message)) {
                return $message;
            }
        }

        return '';
    }

    private function responseIsAuthFailure(?Response $response): bool
    {
        if ($response === null) {
            return false;
        }

        $status = $response->status();
        $code = (int) $response->json('errors.0.code');
        $message = strtolower((string) ($response->json('errors.0.message') ?? ''));

        if ($code === 10000) {
            return true;
        }

        if ($status === 401) {
            return true;
        }

        return $status === 403 && (str_contains($message, 'authentication') || $message === '');
    }

    private function looksLikeRawHttpDump(string $message): bool
    {
        return str_contains($message, 'HTTP request returned status code')
            || str_contains($message, '"success":false')
            || str_contains($message, '{"success"');
    }
}
