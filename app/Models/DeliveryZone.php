<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryZone extends Model
{
    protected $fillable = [
        'branch_id', 'name', 'fee', 'min_order', 'eta_minutes', 'is_active',
    ];

    protected $casts = [
        'fee' => 'decimal:2',
        'min_order' => 'decimal:2',
        'eta_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
