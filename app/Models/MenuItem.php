<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MenuItem extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id', 'menu_category_id', 'name', 'name_bn',
        'description', 'description_bn', 'photo_path', 'barcode',
        'base_price', 'is_available', 'is_vegetarian', 'sort_order',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'is_available' => 'boolean',
        'is_vegetarian' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<MenuCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    /** @return HasMany<MenuItemVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(MenuItemVariant::class)->orderBy('sort_order');
    }

    /** @return HasMany<MenuItemAddOn, $this> */
    public function addOns(): HasMany
    {
        return $this->hasMany(MenuItemAddOn::class)->orderBy('sort_order');
    }

    /** @return HasOne<Recipe, $this> */
    public function recipe()
    {
        return $this->hasOne(Recipe::class);
    }

    public function scopeAvailable(Builder $builder): Builder
    {
        return $builder->where('is_available', true);
    }

    public function scopeSearch(Builder $builder, ?string $term): Builder
    {
        if ($term === null || $term === '') {
            return $builder;
        }

        return $builder->where(function (Builder $query) use ($term): void {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('name_bn', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%");
        });
    }

    public function displayName(string $locale): string
    {
        return $locale === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }

    /**
     * Price of a variant, or the base price when the item has no variants.
     */
    public function priceFor(?MenuItemVariant $variant = null): string
    {
        if ($variant !== null && $variant->menu_item_id === $this->id) {
            return (string) $variant->price;
        }

        return (string) $this->base_price;
    }
}
