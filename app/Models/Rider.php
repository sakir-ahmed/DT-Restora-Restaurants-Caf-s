<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rider extends Model
{
    protected $fillable = [
        'user_id', 'vehicle', 'plate', 'is_on_duty', 'is_active',
    ];

    protected $casts = [
        'is_on_duty' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Delivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function latestLocation(): ?RiderLocation
    {
        return $this->locations()->latest('recorded_at')->first();
    }

    /** @return HasMany<RiderLocation, $this> */
    public function locations(): HasMany
    {
        return $this->hasMany(RiderLocation::class);
    }
}
