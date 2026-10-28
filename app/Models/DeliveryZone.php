<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\FlushesPublicCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryZone extends Model
{
    use FlushesPublicCache;

    /** The fee is the same for every address in the area. */
    public const FEE_FIXED = 'fixed';

    /** The fee is a starting amount; the restaurant confirms the final charge by phone. */
    public const FEE_FLEXIBLE = 'flexible';

    protected $fillable = [
        'branch_id', 'name', 'name_bn', 'fee', 'fee_type', 'min_order', 'eta_minutes', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'fee' => 'decimal:2',
        'min_order' => 'decimal:2',
        'eta_minutes' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
