<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PENDING = 'pending';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_PENDING_VERIFICATION = 'pending_verification';

    use HasFactory;

    protected $fillable = [
        'order_id', 'method', 'amount', 'tendered', 'change_due',
        'reference', 'status', 'user_id',
        'gateway', 'gateway_payment_id', 'gateway_trx_id',
        'idempotency_key', 'verified_at', 'is_advance',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tendered' => 'decimal:2',
        'change_due' => 'decimal:2',
        'verified_at' => 'datetime',
        'is_advance' => 'boolean',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
