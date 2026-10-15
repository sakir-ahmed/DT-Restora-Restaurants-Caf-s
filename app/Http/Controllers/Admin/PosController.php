<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\RestaurantTable;
use App\Models\Shift;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('pos.access');

        $branch = $this->currentBranch($request);
        $user = $request->user('staff');

        $categories = MenuCategory::query()
            ->where('branch_id', $branch->id)
            ->active()
            ->ordered()
            ->with(['items' => fn ($query) => $query->available()->with(['variants', 'addOns'])->orderBy('sort_order')])
            ->get();

        $openShift = Shift::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $user->id)
            ->whereNull('closed_at')
            ->first();

        return Inertia::render('admin/pos/Index', [
            'categories' => $categories,
            'tables' => RestaurantTable::query()
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'area', 'seats', 'status']),
            'branch' => $branch->only(['id', 'code', 'vat_percent', 'vat_inclusive', 'service_charge_percent', 'receipt_footer']),
            'openShift' => $openShift,
            'canOverrideDiscount' => $user->can('discounts.override'),
            'canVoid' => $user->can('orders.void'),
        ]);
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
