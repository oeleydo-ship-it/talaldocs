<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Placeholder model for future workspace API access.
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property string $key_prefix
 * @property string $key_hash
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 */
#[Fillable(['workspace_id', 'name', 'key_prefix', 'key_hash', 'last_used_at', 'expires_at'])]
class ApiKey extends Model
{
    use BelongsToWorkspace;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public static function generatePlainTextKey(): string
    {
        return 'atk_'.Str::lower(Str::random(40));
    }

    public function matches(string $plainKey): bool
    {
        return hash_equals($this->key_hash, hash('sha256', $plainKey));
    }
}
