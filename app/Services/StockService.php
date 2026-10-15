<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\IngredientStock;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The single place where stock quantities change. Every mutation locks
 * the stock row, enforces the negative-stock rule and writes a movement
 * ledger entry with the resulting balance.
 */
class StockService
{
    public function adjust(
        int $branchId,
        int $ingredientId,
        float $baseQtyDelta,
        string $movementType,
        ?Model $reference = null,
        ?float $unitCost = null,
        ?int $userId = null,
        bool $allowNegative = false,
    ): IngredientStock {
        return DB::transaction(function () use ($branchId, $ingredientId, $baseQtyDelta, $movementType, $reference, $unitCost, $userId, $allowNegative): IngredientStock {
            $stock = IngredientStock::query()
                ->where('branch_id', $branchId)
                ->where('ingredient_id', $ingredientId)
                ->lockForUpdate()
                ->first();

            if ($stock === null) {
                $stock = IngredientStock::query()->create([
                    'branch_id' => $branchId,
                    'ingredient_id' => $ingredientId,
                    'stock_qty' => 0,
                    'avg_cost' => 0,
                    'last_cost' => 0,
                ]);

                $stock = IngredientStock::query()
                    ->whereKey($stock->id)
                    ->lockForUpdate()
                    ->first();
            }

            if (! $allowNegative && $baseQtyDelta < 0) {
                $branchAllowsNegative = Branch::query()
                    ->whereKey($branchId)
                    ->value('allow_negative_stock') ?? false;

                $newQty = round((float) $stock->stock_qty + $baseQtyDelta, 3);

                if ($newQty < 0 && ! $branchAllowsNegative) {
                    throw new InsufficientStockException($ingredientId, (float) $stock->stock_qty, $baseQtyDelta);
                }
            }

            $stock->stock_qty = round((float) $stock->stock_qty + $baseQtyDelta, 3);
            $stock->save();

            StockMovement::query()->create([
                'branch_id' => $branchId,
                'ingredient_id' => $ingredientId,
                'qty' => $baseQtyDelta,
                'type' => $movementType,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'unit_cost' => $unitCost,
                'balance_after' => $stock->stock_qty,
                'user_id' => $userId,
            ]);

            return $stock;
        });
    }

    /**
     * Weighted-average cost update after receiving stock. Must be called
     * inside the same transaction as the receiving adjustment.
     */
    public function applyWeightedAverageCost(IngredientStock $stock, float $incomingBaseQty, float $incomingBaseUnitCost): void
    {
        $locked = IngredientStock::query()->whereKey($stock->id)->lockForUpdate()->first();

        if ($locked === null) {
            throw new LogicException('Stock row disappeared during cost update.');
        }

        $oldQty = (float) $locked->stock_qty - $incomingBaseQty;
        $oldAvg = (float) $locked->avg_cost;

        $totalQty = $oldQty + $incomingBaseQty;

        $newAvg = $totalQty <= 0
            ? $incomingBaseUnitCost
            : round((($oldQty * $oldAvg) + ($incomingBaseQty * $incomingBaseUnitCost)) / $totalQty, 4);

        $locked->avg_cost = max(0, $newAvg);
        $locked->last_cost = $incomingBaseUnitCost;
        $locked->save();
    }
}
