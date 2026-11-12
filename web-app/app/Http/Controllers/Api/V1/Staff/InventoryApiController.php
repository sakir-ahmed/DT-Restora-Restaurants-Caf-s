<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Actions\Inventory\CloseStockCountAction;
use App\Actions\Inventory\CorrectReceivedPurchaseAction;
use App\Actions\Inventory\DeletePurchaseAction;
use App\Actions\Inventory\DeleteStockCountAction;
use App\Actions\Inventory\DeleteStockTransferAction;
use App\Actions\Inventory\DeleteWastageAction;
use App\Actions\Inventory\ReceivePurchaseAction;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Api\Concerns\InteractsWithApi;
use App\Http\Controllers\Api\Controller;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\IngredientStock;
use App\Models\Purchase;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\Wastage;
use App\Support\DeleteReasons;
use App\Support\DeletionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Inventory operations for the mobile app: correct, delete and receive purchases, stock counts, deleting
 * wastage and transfers, and ingredient categories. Each calls the Action the web panel uses.
 * Deletes are POST `.../delete` because they carry a reason in the body.
 */
class InventoryApiController extends Controller
{
    use InteractsWithApi;

    public function updatePurchase(Request $request, string $branch, Purchase $purchase, CorrectReceivedPurchaseAction $correct): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'purchases.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($purchase->branch_id === $branchModel->id, 404);

        if ($purchase->trashed() || $purchase->status === Purchase::STATUS_CANCELLED) {
            throw ValidationException::withMessages(['purchase' => __('inventory.purchase_not_editable')]);
        }

        $data = $request->validate([
            'invoice_no' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'purchased_at' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);
        $data['invoice_no'] = trim((string) ($data['invoice_no'] ?? '')) ?: null;
        $data['note'] ??= null;
        $data['supplier_id'] ??= null;

        if ($purchase->status === Purchase::STATUS_RECEIVED) {
            try {
                $correct->execute($purchase, $data, $user);
            } catch (InsufficientStockException $exception) {
                throw ValidationException::withMessages(['purchase' => __('inventory.purchase_correction_blocked', [
                    'ingredient' => Ingredient::query()->whereKey($exception->ingredientId)->value('name'),
                    'available' => rtrim(rtrim(number_format($exception->availableQty, 3, '.', ''), '0'), '.'),
                ])]);
            }

            return $this->success($this->purchasePayload($purchase->fresh()), __('inventory.purchase_corrected'));
        }

        $updated = DB::transaction(function () use ($data, $purchase, $user): bool {
            $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Purchase::STATUS_DRAFT) {
                return false;
            }

            $locked->update([
                'supplier_id' => $data['supplier_id'],
                'invoice_no' => $data['invoice_no'],
                'purchased_at' => $data['purchased_at'],
                'note' => $data['note'],
            ]);
            $locked->items()->delete();

            foreach ($data['items'] as $line) {
                $locked->items()->create([
                    'ingredient_id' => (int) $line['ingredient_id'],
                    'unit_id' => (int) $line['unit_id'],
                    'qty' => $line['qty'],
                    'unit_cost' => $line['unit_cost'],
                    'line_total' => round((float) $line['qty'] * (float) $line['unit_cost'], 2),
                ]);
            }

            activity()->performedOn($locked)->causedBy($user)->log('purchase.updated');

            return true;
        });

        if (! $updated) {
            throw ValidationException::withMessages(['purchase' => __('inventory.purchase_not_editable')]);
        }

