<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'discount_limit_percent',
        'wastage_spike_multiplier',
        'pos_pin_minutes',
        'pos_lock_enabled',
        'delivery_otp_enabled',
        'tax_registration_no',
        'social_facebook',
        'social_instagram',
        'social_youtube',
        'social_tiktok',
        'social_whatsapp',
        'established_year',
        'website_enabled',
        'online_delivery_enabled',
    ];

    protected $casts = [
        'discount_limit_percent' => 'decimal:2',
        'wastage_spike_multiplier' => 'decimal:2',
        'pos_pin_minutes' => 'integer',
        'pos_lock_enabled' => 'boolean',
        'delivery_otp_enabled' => 'boolean',
        'established_year' => 'integer',
        'website_enabled' => 'boolean',
        'online_delivery_enabled' => 'boolean',
    ];

    /**
     * The singleton operational settings row, created with defaults on first use.
     */
    public static function current(): self
    {
        return self::query()->first() ?? self::query()->create([
            'discount_limit_percent' => '10.00',
            'wastage_spike_multiplier' => '1.50',
            'pos_pin_minutes' => 5,
            'pos_lock_enabled' => false,
            'delivery_otp_enabled' => true,
        ]);
    }
}
