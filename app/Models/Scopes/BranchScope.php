<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth('staff')->user() ?? auth('sanctum')->user();

        if (! $user instanceof User || $user->branch_id === null) {
            return;
        }

        $builder->where($model->qualifyColumn('branch_id'), $user->branch_id);
    }
}
