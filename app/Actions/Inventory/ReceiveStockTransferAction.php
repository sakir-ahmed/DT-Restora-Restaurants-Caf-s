<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\IngredientStock;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use LogicException;

class ReceiveStockTransferAction
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Complete an approved transfer: stock leaves the source branch and
     * arrives at the destination branch carrying its weighted-average
     * cost. The approved-status guard means stock moves exactly once.
     */
    public function execute(StockTransfer $transfer, ?User $user = null): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user): StockTransfer {
            $transfer = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== StockTransfer::STATUS_APPROVED) {
                throw new LogicException('Only an approved transfer can be received.');
            }

            $transfer->load(['items.unit']);

            foreach ($transfer->items as $item) {
                $baseQty = $item->unit->toBase((string) $item->qty);

                $sourceStock = IngredientStock::query()
                    ->where('branch_id', $transfer->from_branch_id)
                    ->where('ingredient_id', $item->ingredient_id)
                    ->lockForUpdate()
                    ->first();

                $carriedCost = (float) ($sourceStock->avg_cost ?? 0);

                $this->stock->adjust(
                    branchId: $transfer->from_branch_id,
                    ingredientId: $item->ingredient_id,
                    baseQtyDelta: -$baseQty,
                    movementType: 'transfer_out',
                    reference: $transfer,
                    unitCost: $carriedCost,
                    userId: $user?->id,
                );

                $destinationStock = $this->stock->adjust(
                    branchId: $transfer->to_branch_id,
                    ingredientId: $item->ingredient_id,
                    baseQtyDelta: $baseQty,
                    movementType: 'transfer_in',
                    reference: $transfer,
                    unitCost: $carriedCost,
                    userId: $user?->id,
                );

                // Keep the receiving branch's average cost in line with the
                // incoming quantity when the source had a meaningful cost.
                if ($carriedCost > 0) {
                    $this->stock->applyWeightedAverageCost($destinationStock, $baseQty, $carriedCost);
                }
            }

            $transfer->update([
                'status' => StockTransfer::STATUS_RECEIVED,
                'received_by' => $user?->id,
                'received_at' => now(),
            ]);

            activity()
                ->performedOn($transfer)
                ->causedBy($user)
                ->log('stock.transfer_received');

            return $transfer;
        });
    }
}
