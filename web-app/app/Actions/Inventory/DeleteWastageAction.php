<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\Concerns\ReversesSourceJournal;
use App\Models\User;
use App\Models\Wastage;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

class DeleteWastageAction
{
    use ReversesSourceJournal;

    public function __construct(private readonly StockService $stock) {}

    /**
     * Delete a wastage entry made by mistake: the quantity goes back into stock, the wastage journal
     * is reversed, and the entry is kept marked as deleted.
     */
    public function execute(Wastage $wastage, ?User $user = null, ?string $reason = null, ?string $note = null): Wastage
    {
        return DB::transaction(function () use ($wastage, $user, $reason, $note): Wastage {
            $wastage = Wastage::query()->whereKey($wastage->id)->lockForUpdate()->firstOrFail();
            $wastage->loadMissing('unit');

            $baseQty = $wastage->unit->toBase((string) $wastage->qty);

            $this->stock->adjust(
                branchId: $wastage->branch_id,
                ingredientId: $wastage->ingredient_id,
                baseQtyDelta: $baseQty,
                movementType: 'wastage',
                reference: $wastage,
                unitCost: $baseQty > 0 ? round((float) $wastage->cost / $baseQty, 4) : null,
                userId: $user?->id,
            );

            $this->reverseJournalsFor($wastage, $user, __('inventory.wastage_delete_journal_note', ['id' => $wastage->id]));

            $this->markDeleted($wastage, $user, $reason, $note);

            activity()->performedOn($wastage)->causedBy($user)->withProperties(['reason' => $reason, 'note' => $note])->log('wastage.deleted');

            return $wastage;
        });
    }
}
