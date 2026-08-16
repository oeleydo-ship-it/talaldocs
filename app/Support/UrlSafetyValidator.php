<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class UrlSafetyValidator
{
    /**
     * @var list<string>
     */
    private const BLOCKED_HOSTS = [
        'localhost',
        '127.0.0.1',
        '0.0.0.0',
        '::1',
        'metadata.google.internal',
        'metadata.google',
    ];

    public function assertSafe(string $url): void
    {
        $parsed = parse_url($url);

        if (! is_array($parsed)) {
            throw ValidationException::withMessages([
                'url' => 'The website URL is not valid.',
            ]);
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw ValidationException::withMessages([
                'url' => 'Only http and https URLs are allowed.',
            ]);
        }

        $host = strtolower((string) ($parsed['host'] ?? ''));

        if ($host === '') {
            throw ValidationException::withMessages([
                'url' => 'The website URL must include a host name.',
            ]);
        }

        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            throw ValidationException::withMessages([
                'url' => 'That URL is not allowed.',
            ]);
        }

        if (str_contains($host, 'localhost') || str_ends_with($host, '.local')) {
            throw ValidationException::withMessages([
                'url' => 'Local or private URLs are not allowed.',
            ]);
        }

        $this->assertResolvableHostIsPublic($host);
    }

    private function assertResolvableHostIsPublic(string $host): void
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertPublicIp($host);

            return;
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];

        if ($records === []) {
            $fallback = gethostbyname($host);

            if ($fallback === $host) {
                throw ValidationException::withMessages([
                    'url' => 'The website host could not be resolved.',
                ]);
            }

            $this->assertPublicIp($fallback);

            return;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($ip)) {
                $this->assertPublicIp($ip);
            }
        }
    }

    private function assertPublicIp(string $ip): void
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw ValidationException::withMessages([
                'url' => 'Private or reserved network addresses are not allowed.',
            ]);
        }
    }
}
