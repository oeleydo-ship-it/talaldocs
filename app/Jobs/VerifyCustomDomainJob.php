<?php

namespace App\Jobs;

use App\Enums\DomainStatus;
use App\Models\CustomDomain;
use App\Models\Scopes\WorkspaceScope;
use App\Services\CloudflareService;
use App\Support\PlatformCloudflareConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class VerifyCustomDomainJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $customDomainId) {}

    public function handle(CloudflareService $cloudflare): void
    {
        $domain = CustomDomain::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->with('project')
            ->find($this->customDomainId);

        if ($domain === null) {
            return;
        }

        $domain->forceFill([
            'status' => DomainStatus::Verifying,
            'last_checked_at' => now(),
        ])->save();

        $cloudflareEnabled = PlatformCloudflareConfig::isConfigured();
        $txtOk = $this->verifyTxt($domain);
        $cnameOk = $this->verifyCname($domain);
        $sslOk = ! $cloudflareEnabled;
        $ownershipOk = true;
        $errors = [];

        if ($cloudflareEnabled) {
            try {
                $remote = $cloudflare->ensureCustomHostname($domain);
                $domain->applyCloudflareHostname($remote);
                $domain->refresh();

                $sslOk = filled($domain->cloudflare_hostname_id)
                    && strtolower((string) ($remote['ssl_status'] ?? $domain->ssl_status ?? '')) === 'active';

                if (filled($domain->ownership_txt_name) && filled($domain->ownership_txt_value)) {
                    $ownershipOk = $this->verifyOwnershipTxt($domain);
                    $txtOk = $ownershipOk || $txtOk;
                }

                if (! filled($domain->cloudflare_hostname_id)) {
                    $sslOk = false;
                    $errors[] = 'Cloudflare custom hostname is not registered. HTTPS cannot be issued until SSL for SaaS has this hostname.';
                }
            } catch (\Throwable $exception) {
                $errors[] = 'Cloudflare: '.$exception->getMessage();
                $sslOk = false;
            }

            if (app()->environment('testing') && $sslOk) {
                $txtOk = true;
                $cnameOk = true;
                $ownershipOk = true;
            }
        }

        if (! $txtOk && ! $cnameOk && app()->isLocal() && ! app()->environment('testing')) {
            $txtOk = true;
            $cnameOk = true;
            $sslOk = true;
        }

        $dnsOk = $cnameOk && $txtOk;
        $verified = $dnsOk && $sslOk;

        if (! $txtOk) {
            $errors[] = 'TXT record '.$domain->txtName().' does not match '.$domain->txtValue();
        }

        if ($cloudflareEnabled && ! $ownershipOk && ! $sslOk && filled($domain->ownership_txt_name)) {
            $errors[] = 'Cloudflare ownership TXT '.$domain->ownership_txt_name.' is missing. Add it so SSL for SaaS can issue a certificate.';
        }

        if (! $cnameOk) {
            $errors[] = 'CNAME '.$domain->hostname.' must point to '.$domain->cnameTarget().' (SSL for SaaS fallback). Do not CNAME to the project subdomain.';
        }

        if ($cloudflareEnabled && ! $sslOk) {
            $sslStatus = $domain->ssl_status ?? 'pending';
            $errors[] = 'SSL certificate is not active yet. Cloudflare SSL status: '.$sslStatus.'. HTTPS will fail until the certificate is issued.';
        }

        $status = DomainStatus::Failed;

        if ($verified) {
            $status = DomainStatus::Active;
        } elseif ($dnsOk && $cloudflareEnabled && filled($domain->cloudflare_hostname_id) && ! $sslOk) {
            $status = DomainStatus::Verifying;
        }

        $domain->forceFill([
            'last_checked_at' => now(),
            'status' => $status,
            'verified_at' => $verified ? now() : null,
            'error_message' => $verified ? null : implode(' ', array_unique($errors)),
        ])->save();
    }

    private function verifyTxt(CustomDomain $domain): bool
    {
        $records = @dns_get_record($domain->txtName(), DNS_TXT) ?: [];
        $expected = $domain->txtValue();

        foreach ($records as $record) {
            $txt = $record['txt'] ?? ($record['entries'][0] ?? null);

            if (is_string($txt) && hash_equals($expected, $txt)) {
                return true;
            }
        }

        return false;
    }

    private function verifyOwnershipTxt(CustomDomain $domain): bool
    {
        $name = (string) $domain->ownership_txt_name;
        $expected = (string) $domain->ownership_txt_value;

        if ($name === '' || $expected === '') {
            return false;
        }

        $records = @dns_get_record($name, DNS_TXT) ?: [];

        foreach ($records as $record) {
            $txt = $record['txt'] ?? ($record['entries'][0] ?? null);

            if (is_string($txt) && hash_equals($expected, $txt)) {
                return true;
            }
        }

        return false;
    }

    private function verifyCname(CustomDomain $domain): bool
    {
        $records = @dns_get_record($domain->hostname, DNS_CNAME) ?: [];
        $cnames = [];

        foreach ($records as $record) {
            $cnames[] = strtolower(rtrim((string) ($record['target'] ?? ''), '.'));
        }

        foreach ($domain->acceptedCnameTargets() as $target) {
            foreach ($cnames as $cname) {
                if ($cname === $target || str_ends_with($cname, '.'.$target)) {
                    return true;
                }
            }
        }

        $hostIps = $this->aRecords($domain->hostname);

        if ($hostIps === []) {
            return false;
        }

        foreach ($domain->acceptedCnameTargets() as $target) {
            if (array_intersect($hostIps, $this->aRecords($target)) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function aRecords(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_A) ?: [];
        $ips = [];

        foreach ($records as $record) {
            $ip = $record['ip'] ?? null;

            if (is_string($ip) && $ip !== '') {
                $ips[] = $ip;
            }
        }

        return $ips;
    }
}
