<?php

namespace App\Support;

use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PlatformAudit
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(User $admin, string $action, ?Model $subject = null, array $metadata = []): void
    {
        PlatformAuditLog::query()->create([
            'admin_id' => $admin->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
