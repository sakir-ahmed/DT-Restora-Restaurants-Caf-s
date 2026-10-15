<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\ReceivePurchaseAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PurchaseRequest;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->where('branch_id', $branch->id)
            ->with(['supplier:id,name', 'items.ingredient:id,name', 'items.unit:id,symbol'])
            ->latest('purchased_at')
            ->limit(100)
            ->get();

        return Inertia::render('admin/purchases/Index', [
            'purchases' => $purchases,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'ingredients' => Ingredient::query()->active()->with('unit:id,symbol,type')->orderBy('name')->get(['id', 'name', 'unit_id']),
            'units' => Unit::query()->orderBy('type')->orderBy('name')->get(['id', 'name', 'symbol', 'type']),
            'canManage' => $request->user('staff')?->can('purchases.manage'),
        ]);
    }

    public function store(PurchaseRequest $request, ReceivePurchaseAction $receiveAction): RedirectResponse
    {
        $branch = $this->currentBranch($request);

        $purchase = DB::transaction(function () use ($request, $branch): Purchase {
            $purchase = Purchase::query()->create([
                'branch_id' => $branch->id,
                'supplier_id' => (int) $request->validated('supplier_id'),
                'invoice_no' => $request->validated('invoice_no'),
                'purchased_at' => $request->validated('purchased_at'),
                'note' => $request->validated('note'),
                'status' => Purchase::STATUS_DRAFT,
                'user_id' => $request->user('staff')?->id,
            ]);

            foreach ($request->validated('items') as $line) {
                $purchase->items()->create([
                    'ingredient_id' => (int) $line['ingredient_id'],
                    'unit_id' => (int) $line['unit_id'],
                    'qty' => $line['qty'],
                    'unit_cost' => $line['unit_cost'],
                    'line_total' => round((float) $line['qty'] * (float) $line['unit_cost'], 2),
                ]);
            }

            return $purchase->load('items.unit');
        });

        if ($request->boolean('receive')) {
            $receiveAction->execute($purchase, $request->user('staff'));
        }

        return back()->with('success', __('inventory.purchase_created'));
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
