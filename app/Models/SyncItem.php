<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncItem extends Model
{
    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_CONFLICT = 'conflict';

    public const TYPES = ['order', 'order_edit', 'order_status', 'payment', 'shift', 'wastage', 'stock_count'];

    protected $fillable = [
        'client_id', 'type', 'payload', 'data_version', 'status',
        'conflict_reason', 'user_id', 'accepted_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'data_version' => 'integer',
        'accepted_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
