<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(int $workspaceId, string $action, ?User $user = null, ?Model $subject = null, array $metadata = []): void
    {
        AuditLog::query()->create([
            'workspace_id' => $workspaceId,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject === null ? null : $subject::class,
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
