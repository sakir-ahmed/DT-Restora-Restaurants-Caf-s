<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\CloseStockCountAction;
use App\Actions\Inventory\DeleteStockCountAction;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\StockCount;
use App\Support\DeleteReasons;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockCountController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('inventory.view');

        $branch = $this->currentBranch($request);

        $counts = StockCount::query()
            ->visible()
            ->where('branch_id', $branch->id)
            ->withCount('items')
            ->latest()
            ->limit(50)
            ->get();

        $openCount = StockCount::query()
            ->where('branch_id', $branch->id)
            ->where('status', StockCount::STATUS_OPEN)
            ->with(['items.ingredient.unit'])
            ->first();

        return Inertia::render('admin/stock-counts/Index', [
            'counts' => $counts,
            'openCount' => $openCount,
            'canManage' => $request->user('staff')?->can('inventory.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.manage');

        $branch = $this->currentBranch($request);

        $existing = StockCount::query()
            ->where('branch_id', $branch->id)
            ->where('status', StockCount::STATUS_OPEN)
            ->exists();

        if ($existing) {
            return back()->with('error', __('inventory.count_already_open'));
        }

        DB::transaction(function () use ($request, $branch): void {
            $count = StockCount::query()->create([
                'branch_id' => $branch->id,
                'status' => StockCount::STATUS_OPEN,
                'note' => $request->input('note'),
                'user_id' => $request->user('staff')?->id,
            ]);

            $stocks = IngredientStock::query()
                ->where('branch_id', $branch->id)
                ->whereHas('ingredient', fn ($query) => $query->where('is_active', true))
                ->with('ingredient:id,name,unit_id')
                ->get();

            foreach ($stocks as $stock) {
                $count->items()->create([
                    'ingredient_id' => $stock->ingredient_id,
                    'system_qty' => $stock->stock_qty,
                ]);
            }
        });

        return back()->with('success', __('inventory.count_started'));
    }

    public function updateCounts(Request $request, StockCount $count): RedirectResponse
    {
        $this->authorize('inventory.manage');

        abort_unless($count->isOpen(), 422);

        $counted = (array) $request->input('counted', []);

        DB::transaction(function () use ($count, $counted): void {
            foreach ($counted as $itemId => $qty) {
                if ($qty === null || $qty === '') {
                    continue;
                }

                $count->items()->whereKey((int) $itemId)->update([
                    'counted_qty' => (float) $qty,
                ]);
            }
        });

        return back()->with('success', __('inventory.count_saved'));
    }

    public function close(Request $request, StockCount $count, CloseStockCountAction $action): RedirectResponse
    {
        $this->authorize('inventory.manage');

        $action->execute($count, $request->user('staff'));

        return back()->with('success', __('inventory.count_closed'));
    }

    /**
     * Delete a count made by mistake. A closed count takes its variances back out of stock and is
     * refused when stock has since fallen too low to do so.
     */
    public function destroy(Request $request, StockCount $count, DeleteStockCountAction $action): RedirectResponse
    {
        $this->authorize('inventory.manage');

        $user = $request->user('staff');
        abort_unless($user->canAccessBranch($count->branch_id), 404);

        $reason = DeleteReasons::validate($request);

        try {
            $action->execute($count, $user, $reason['reason'], $reason['note']);
        } catch (InsufficientStockException $exception) {
            return back()->with('error', __('inventory.count_delete_blocked', [
                'ingredient' => Ingredient::query()->whereKey($exception->ingredientId)->value('name'),
                'available' => rtrim(rtrim(number_format($exception->availableQty, 3, '.', ''), '0'), '.'),
            ]));
        }

        return back()->with('success', __('inventory.count_deleted'));
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
