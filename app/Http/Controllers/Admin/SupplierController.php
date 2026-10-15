<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupplierRequest;
use App\Models\Supplier;
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
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'canManage' => $request->user('staff')?->can('inventory.manage'),
        ]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        Supplier::query()->create($request->validated());

        return back()->with('success', __('inventory.supplier_created'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return back()->with('success', __('inventory.supplier_updated'));
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('inventory.manage');

        if ($supplier->purchases()->exists()) {
            return back()->with('error', __('inventory.supplier_delete_not_empty'));
        }

        $supplier->delete();

        return back()->with('success', __('inventory.supplier_deleted'));
    }
}
