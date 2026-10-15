<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    use BelongsToBranch, HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'branch_id', 'supplier_id', 'invoice_no', 'purchased_at',
        'total', 'status', 'received_at', 'note', 'user_id',
    ];

    protected $casts = [
        'purchased_at' => 'date',
        'total' => 'decimal:2',
        'received_at' => 'datetime',
    ];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<PurchaseItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }
}
