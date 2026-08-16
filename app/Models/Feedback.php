<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'project_id', 'page_id', 'helpful', 'comment'])]
class Feedback extends Model
{
    use BelongsToWorkspace;

    protected $table = 'feedback';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'helpful' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
