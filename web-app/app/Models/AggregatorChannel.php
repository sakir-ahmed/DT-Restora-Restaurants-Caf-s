<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AggregatorChannel extends Model
{
    public const FOODPANDA = 'foodpanda';

    public const UBEREATS = 'ubereats';

    protected $fillable = [
        'key', 'name', 'commission_percent', 'webhook_secret', 'is_active',
        'environment', 'chain_id', 'client_id', 'client_secret', 'vendor_map',
        'last_synced_at', 'last_error',
    ];

    protected $hidden = ['client_secret'];

    protected $appends = ['has_client_secret'];

    protected $casts = [
        'commission_percent' => 'decimal:2',
        'is_active' => 'boolean',
        'client_secret' => 'encrypted',
        'vendor_map' => 'array',
        'last_synced_at' => 'datetime',
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

    public function isFoodpanda(): bool
    {
        return $this->key === self::FOODPANDA;
    }

    public function isUberEats(): bool
    {
        return $this->key === self::UBEREATS;
    }

    /**
     * Whether the platform API credentials are filled in.
     */
    public function hasApiCredentials(): bool
    {
        return ($this->isUberEats() || filled($this->chain_id)) && filled($this->client_id) && filled($this->client_secret);
    }

    /**
     * Platform vendor (outlet) id for a branch, from the vendor map.
     */
    public function vendorIdFor(int $branchId): ?string
    {
        $vendor = ($this->vendor_map ?? [])[(string) $branchId] ?? null;

        return filled($vendor) ? (string) $vendor : null;
    }

    /**
     * Branch that owns a platform vendor id (or store id).
     */
    public function branchIdForVendor(?string $vendorId): ?int
    {
        if ($vendorId === null || $vendorId === '') {
            return null;
        }

        foreach (($this->vendor_map ?? []) as $branchId => $mapped) {
            if (strcasecmp((string) $mapped, $vendorId) === 0) {
                return (int) $branchId;
            }
        }

        return null;
    }

    /** @return Attribute<bool, never> */
    protected function hasClientSecret(): Attribute
    {
        return Attribute::get(fn (): bool => filled($this->client_secret));
    }
}
