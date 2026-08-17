<?php

namespace App\Models;

use App\Enums\DomainStatus;
use App\Models\Concerns\BelongsToWorkspace;
use App\Support\PlatformCloudflareConfig;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $project_id
 * @property string $hostname
 * @property string $verification_token
 * @property DomainStatus $status
 * @property bool $is_primary
 * @property Carbon|null $verified_at
 * @property string|null $cloudflare_hostname_id
 * @property string|null $ssl_status
 * @property string|null $ownership_txt_name
 * @property string|null $ownership_txt_value
 * @property string|null $ssl_txt_name
 * @property string|null $ssl_txt_value
 */
#[Fillable([
    'workspace_id',
    'project_id',
    'hostname',
    'verification_token',
    'status',
    'is_primary',
    'verified_at',
    'last_checked_at',
    'error_message',
    'cloudflare_hostname_id',
    'ssl_status',
    'ownership_txt_name',
    'ownership_txt_value',
    'ssl_txt_name',
    'ssl_txt_value',
    'cloudflare_dns_record_id',
])]
class CustomDomain extends Model
{
    /** @use HasFactory<\Database\Factories\CustomDomainFactory> */
    use BelongsToWorkspace, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DomainStatus::class,
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function txtName(): string
    {
        return '_anytdocs-challenge.'.$this->hostname;
    }

    public function txtValue(): string
    {
        return 'anytdocs-verify='.$this->verification_token;
    }

    public function cnameTarget(): string
    {
        return PlatformCloudflareConfig::publicCnameTarget();
    }

    public function tenantCnameTarget(): string
    {
        $project = $this->project;
        $platform = strtolower((string) config('anytdocs.domain'));

        return $project?->subdomain
            ? strtolower($project->subdomain.'.'.$platform)
            : $platform;
    }

    /**
     * @return list<string>
     */
    public function acceptedCnameTargets(): array
    {
        return [strtolower($this->cnameTarget())];
    }

    public function usesCloudflare(): bool
    {
        return filled($this->cloudflare_hostname_id);
    }

    public function sslReady(): bool
    {
        if (! filled($this->cloudflare_hostname_id)) {
            return false;
        }

        return strtolower((string) $this->ssl_status) === 'active';
    }

    /**
     * @param  array{
     *     id?: string|null,
     *     ssl_status?: string|null,
     *     ownership_txt_name?: string|null,
     *     ownership_txt_value?: string|null,
     *     ssl_txt_name?: string|null,
     *     ssl_txt_value?: string|null
     * }  $remote
     */
    public function applyCloudflareHostname(array $remote): void
    {
        $sslStatus = array_key_exists('ssl_status', $remote)
            ? (filled($remote['ssl_status'] ?? null) ? strtolower((string) $remote['ssl_status']) : null)
            : $this->ssl_status;

        $hostnameId = filled($remote['id'] ?? null)
            ? (string) $remote['id']
            : $this->cloudflare_hostname_id;

        $this->forceFill([
            'cloudflare_hostname_id' => filled($hostnameId) ? (string) $hostnameId : $this->cloudflare_hostname_id,
            'ssl_status' => filled($hostnameId) ? $sslStatus : null,
            'ownership_txt_name' => $remote['ownership_txt_name'] ?? $this->ownership_txt_name,
            'ownership_txt_value' => $remote['ownership_txt_value'] ?? $this->ownership_txt_value,
            'ssl_txt_name' => $remote['ssl_txt_name'] ?? $this->ssl_txt_name,
            'ssl_txt_value' => $remote['ssl_txt_value'] ?? $this->ssl_txt_value,
        ])->save();
    }
}
