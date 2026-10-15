<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'name_bn', 'unit_id', 'reorder_level', 'is_active',
    ];

    protected $casts = [
        'reorder_level' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return HasMany<IngredientStock, $this> */
    public function stocks(): HasMany
    {
        return $this->hasMany(IngredientStock::class);
    }

    public function stockFor(int $branchId): IngredientStock
    {
        return $this->stocks->firstWhere('branch_id', $branchId)
            ?? new IngredientStock(['branch_id' => $branchId, 'ingredient_id' => $this->id]);
    }

    /**
     * Eager load the branch stock row alongside the ingredient.
     */
    public function scopeWithStockFor(Builder $builder, int $branchId): Builder
    {
        return $builder->with(['stocks' => fn ($query) => $query->where('branch_id', $branchId)]);
    }

    public function scopeActive(Builder $builder): Builder
    {
        return $builder->where('is_active', true);
    }

    public function scopeSearch(Builder $builder, ?string $term): Builder
    {
        if ($term === null || $term === '') {
            return $builder;
        }

        return $builder->where(function (Builder $query) use ($term): void {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('name_bn', 'like', "%{$term}%");
        });
    }

    public function displayName(string $locale): string
    {
        return $locale === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }
}
