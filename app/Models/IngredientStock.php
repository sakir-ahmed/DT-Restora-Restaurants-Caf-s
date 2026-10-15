<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-branch stock level and weighted-average cost of an ingredient.
 * All mutations run inside transactions with lockForUpdate.
 */
class IngredientStock extends Model
{
    protected $fillable = [
        'branch_id', 'ingredient_id', 'stock_qty', 'avg_cost', 'last_cost',
    ];

    protected $casts = [
        'stock_qty' => 'decimal:3',
        'avg_cost' => 'decimal:4',
        'last_cost' => 'decimal:4',
    ];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Ingredient, $this> */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function isBelowReorderLevel(): bool
    {
        $reorderLevel = (float) ($this->ingredient->reorder_level ?? 0);

        return $reorderLevel > 0 && (float) $this->stock_qty <= $reorderLevel;
    }
}
