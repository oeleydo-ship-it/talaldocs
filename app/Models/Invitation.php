<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $email
 * @property WorkspaceRole $role
 * @property string $token
 * @property Carbon|null $accepted_at
 * @property Carbon|null $expires_at
 */
#[Fillable(['workspace_id', 'email', 'role', 'token', 'invited_by', 'accepted_at', 'expires_at'])]
class Invitation extends Model
{
    use BelongsToWorkspace;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isOpen(): bool
    {
        return $this->accepted_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
