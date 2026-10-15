<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AggregatorChannel extends Model
{
    protected $fillable = [
        'key', 'name', 'commission_percent', 'webhook_secret', 'is_active',
    ];

    protected $casts = [
        'commission_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<AggregatorPayout, $this> */
    public function payouts(): HasMany
    {
        return $this->hasMany(AggregatorPayout::class);
    }

    public function commissionFor(float $orderTotal): float
    {
        return round($orderTotal * ((float) $this->commission_percent) / 100, 2);
    }
}
