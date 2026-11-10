<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoidRequest extends Model
{
    public const REASONS = [
        'wrong_item', 'customer_changed_mind', 'kitchen_mistake',
        'duplicate_order', 'payment_failed', 'other',
    ];

    protected $fillable = [
        'order_id', 'requested_by', 'approved_by', 'reason_code',
        'note', 'status', 'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
