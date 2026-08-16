<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class WorkspaceScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('current.workspace_id')) {
            return;
        }

        $workspaceId = app('current.workspace_id');

        if ($workspaceId === null) {
            return;
        }

        $builder->where($model->qualifyColumn('workspace_id'), $workspaceId);
    }
}
