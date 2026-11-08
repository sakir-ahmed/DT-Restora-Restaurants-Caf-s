<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\Accounting\PostJournalEntryAction;
use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\IngredientStock;
use App\Models\Journal;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\StockService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class CorrectReceivedPurchaseAction
{
    public function __construct(
        private readonly StockService $stock,
        private readonly PostJournalEntryAction $journals,
    ) {}

    /**
     * Correct a received purchase after a data entry mistake. Everything runs in one
     * transaction, so a failure leaves stock, cost and the ledger exactly as they were:
     *
     * 1. The net stock change per ingredient is checked first, so a correction can never
     *    take stock below zero because the goods were already used.
     * 2. The old lines are reversed out of stock (and out of the average cost) and the
     *    new lines are received, each as its own movement, so the history stays auditable.
     * 3. The old journal is reversed and a new one is posted for the corrected total.
     *
     * @param  array{supplier_id: int|null, invoice_no: string|null, purchased_at: string, note: string|null, items: array<int, array{ingredient_id: int|string, unit_id: int|string, qty: int|float|string, unit_cost: int|float|string}>}  $data
     *
     * @throws InsufficientStockException
     */
    public function execute(Purchase $purchase, array $data, ?User $user = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $data, $user): Purchase {
            $purchase = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if (! $purchase->isReceived()) {
                throw new LogicException('Only a received purchase can be corrected.');
            }

            $purchase->load('items.unit');
            $oldItems = $purchase->items;
            $units = Unit::query()->whereIn('id', collect($data['items'])->pluck('unit_id'))->get()->keyBy('id');

            $before = $this->summary($oldItems);

            $newLines = collect($data['items'])->map(function (array $line) use ($units): array {
                $unit = $units->get((int) $line['unit_id']);

                return [
                    'ingredient_id' => (int) $line['ingredient_id'],
                    'unit_id' => (int) $line['unit_id'],
                    'qty' => $line['qty'],
                    'unit_cost' => $line['unit_cost'],
                    'line_total' => round((float) $line['qty'] * (float) $line['unit_cost'], 2),
                    'base_qty' => $unit->toBase((string) $line['qty']),
                    'base_unit_cost' => (float) $unit->factor === 0.0 ? 0.0 : round((float) $line['unit_cost'] / (float) $unit->factor, 4),
                ];
            });

            $this->assertStockStaysNonNegative($purchase, $oldItems, $newLines->all());

            // 1. Take the old receipt out of stock and out of the average cost.
            foreach ($oldItems as $item) {
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

            // 2. Replace the lines and receive the corrected ones.
            $purchase->items()->delete();

            foreach ($newLines as $line) {
                $purchase->items()->create([
                    'ingredient_id' => $line['ingredient_id'],
                    'unit_id' => $line['unit_id'],
                    'qty' => $line['qty'],
                    'unit_cost' => $line['unit_cost'],
                    'line_total' => $line['line_total'],
                ]);

                $stock = $this->stock->adjust(
                    branchId: $purchase->branch_id,
                    ingredientId: $line['ingredient_id'],
                    baseQtyDelta: $line['base_qty'],
                    movementType: 'purchase',
                    reference: $purchase,
                    unitCost: $line['base_unit_cost'],
                    userId: $user?->id,
                );

                $this->stock->applyWeightedAverageCost($stock, $line['base_qty'], $line['base_unit_cost']);
            }

            // 3. Reverse the old journal and post the corrected one.
            $original = Journal::query()
                ->where('source_type', $purchase->getMorphClass())
                ->where('source_id', $purchase->id)
                ->where('status', Journal::STATUS_POSTED)
                ->first();

            if ($original !== null) {
                $this->journals->reverse($original, $user, __('inventory.purchase_correction_journal_note', ['id' => $purchase->id]));
            }

            $purchase->update([
                'supplier_id' => $data['supplier_id'],
                'invoice_no' => $data['invoice_no'],
                'purchased_at' => $data['purchased_at'],
                'note' => $data['note'],
                'total' => $newLines->sum('line_total'),
            ]);

            app(AccountingService::class)->postPurchaseReceived($purchase->fresh(), $user);

            $purchase->load('items.unit');

            activity()
                ->performedOn($purchase)
                ->causedBy($user)
                ->withProperties(['before' => $before, 'after' => $this->summary($purchase->items)])
                ->log('purchase.corrected');

            return $purchase;
        });
    }

    /**
     * Per ingredient, the correction removes the old base quantity and adds the new one.
     * If goods were already used and the net change would push stock below zero, refuse.
     *
     * @param  Collection<int, PurchaseItem>  $oldItems
     * @param  array<int, array<string, mixed>>  $newLines
     */
    private function assertStockStaysNonNegative(Purchase $purchase, $oldItems, array $newLines): void
    {
        if (Branch::query()->whereKey($purchase->branch_id)->value('allow_negative_stock')) {
            return;
        }

        $net = [];

        foreach ($oldItems as $item) {
            $net[$item->ingredient_id] = ($net[$item->ingredient_id] ?? 0.0) - $item->unit->toBase((string) $item->qty);
        }

        foreach ($newLines as $line) {
            $net[$line['ingredient_id']] = ($net[$line['ingredient_id']] ?? 0.0) + (float) $line['base_qty'];
        }

        foreach ($net as $ingredientId => $delta) {
            if ($delta >= 0) {
                continue;
            }

            $available = (float) (IngredientStock::query()
                ->where('branch_id', $purchase->branch_id)
                ->where('ingredient_id', $ingredientId)
                ->lockForUpdate()
                ->value('stock_qty') ?? 0);

            if (round($available + $delta, 3) < 0) {
                throw new InsufficientStockException((int) $ingredientId, $available, $delta);
            }
        }
    }

    /**
     * @param  Collection<int, PurchaseItem>  $items
     * @return array<int, array{ingredient_id: int, unit_id: int, qty: string, unit_cost: string}>
     */
    private function summary($items): array
    {
        return $items->map(fn (PurchaseItem $item): array => [
            'ingredient_id' => $item->ingredient_id,
            'unit_id' => $item->unit_id,
            'qty' => (string) $item->qty,
            'unit_cost' => (string) $item->unit_cost,
        ])->values()->all();
    }
}
