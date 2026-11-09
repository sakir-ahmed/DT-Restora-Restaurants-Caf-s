<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\CorrectReceivedPurchaseAction;
use App\Actions\Inventory\DeletePurchaseAction;
use App\Actions\Inventory\ReceivePurchaseAction;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PurchaseRequest;
use App\Http\Requests\Admin\PurchaseUpdateRequest;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Unit;
use App\Support\DeleteReasons;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('purchases.manage');

        $branch = $this->currentBranch($request);

        $purchases = Purchase::query()
            ->visible()
            ->where('branch_id', $branch->id)
            ->with(['supplier:id,name', 'items.ingredient:id,name', 'items.unit:id,symbol'])
            ->latest('purchased_at')
            ->limit(100)
            ->get();

        return Inertia::render('admin/purchases/Index', [
            'purchases' => $purchases,
            'suppliers' => Supplier::query()->where('is_active', true)->with('ingredients:id')->orderBy('name')->get(['id', 'name'])
                ->map(fn (Supplier $supplier): array => ['id' => $supplier->id, 'name' => $supplier->name, 'ingredient_ids' => $supplier->ingredients->pluck('id')->all()]),
            'ingredients' => Ingredient::query()->active()->with('unit:id,symbol,type')->orderBy('name')->get(['id', 'name', 'unit_id', 'ingredient_category_id']),
            'categories' => IngredientCategory::query()->active()->ordered()->get(['id', 'name', 'name_bn']),
            'units' => Unit::query()->orderBy('type')->orderBy('name')->get(['id', 'name', 'symbol', 'type']),
            'canManage' => $request->user('staff')?->can('purchases.manage'),
        ]);
    }

    public function store(PurchaseRequest $request, ReceivePurchaseAction $receiveAction): RedirectResponse
    {
        $branch = $this->currentBranch($request);

        $defaultSupplierId = $request->validated('supplier_id');

        $purchases = DB::transaction(function () use ($request, $branch, $defaultSupplierId): Collection {
            return collect($request->validated('items'))
                ->map(fn (array $line): array => [
                    ...$line,
                    'supplier_id' => ($line['supplier_id'] ?? $defaultSupplierId) !== null ? (int) ($line['supplier_id'] ?? $defaultSupplierId) : null,
                    'invoice_no' => trim((string) ($line['invoice_no'] ?? $request->validated('invoice_no'))) ?: null,
                ])
                ->groupBy(fn (array $line): string => $line['supplier_id'].'|'.$line['invoice_no'])
                ->map(function (Collection $lines) use ($request, $branch): Purchase {
                    $purchase = Purchase::query()->create([
                        'branch_id' => $branch->id,
                        'supplier_id' => $lines->first()['supplier_id'],
                        'invoice_no' => $lines->first()['invoice_no'],
                        'purchased_at' => $request->validated('purchased_at'),
                        'note' => $request->validated('note'),
                        'status' => Purchase::STATUS_DRAFT,
                        'user_id' => $request->user('staff')?->id,
                    ]);

                    foreach ($lines as $line) {
                        $purchase->items()->create([
                            'ingredient_id' => (int) $line['ingredient_id'],
                            'unit_id' => (int) $line['unit_id'],
                            'qty' => $line['qty'],
                            'unit_cost' => $line['unit_cost'],
                            'line_total' => round((float) $line['qty'] * (float) $line['unit_cost'], 2),
                        ]);
                    }

                    return $purchase->load('items.unit');
                })
                ->values();
        });

        if ($request->boolean('receive')) {
            foreach ($purchases as $purchase) {
                $receiveAction->execute($purchase, $request->user('staff'));
            }
        }

        return back()->with('success', __('inventory.purchase_created'));
    }

    /**
     * Edit a purchase. A draft has posted nothing, so it is a plain update. A received purchase is
     * corrected through one action that reverses and re-posts stock, cost and the ledger together, and
     * refuses when the goods were already used. A cancelled purchase is read-only.
     */
    public function update(PurchaseUpdateRequest $request, Purchase $purchase, CorrectReceivedPurchaseAction $correct): RedirectResponse
    {
        $user = $request->user('staff');
        abort_unless($user->canAccessBranch($purchase->branch_id), 404);

        if ($purchase->trashed()) {
            return back()->with('error', __('inventory.purchase_not_editable'));
        }

        $data = $request->validated();
        $data['invoice_no'] = trim((string) ($data['invoice_no'] ?? '')) ?: null;
        $data['note'] = $data['note'] ?? null;
        $data['supplier_id'] = $data['supplier_id'] ?? null;

        if ($purchase->status === Purchase::STATUS_RECEIVED) {
            try {
                $correct->execute($purchase, $data, $user);
            } catch (InsufficientStockException $exception) {
                $name = Ingredient::query()->whereKey($exception->ingredientId)->value('name');

                return back()->with('error', __('inventory.purchase_correction_blocked', [
                    'ingredient' => $name,
                    'available' => rtrim(rtrim(number_format($exception->availableQty, 3, '.', ''), '0'), '.'),
                ]));
            }

            return back()->with('success', __('inventory.purchase_corrected'));
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
            return back()->with('error', __('inventory.purchase_not_editable'));
        }

        return back()->with('success', __('inventory.purchase_updated'));
    }

    /**
     * Delete a purchase entered by mistake. A received one is first undone (stock, average cost and
     * ledger) and refused when its goods were already used; the row is kept marked as deleted.
     */
    public function destroy(Request $request, Purchase $purchase, DeletePurchaseAction $action): RedirectResponse
    {
        $this->authorize('purchases.manage');

        $user = $request->user('staff');
        abort_unless($user->canAccessBranch($purchase->branch_id), 404);

        $reason = DeleteReasons::validate($request);

        try {
            $action->execute($purchase, $user, $reason['reason'], $reason['note']);
        } catch (InsufficientStockException $exception) {
            $name = Ingredient::query()->whereKey($exception->ingredientId)->value('name');

            return back()->with('error', __('inventory.purchase_delete_blocked', [
                'ingredient' => $name,
                'available' => rtrim(rtrim(number_format($exception->availableQty, 3, '.', ''), '0'), '.'),
            ]));
        }

        return back()->with('success', __('inventory.purchase_deleted'));
    }

    /**
     * Add an ingredient from the purchase form without leaving it.
     */
    public function storeIngredient(Request $request): JsonResponse
    {
        $this->authorize('purchases.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'ingredient_category_id' => ['nullable', 'integer', 'exists:ingredient_categories,id'],
        ]);

        $ingredient = Ingredient::query()->create([...$validated, 'reorder_level' => 0, 'is_active' => true]);

        activity()->performedOn($ingredient)->causedBy($request->user('staff'))->log('ingredient.created');

        $ingredient->load('unit:id,symbol,type');

        return response()->json([
            'id' => $ingredient->id,
            'name' => $ingredient->name,
            'unit_id' => $ingredient->unit_id,
            'ingredient_category_id' => $ingredient->ingredient_category_id,
            'unit' => $ingredient->unit?->only(['id', 'symbol', 'type']),
        ], 201);
    }

    /**
     * Add a supplier from the purchase form without leaving it. Only the name is needed.
     */
    public function storeSupplier(Request $request): JsonResponse
    {
        $this->authorize('purchases.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'mobile' => ['nullable', 'string', 'max:20'],
        ]);

        $supplier = Supplier::query()->create([...$validated, 'is_active' => true]);

        activity()->performedOn($supplier)->causedBy($request->user('staff'))->log('supplier.created');

        return response()->json(['id' => $supplier->id, 'name' => $supplier->name, 'ingredient_ids' => []], 201);
    }

    public function receive(Request $request, Purchase $purchase, ReceivePurchaseAction $action): RedirectResponse
    {
        $this->authorize('purchases.manage');

        $user = $request->user('staff');
        abort_unless($user->canAccessBranch($purchase->branch_id), 404);

        $action->execute($purchase, $user);

        return back()->with('success', __('inventory.purchase_received'));
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
