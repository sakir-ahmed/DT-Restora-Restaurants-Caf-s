<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupplierRequest;
use App\Models\Ingredient;
use App\Models\Supplier;
use App\Support\DeletionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('inventory.view');

        return Inertia::render('admin/suppliers/Index', [
            'suppliers' => Supplier::query()
                ->withCount('purchases')
                ->with('ingredients:id')
                ->orderBy('name')
                ->get()
                ->map(fn (Supplier $supplier): array => [
                    ...$supplier->toArray(),
                    'ingredient_ids' => $supplier->ingredients->pluck('id')->all(),
                ]),
            'ingredients' => Ingredient::query()->active()->with('unit:id,symbol')->orderBy('name')->get(['id', 'name', 'unit_id']),
            'canManage' => $request->user('staff')?->can('inventory.manage'),
        ]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::query()->create($request->safe()->except('ingredient_ids'));
        $supplier->ingredients()->sync($request->validated('ingredient_ids', []));

        return back()->with('success', __('inventory.supplier_created'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->safe()->except('ingredient_ids'));
        $supplier->ingredients()->sync($request->validated('ingredient_ids', []));

        return back()->with('success', __('inventory.supplier_updated'));
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('inventory.manage');

        $blockers = DeletionGuard::blockers([__('inventory.related_purchases') => $supplier->purchases()]);

        if ($blockers !== []) {
            return back()->with('error', DeletionGuard::message($supplier->name, $blockers));
        }

        $supplier->delete();

        return back()->with('success', __('inventory.supplier_deleted'));
    }
}