        return $this->success($this->purchasePayload($purchase->fresh()), __('inventory.purchase_updated'));
    }

    public function deletePurchase(Request $request, string $branch, Purchase $purchase, DeletePurchaseAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'purchases.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($purchase->branch_id === $branchModel->id, 404);

        $reason = DeleteReasons::validate($request);

        try {
            $action->execute($purchase, $user, $reason['reason'], $reason['note']);
        } catch (InsufficientStockException $exception) {
            throw ValidationException::withMessages(['purchase' => __('inventory.purchase_delete_blocked', [
                'ingredient' => Ingredient::query()->whereKey($exception->ingredientId)->value('name'),
                'available' => rtrim(rtrim(number_format($exception->availableQty, 3, '.', ''), '0'), '.'),
            ])]);
        }

        return $this->success(null, __('inventory.purchase_deleted'));
    }

    public function receivePurchase(Request $request, string $branch, Purchase $purchase, ReceivePurchaseAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'purchases.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($purchase->branch_id === $branchModel->id, 404);

        $action->execute($purchase, $user);

        return $this->success($this->purchasePayload($purchase->fresh()), __('inventory.purchase_received'));
    }

    public function deleteWastage(Request $request, string $branch, Wastage $wastage, DeleteWastageAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'wastage.log');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($wastage->branch_id === $branchModel->id, 404);

        $reason = DeleteReasons::validate($request);
        $action->execute($wastage, $user, $reason['reason'], $reason['note']);

        return $this->success(null, __('inventory.wastage_deleted'));
    }

    public function stockCounts(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'inventory.view');
        $branchModel = $this->branchOrFail($request, $branch);

        $open = StockCount::query()
            ->where('branch_id', $branchModel->id)
            ->where('status', StockCount::STATUS_OPEN)
            ->with('items.ingredient.unit')
            ->first();

        return $this->success([
            'counts' => StockCount::query()
                ->visible()
                ->where('branch_id', $branchModel->id)
                ->withCount('items')
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (StockCount $count): array => [
                    'id' => $count->id,
                    'status' => $count->status,
                    'note' => $count->note,
                    'items_count' => $count->items_count,
                    'closed_at' => $count->closed_at?->toIso8601String(),
                    'created_at' => $count->created_at?->toIso8601String(),
                ])->all(),
            'open' => $open === null ? null : [
                'id' => $open->id,
                'note' => $open->note,
                'items' => $open->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'ingredient_id' => $item->ingredient_id,
                    'name' => $item->ingredient?->name,
                    'unit_symbol' => $item->ingredient?->unit?->symbol,
                    'unit_factor' => $item->ingredient?->unit !== null ? (string) $item->ingredient->unit->factor : null,
                    'system_qty' => (string) $item->system_qty,
                    'counted_qty' => $item->counted_qty === null ? null : (string) $item->counted_qty,
                ])->all(),
            ],
            'can_manage' => $user->can('inventory.manage'),
        ]);
    }

    public function startStockCount(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'inventory.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        if (StockCount::query()->where('branch_id', $branchModel->id)->where('status', StockCount::STATUS_OPEN)->exists()) {
            throw ValidationException::withMessages(['count' => __('inventory.count_already_open')]);
        }

        $count = DB::transaction(function () use ($request, $branchModel, $user): StockCount {
            $count = StockCount::query()->create([
                'branch_id' => $branchModel->id,
                'status' => StockCount::STATUS_OPEN,
                'note' => $request->input('note'),
                'user_id' => $user->id,
            ]);

            IngredientStock::query()
                ->where('branch_id', $branchModel->id)
                ->whereHas('ingredient', fn ($query) => $query->where('is_active', true))
                ->get()
                ->each(fn (IngredientStock $stock) => $count->items()->create([
                    'ingredient_id' => $stock->ingredient_id,
                    'system_qty' => $stock->stock_qty,
                ]));

            return $count;
        });

        return $this->success(['id' => $count->id], __('inventory.count_started'), 201);
    }

    public function updateStockCount(Request $request, string $branch, StockCount $count): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'inventory.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($count->branch_id === $branchModel->id, 404);

        if (! $count->isOpen()) {
            throw ValidationException::withMessages(['count' => __('inventory.count_closed')]);
        }

        $counted = $request->validate([
            'counted' => ['required', 'array'],
            'counted.*' => ['nullable', 'numeric', 'min:0'],
        ])['counted'];

        DB::transaction(function () use ($count, $counted): void {
            foreach ($counted as $itemId => $qty) {
                if ($qty === null || $qty === '') {
                    continue;
                }

                $count->items()->whereKey((int) $itemId)->update(['counted_qty' => (float) $qty]);
            }
        });

        return $this->success(null, __('inventory.count_saved'));
    }

    public function closeStockCount(Request $request, string $branch, StockCount $count, CloseStockCountAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'inventory.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($count->branch_id === $branchModel->id, 404);

        $action->execute($count, $user);

        return $this->success(null, __('inventory.count_closed'));
    }

    public function deleteStockCount(Request $request, string $branch, StockCount $count, DeleteStockCountAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'inventory.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($count->branch_id === $branchModel->id, 404);

        $reason = DeleteReasons::validate($request);

        try {
            $action->execute($count, $user, $reason['reason'], $reason['note']);
        } catch (InsufficientStockException $exception) {
            throw ValidationException::withMessages(['count' => __('inventory.count_delete_blocked', [
                'ingredient' => Ingredient::query()->whereKey($exception->ingredientId)->value('name'),
                'available' => rtrim(rtrim(number_format($exception->availableQty, 3, '.', ''), '0'), '.'),
            ])]);
        }

        return $this->success(null, __('inventory.count_deleted'));
    }

    public function transfers(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'transfers.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        return $this->success(StockTransfer::query()
            ->visible()
            ->where(fn ($query) => $query->where('from_branch_id', $branchModel->id)->orWhere('to_branch_id', $branchModel->id))
            ->with(['fromBranch:id,name,code', 'toBranch:id,name,code', 'items.ingredient:id,name', 'items.unit:id,symbol'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (StockTransfer $transfer): array => [
                'id' => $transfer->id,
                'status' => $transfer->status,
                'note' => $transfer->note,
                'from' => $transfer->fromBranch?->name,
                'to' => $transfer->toBranch?->name,
                'from_branch_id' => $transfer->from_branch_id,
                'to_branch_id' => $transfer->to_branch_id,
                'created_at' => $transfer->created_at?->toIso8601String(),
                'items' => $transfer->items->map(fn ($item): array => [
                    'ingredient' => $item->ingredient?->name,
                    'unit' => $item->unit?->symbol,
                    'qty' => (string) $item->qty,
                ])->all(),
            ])->all());
    }

    public function deleteTransfer(Request $request, StockTransfer $transfer, DeleteStockTransferAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'transfers.manage');
        abort_unless($user->canAccessBranch($transfer->from_branch_id) || $user->canAccessBranch($transfer->to_branch_id), 404);

        $reason = DeleteReasons::validate($request);

        try {
            $action->execute($transfer, $user, $reason['reason'], $reason['note']);
        } catch (InsufficientStockException $exception) {
            throw ValidationException::withMessages(['transfer' => __('inventory.transfer_delete_blocked', [
                'ingredient' => Ingredient::query()->whereKey($exception->ingredientId)->value('name'),
                'available' => rtrim(rtrim(number_format($exception->availableQty, 3, '.', ''), '0'), '.'),
            ])]);
        }

        return $this->success(null, __('inventory.transfer_deleted'));
    }

    public function deactivateIngredient(Request $request, Ingredient $ingredient): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'inventory.manage');

        $ingredient->update(['is_active' => false]);

        return $this->success(null, __('inventory.ingredient_deactivated'));
    }

    public function categories(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'inventory.view');

        return $this->success(IngredientCategory::query()->withCount('ingredients')->ordered()->get()->map(fn (IngredientCategory $category): array => [
            'id' => $category->id,
            'name' => $category->name,
            'name_bn' => $category->name_bn,
            'sort_order' => $category->sort_order,
            'is_active' => (bool) $category->is_active,
            'ingredients_count' => $category->ingredients_count,
        ])->all());
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'inventory.manage');

        $category = IngredientCategory::query()->create($this->validatedCategory($request, null));

        return $this->success(['id' => $category->id], __('inventory.category_created'), 201);
    }

    public function updateCategory(Request $request, IngredientCategory $category): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'inventory.manage');

        $category->update($this->validatedCategory($request, $category));

        return $this->success(['id' => $category->id], __('inventory.category_updated'));
    }

    public function destroyCategory(Request $request, IngredientCategory $category): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'inventory.manage');

        $blockers = DeletionGuard::blockers([__('inventory.related_ingredients') => $category->ingredients()]);

        if ($blockers !== []) {
            throw ValidationException::withMessages(['category' => DeletionGuard::message($category->name, $blockers)]);
        }

        $category->delete();

        return $this->success(null, __('inventory.category_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCategory(Request $request, ?IngredientCategory $category): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('ingredient_categories', 'name')->ignore($category?->id)],
            'name_bn' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        return [...$data, 'sort_order' => (int) ($data['sort_order'] ?? 0)];
    }

    /**
     * @return array<string, mixed>
     */
    private function purchasePayload(Purchase $purchase): array
    {
        $purchase->loadMissing(['supplier:id,name', 'items.ingredient:id,name', 'items.unit:id,symbol']);

        return [
            'id' => $purchase->id,
            'status' => $purchase->status,
            'invoice_no' => $purchase->invoice_no,
            'purchased_at' => $purchase->purchased_at?->toIso8601String(),
            'supplier_id' => $purchase->supplier_id,
            'supplier' => $purchase->supplier?->name,
            'note' => $purchase->note,
            'total' => (string) $purchase->items->sum('line_total'),
            'items' => $purchase->items->map(fn ($item): array => [
                'ingredient_id' => $item->ingredient_id,
                'ingredient' => $item->ingredient?->name,
                'unit_id' => $item->unit_id,
                'unit' => $item->unit?->symbol,
                'qty' => (string) $item->qty,
                'unit_cost' => (string) $item->unit_cost,
                'line_total' => (string) $item->line_total,
            ])->all(),
        ];
    }
}
