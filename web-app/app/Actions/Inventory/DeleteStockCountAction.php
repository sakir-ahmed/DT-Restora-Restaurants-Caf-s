<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\Concerns\ReversesSourceJournal;
use App\Exceptions\InsufficientStockException;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

class DeleteStockCountAction
{
    use ReversesSourceJournal;

    public function __construct(private readonly StockService $stock) {}

    /**
     * Delete a stock count made by mistake. An open count changed nothing, so it is simply marked
     * deleted. A closed count first takes its variances back out of stock, in one transaction; it is
     * refused when stock has since fallen too low to take them out.
     *
     * @throws InsufficientStockException
     */
    public function execute(StockCount $count, ?User $user = null, ?string $reason = null, ?string $note = null): StockCount
    {
        return DB::transaction(function () use ($count, $user, $reason, $note): StockCount {
            $count = StockCount::query()->whereKey($count->id)->lockForUpdate()->firstOrFail();

            if ($count->status === StockCount::STATUS_CLOSED) {
                $count->load('items');

                foreach ($count->items as $item) {
                    /** @var StockCountItem $item */
                    $variance = (float) $item->variance_qty;

                    if ($variance === 0.0) {
                        continue;
                    }

                    $this->stock->adjust(
                        branchId: $count->branch_id,
                        ingredientId: $item->ingredient_id,
                        baseQtyDelta: -$variance,
                        movementType: 'count',
                        reference: $count,
                        userId: $user?->id,
                    );
                }
            }

            $this->markDeleted($count, $user, $reason, $note);

            activity()->performedOn($count)->causedBy($user)->withProperties(['reason' => $reason, 'note' => $note])->log('stock.count_deleted');

            return $count;
        });
    }
}
