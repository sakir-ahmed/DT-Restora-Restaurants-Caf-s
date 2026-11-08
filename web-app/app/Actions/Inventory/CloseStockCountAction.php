<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use LogicException;

class CloseStockCountAction
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Close a stock count and apply every counted variance to stock.
     * Items without a counted quantity are skipped.
     */
    public function execute(StockCount $count, ?User $user = null): StockCount
    {
        return DB::transaction(function () use ($count, $user): StockCount {
            $count = StockCount::query()->whereKey($count->id)->lockForUpdate()->firstOrFail();

            if (! $count->isOpen()) {
                throw new LogicException('Only an open stock count can be closed.');
            }

            $count->load(['items.ingredient']);

            foreach ($count->items as $item) {
                /** @var StockCountItem $item */
                if ($item->counted_qty === null) {
                    continue;
                }

                $variance = round((float) $item->counted_qty - (float) $item->system_qty, 3);

                $item->update(['variance_qty' => $variance]);

                if ($variance === 0.0) {
                    continue;
                }

                $this->stock->adjust(
                    branchId: $count->branch_id,
                    ingredientId: $item->ingredient_id,
                    baseQtyDelta: $variance,
                    movementType: 'count',
                    reference: $count,
                    userId: $user?->id,
                );
            }

            $count->update([
                'status' => StockCount::STATUS_CLOSED,
                'closed_at' => now(),
            ]);

            activity()
                ->performedOn($count)
                ->causedBy($user)
                ->log('stock.count_closed');

            return $count;
        });
    }
}
