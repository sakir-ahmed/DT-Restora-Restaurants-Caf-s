<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltySetting extends Model
{
    protected $fillable = ['earn_rate', 'point_value', 'expiry_days', 'is_enabled'];

    protected $casts = [
        'earn_rate' => 'integer',
        'point_value' => 'decimal:2',
        'expiry_days' => 'integer',
        'is_enabled' => 'boolean',
    ];

    /**
     * The singleton settings row, created with defaults on first use.
     */
    public static function current(): self
    {
        return self::query()->first() ?? self::query()->create([
            'earn_rate' => 1,
            'point_value' => '1.00',
            'expiry_days' => 365,
            'is_enabled' => true,
        ]);
    }
}
