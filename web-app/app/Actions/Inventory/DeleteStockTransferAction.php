<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\Concerns\ReversesSourceJournal;
use App\Exceptions\InsufficientStockException;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

class DeleteStockTransferAction
{
    use ReversesSourceJournal;

    public function __construct(private readonly StockService $stock) {}

    /**
     * Delete a transfer made by mistake. A requested or approved transfer moved nothing, so it is
     * simply marked deleted. A received one is undone first: the quantity leaves the destination
     * branch (with its carried cost) and goes back to the source, all in one transaction. It is
     * refused when the destination already used the goods.
     *
     * @throws InsufficientStockException
     */
    public function execute(StockTransfer $transfer, ?User $user = null, ?string $reason = null, ?string $note = null): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user, $reason, $note): StockTransfer {
            $transfer = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($transfer->status === StockTransfer::STATUS_RECEIVED) {
                $transfer->load('items.unit');

                foreach ($transfer->items as $item) {
                    $baseQty = $item->unit->toBase((string) $item->qty);

                    $carriedCost = (float) (StockMovement::query()
                        ->where('reference_type', $transfer->getMorphClass())
                        ->where('reference_id', $transfer->id)
                        ->where('ingredient_id', $item->ingredient_id)
                        ->where('type', 'transfer_out')
                        ->value('unit_cost') ?? 0);

                    $destination = $this->stock->adjust(
                        branchId: $transfer->to_branch_id,
                        ingredientId: $item->ingredient_id,
                        baseQtyDelta: -$baseQty,
                        movementType: 'transfer_in',
                        reference: $transfer,
                        unitCost: $carriedCost,
                        userId: $user?->id,
                    );

                    if ($carriedCost > 0) {
                        $this->stock->removeWeightedAverageCost($destination, $baseQty, $carriedCost);
                    }

                    $this->stock->adjust(
                        branchId: $transfer->from_branch_id,
                        ingredientId: $item->ingredient_id,
                        baseQtyDelta: $baseQty,
                        movementType: 'transfer_out',
                        reference: $transfer,
                        unitCost: $carriedCost,
                        userId: $user?->id,
                    );
                }
            }

            $this->markDeleted($transfer, $user, $reason, $note);

            activity()->performedOn($transfer)->causedBy($user)->withProperties(['reason' => $reason, 'note' => $note])->log('stock.transfer_deleted');

            return $transfer;
        });
    }
}
