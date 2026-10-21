<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Setting;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
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
            ->with(['items' => fn ($query) => $query->available()->with(['variants', 'addOns', 'media'])->orderBy('sort_order')])
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
            'openOrders' => Order::query()
                ->where('branch_id', $branch->id)
                ->whereIn('status', array_map(fn (OrderStatus $status): string => $status->value, array_filter(OrderStatus::cases(), fn (OrderStatus $status): bool => $status->isKitchenActive())))
                ->whereDoesntHave('payments')
                ->with(['items:id,order_id,menu_item_id,variant_id,name,quantity,add_ons', 'customer:id,mobile'])
                ->latest()
                ->limit(60)
                ->get(['id', 'invoice_no', 'order_type', 'restaurant_table_id', 'customer_id', 'status', 'discount_type', 'discount_value', 'total', 'created_at']),
            'branch' => $branch->only(['id', 'name', 'name_bn', 'code', 'phone', 'address', 'vat_percent', 'vat_inclusive', 'service_charge_percent', 'receipt_footer']),
            'openShift' => $openShift,
            'canOverrideDiscount' => $user->can('discounts.override'),
            'canVoid' => $user->can('orders.void'),
            'posPinMinutes' => Setting::current()->pos_pin_minutes,
        ]);
    }

    public function unlock(Request $request): JsonResponse
    {
        $this->authorize('pos.access');

        $validated = $request->validate([
            'pin' => ['required', 'string', 'min:4', 'max:12'],
        ]);

        $user = $request->user('staff');

        if ($user->pin === null || ! Hash::check($validated['pin'], $user->pin)) {
            throw ValidationException::withMessages([
                'pin' => __('orders.pin_invalid'),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
