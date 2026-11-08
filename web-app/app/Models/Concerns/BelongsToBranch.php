<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Apply to every branch-owned model: users without a branch assignment
 * (owner, super-admin, accountant) see all branches; everyone else is
 * globally scoped to their own branch.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);
    }

    public function scopeForBranch(Builder $builder, int $branchId): Builder
    {
        return $builder->where($this->qualifyColumn('branch_id'), $branchId);
    }

    public function scopeWithoutBranchScope(Builder $builder): Builder
    {
        return $builder->withoutGlobalScope(BranchScope::class);
    }
}
