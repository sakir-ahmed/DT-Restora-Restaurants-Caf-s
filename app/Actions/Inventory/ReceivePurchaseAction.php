<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use LogicException;

class ReceivePurchaseAction
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Receive a purchase: stock in per line, weighted-average cost
     * refresh, movement ledger entries, status change. Idempotency is
     * guaranteed by the draft/received status guard.
     */
    public function execute(Purchase $purchase, ?User $user = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $user): Purchase {
            $purchase = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($purchase->isReceived()) {
                return $purchase;
            }

            if ($purchase->status === Purchase::STATUS_CANCELLED) {
                throw new LogicException('A cancelled purchase cannot be received.');
            }

            $purchase->load(['items.ingredient', 'items.unit']);

            foreach ($purchase->items as $item) {
                /** @var PurchaseItem $item */
                $baseQty = $item->unit->toBase((string) $item->qty);
                $baseUnitCost = $item->baseUnitCost();

                $stock = $this->stock->adjust(
                    branchId: $purchase->branch_id,
                    ingredientId: $item->ingredient_id,
                    baseQtyDelta: $baseQty,
                    movementType: 'purchase',
                    reference: $purchase,
                    unitCost: $baseUnitCost,
                    userId: $user?->id,
                );

                $this->stock->applyWeightedAverageCost($stock, $baseQty, $baseUnitCost);
            }

            $purchase->update([
                'status' => Purchase::STATUS_RECEIVED,
                'received_at' => now(),
                'total' => $purchase->items->sum(fn (PurchaseItem $item) => (float) $item->line_total),
            ]);

            activity()
                ->performedOn($purchase)
                ->causedBy($user)
                ->log('purchase.received');

            return $purchase;
        });
    }
}
