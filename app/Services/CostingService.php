<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\IngredientStock;
use App\Models\MenuItem;
use App\Models\Recipe;

/**
 * Costing engine: recipe cost from the branch's weighted-average
 * ingredient costs, and the food cost percentage against the item price.
 */
class CostingService
{
    /**
     * @return array{cost: string, lines: array<int, array{ingredient_id: int, name: string, qty: string, unit_symbol: string, unit_cost: string, line_cost: string}>, food_cost_percent: ?string}
     */
    public function recipeCost(MenuItem $item, int $branchId): array
    {
        $recipe = Recipe::query()
            ->where('menu_item_id', $item->id)
            ->with(['items.ingredient.unit'])
            ->first();

        if ($recipe === null) {
            return ['cost' => '0.00', 'lines' => [], 'food_cost_percent' => null];
        }

        $stocks = IngredientStock::query()
            ->where('branch_id', $branchId)
            ->whereIn('ingredient_id', $recipe->items->pluck('ingredient_id'))
            ->get()
            ->keyBy('ingredient_id');

        $total = 0.0;
        $lines = [];

        foreach ($recipe->items as $line) {
            $ingredient = $line->ingredient;
            $avgCost = (float) ($stocks[$line->ingredient_id]->avg_cost ?? 0);
            $lineCost = round((float) $line->qty * $avgCost, 2);
            $total += $lineCost;

            $lines[] = [
                'ingredient_id' => $ingredient->id,
                'name' => $ingredient->name,
                'qty' => (string) $line->qty,
                'unit_symbol' => $ingredient->unit->symbol,
                'unit_cost' => number_format($avgCost, 4, '.', ''),
                'line_cost' => number_format($lineCost, 2, '.', ''),
            ];
        }

        $price = (float) ($item->variants->min('price') ?? $item->base_price);

        $foodCostPercent = $price > 0
            ? number_format(round($total / $price * 100, 2), 2, '.', '')
            : null;

        return [
            'cost' => number_format(round($total, 2), 2, '.', ''),
            'lines' => $lines,
            'food_cost_percent' => $foodCostPercent,
        ];
    }
}
