<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @use HasMany<User, $this> */
class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'name_bn', 'code', 'phone', 'address', 'city',
        'latitude', 'longitude', 'vat_percent', 'vat_inclusive',
        'service_charge_percent', 'allow_negative_stock',
        'online_ordering_enabled', 'opening_hours', 'receipt_footer',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:2',
        'longitude' => 'decimal:2',
        'vat_percent' => 'decimal:2',
        'vat_inclusive' => 'boolean',
        'service_charge_percent' => 'decimal:2',
        'allow_negative_stock' => 'boolean',
        'online_ordering_enabled' => 'boolean',
        'opening_hours' => 'array',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<MenuCategory, $this> */
    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class);
    }

    /** @return HasMany<MenuItem, $this> */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /** @return HasMany<DeliveryZone, $this> */
    public function zones(): HasMany
    {
        return $this->hasMany(DeliveryZone::class);
    }

    public function displayName(string $locale): string
    {
        return $locale === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }
}
