<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['workspace_id', 'name', 'slug', 'markdown', 'html'])]
class Block extends Model
{
    /** @use HasFactory<\Database\Factories\BlockFactory> */
    use BelongsToWorkspace, HasFactory, SoftDeletes;
}
