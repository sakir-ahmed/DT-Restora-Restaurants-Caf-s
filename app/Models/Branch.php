<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

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

    /** @return MorphMany<Media, $this> */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Readable URL segment for public location pages (falls back to the branch code).
     */
    public function publicSlug(): string
    {
        $slug = Str::slug($this->name);

        return $slug !== '' ? $slug : strtolower($this->code);
    }

    /**
     * Resolve an active branch from its public slug or, for older links, its code.
     */
    public static function findByPublicSlug(string $slug): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->get()
            ->first(fn (self $branch): bool => $branch->publicSlug() === $slug || strtolower($branch->code) === $slug);
    }
}
