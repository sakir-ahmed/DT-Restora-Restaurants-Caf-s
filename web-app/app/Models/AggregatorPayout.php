<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AggregatorPayout extends Model
{
    protected $fillable = [
        'aggregator_channel_id', 'period_from', 'period_to',
        'expected_amount', 'received_amount', 'status',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'expected_amount' => 'decimal:2',
        'received_amount' => 'decimal:2',
    ];

    /** @return BelongsTo<AggregatorChannel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(AggregatorChannel::class, 'aggregator_channel_id');
    }
}
