<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Records that are deleted by mistake-correction instead of being destroyed. The row stays for the
 * audit trail; every default query skips it. A list page calls visible() to follow the system
 * setting "show deleted items": on keeps deleted rows in the list (shown as deleted), off hides them.
 *
 * @mixin Model
 */
trait SafeDeletes
{
    use SoftDeletes;

    public function initializeSafeDeletes(): void
    {
        $this->mergeFillable(['deleted_by', 'delete_reason', 'delete_note']);
    }

    /**
     * @param  Builder<static>  $builder
     * @return Builder<static>
     */
    public function scopeVisible(Builder $builder): Builder
    {
        return Setting::showsDeletedItems() ? $builder->withTrashed() : $builder;
    }

    /** @return BelongsTo<User, $this> */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
