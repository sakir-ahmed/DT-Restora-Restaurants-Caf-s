<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\Concerns\ReversesSourceJournal;
use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\IngredientStock;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

class DeletePurchaseAction
{
    use ReversesSourceJournal;

    public function __construct(private readonly StockService $stock) {}

    /**
     * Delete a purchase entered by mistake. A draft posted nothing, so it is simply marked deleted.
     * A received purchase is undone first, in one transaction: its stock and average cost come back
     * out and its journal is reversed. It is refused when the goods were already used, so stock can
     * never go below zero. The row is kept, marked deleted, for the audit trail.
     *
     * @throws InsufficientStockException
     */
    public function execute(Purchase $purchase, ?User $user = null, ?string $reason = null, ?string $note = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $user, $reason, $note): Purchase {
            $purchase = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($purchase->isReceived()) {
                $purchase->load('items.unit');

                $this->assertStockCanBeTakenOut($purchase);

                foreach ($purchase->items as $item) {
                    /** @var PurchaseItem $item */
                    $baseQty = $item->unit->toBase((string) $item->qty);
                    $baseCost = $item->baseUnitCost();

                    $stock = $this->stock->adjust(
                        branchId: $purchase->branch_id,
                        ingredientId: $item->ingredient_id,
                        baseQtyDelta: -$baseQty,
                        movementType: 'purchase',
                        reference: $purchase,
                        unitCost: $baseCost,
                        userId: $user?->id,
                        allowNegative: true,
                    );

                    $this->stock->removeWeightedAverageCost($stock, $baseQty, $baseCost);
                }

                $this->reverseJournalsFor($purchase, $user, __('inventory.purchase_delete_journal_note', ['id' => $purchase->id]));
            }

            $this->markDeleted($purchase, $user, $reason, $note);

            activity()->performedOn($purchase)->causedBy($user)->withProperties(['reason' => $reason, 'note' => $note])->log('purchase.deleted');

            return $purchase;
        });
    }

    private function assertStockCanBeTakenOut(Purchase $purchase): void
    {
        if (Branch::query()->whereKey($purchase->branch_id)->value('allow_negative_stock')) {
            return;
        }

        $needed = [];

        foreach ($purchase->items as $item) {
            $needed[$item->ingredient_id] = ($needed[$item->ingredient_id] ?? 0.0) + $item->unit->toBase((string) $item->qty);
        }

        foreach ($needed as $ingredientId => $baseQty) {
            $available = (float) (IngredientStock::query()
                ->where('branch_id', $purchase->branch_id)
                ->where('ingredient_id', $ingredientId)
                ->lockForUpdate()
                ->value('stock_qty') ?? 0);

            if (round($available - $baseQty, 3) < 0) {
                throw new InsufficientStockException((int) $ingredientId, $available, -$baseQty);
            }
        }
    }
}
