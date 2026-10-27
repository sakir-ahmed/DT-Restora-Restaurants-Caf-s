<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Sales\ApproveOrderVoidAction;
use App\Actions\Sales\RequestOrderVoidAction;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DataVersion;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\VoidRequest;
use App\Services\PosSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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
            'branch' => [
                ...$branch->only(['id', 'name', 'name_bn', 'code', 'phone', 'address', 'vat_percent', 'vat_inclusive', 'service_charge_percent', 'receipt_footer']),
                'pos_settings' => PosSettings::for($branch),
            ],
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

    /**
     * Free an occupied table. A table whose order is already paid is just
     * released; one with an unpaid running order is force-cleared, which
     * voids that order, so it needs the void permission.
     */
    public function releaseTable(Request $request, RestaurantTable $table, RequestOrderVoidAction $requestVoid, ApproveOrderVoidAction $approveVoid): RedirectResponse
    {
        $this->authorize('pos.access');

        $user = $request->user('staff');

        abort_unless($user->canAccessBranch($table->branch_id), 404);

        $validated = $request->validate([
            'force' => ['sometimes', 'boolean'],
            'reason_code' => ['required_if:force,true', 'nullable', 'string', Rule::in(VoidRequest::REASONS)],
        ]);

        $running = $this->unpaidRunningOrders($table->branch_id)
            ->where('restaurant_table_id', $table->id)
            ->get();

        if ($running->isNotEmpty()) {
            if (! $request->boolean('force')) {
                throw ValidationException::withMessages([
                    'table' => __('orders.table_has_running_order'),
                ]);
            }

            $this->authorize('orders.void');

            foreach ($running as $order) {
                $approveVoid->execute($requestVoid->execute($order, $user, (string) $validated['reason_code']), $user);
            }
        }

        $table->update(['status' => 'free']);

        DataVersion::bump();

        return back()->with('success', __('orders.table_released'));
    }

    /**
     * Force-clear a running order that has no table: void it, restore
     * stock and drop it from the running list.
     */
    public function clearOrder(Request $request, Order $order, RequestOrderVoidAction $requestVoid, ApproveOrderVoidAction $approveVoid): RedirectResponse
    {
        $this->authorize('pos.access');
        $this->authorize('orders.void');

        $user = $request->user('staff');

        abort_unless($user->canAccessBranch($order->branch_id), 404);

        $validated = $request->validate([
            'reason_code' => ['required', 'string', Rule::in(VoidRequest::REASONS)],
        ]);

        $running = $this->unpaidRunningOrders($order->branch_id)->whereKey($order->id)->exists();

        if (! $running) {
            throw ValidationException::withMessages([
                'order' => __('orders.not_running'),
            ]);
        }

        $approveVoid->execute($requestVoid->execute($order, $user, (string) $validated['reason_code']), $user);

        return back()->with('success', __('orders.voided'));
    }

    /**
     * @return Builder<Order>
     */
    private function unpaidRunningOrders(int $branchId): Builder
    {
        return Order::query()
            ->where('branch_id', $branchId)
            ->whereIn('status', array_map(fn (OrderStatus $status): string => $status->value, array_filter(OrderStatus::cases(), fn (OrderStatus $status): bool => $status->isKitchenActive())))
            ->whereDoesntHave('payments');
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
