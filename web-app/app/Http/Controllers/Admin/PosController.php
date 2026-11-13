<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Sales\ApproveOrderVoidAction;
use App\Actions\Sales\RequestOrderVoidAction;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DataVersion;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\Recipe;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\VoidRequest;
use App\Services\PosSettings;
use App\Services\Sms\SmsFeatures;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            ->regular()
            ->where('branch_id', $branch->id)
            ->active()
            ->ordered()
            ->with(['items' => fn ($query) => $query->available()->regular()->with(['variants', 'addOns', 'media'])->orderBy('sort_order')])
            ->get();

        $openShift = Shift::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $user->id)
            ->whereNull('closed_at')
            ->first();

        return Inertia::render('admin/pos/Index', [
            'invoiceSmsEnabled' => SmsFeatures::enabled('invoice'),
            'categories' => $categories,
            'tables' => $this->tablesWithBookings($branch),
            'stockBook' => $this->stockBook($branch, $categories),
            'openOrders' => Order::query()
                ->where('branch_id', $branch->id)
                ->whereIn('status', array_map(fn (OrderStatus $status): string => $status->value, array_filter(OrderStatus::cases(), fn (OrderStatus $status): bool => $status->isKitchenActive())))
                // Booking money on an order is not a payment that settles it: the order stays open.
                ->whereDoesntHave('payments', fn (Builder $payments) => $payments->where('is_advance', false))
                ->withSum(['payments as advance_paid' => fn (Builder $payments) => $payments->where('is_advance', true)->where('status', 'completed')], 'amount')
                ->with(['items:id,order_id,menu_item_id,variant_id,name,quantity,add_ons', 'customer:id,mobile'])
                ->latest()
                ->limit(60)
                ->get(['id', 'invoice_no', 'order_type', 'restaurant_table_id', 'customer_id', 'status', 'discount_type', 'discount_value', 'total', 'created_at'])
                ->map(fn (Order $order): array => $order->toArray() + [
                    'advance' => (float) ($order->advance_paid ?? 0),
                    'balance' => max(0, round((float) $order->total - (float) ($order->advance_paid ?? 0), 2)),
                ])
                ->values(),
            'editTarget' => $this->orderForEdit($request, $branch),
            'branch' => [
                ...$branch->only(['id', 'name', 'name_bn', 'code', 'phone', 'address', 'vat_percent', 'vat_inclusive', 'service_charge_percent', 'receipt_footer']),
                'pos_settings' => PosSettings::for($branch),
                'tax_registration_no' => Setting::current()->tax_registration_no,
            ],
            'openShift' => $openShift,
            'canOverrideDiscount' => $user->can('discounts.override'),
            'canVoid' => $user->can('orders.void'),
            'posPinMinutes' => Setting::current()->pos_pin_minutes,
            'posLockEnabled' => (bool) Setting::current()->pos_lock_enabled,
        ]);
    }

    /**
     * Customers matching a mobile number or a name, for the POS auto-fill. Digits search the
     * mobile; anything else searches the name.
     */
    public function customers(Request $request): JsonResponse
    {
        $this->authorize('pos.access');

        $term = trim($request->string('q')->toString());

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $digits = preg_replace('/\D+/', '', $term) ?? '';
        $mobileSearch = $digits !== '' && $digits === preg_replace('/[\s+()-]+/', '', $term);

        $customers = Customer::query()
            ->whereNotNull('mobile')
            ->when(
                $mobileSearch,
                fn (Builder $query) => $query->where('mobile', 'like', '%'.ltrim($digits, '+').'%'),
                fn (Builder $query) => $query->where('name', 'like', '%'.$term.'%'),
            )
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'mobile']);

        return response()->json($customers->map(fn (Customer $customer): array => [
            'id' => $customer->id,
            'name' => $customer->name === __('orders.walk_in_customer') ? '' : $customer->name,
            'mobile' => preg_replace('/^88/', '', (string) $customer->mobile),
        ])->values());
    }

    /**
     * What each menu item uses and what the branch has, so the POS can warn while an item is
     * being picked instead of after the order is sent. The server still checks on save.
     *
     * @param  Collection<int, MenuCategory>  $categories
     * @return array{allow_negative: bool, recipes: array<int, array<int, array{ingredient_id: int, qty: float}>>, stock: array<int, array{name: string, name_bn: ?string, qty: float}>}
     */
    private function stockBook(Branch $branch, Collection $categories): array
    {
        $recipes = Recipe::query()
            ->with('items')
            ->whereIn('menu_item_id', $categories->flatMap(fn (MenuCategory $category) => $category->items->pluck('id')))
            ->get();

        $ingredientIds = $recipes->flatMap(fn (Recipe $recipe) => $recipe->items->pluck('ingredient_id'))->unique()->values();
        $quantities = IngredientStock::query()
            ->where('branch_id', $branch->id)
            ->whereIn('ingredient_id', $ingredientIds)
            ->pluck('stock_qty', 'ingredient_id');

        return [
            'allow_negative' => (bool) $branch->allow_negative_stock,
            'recipes' => $recipes
                ->mapWithKeys(fn (Recipe $recipe): array => [
                    $recipe->menu_item_id => $recipe->items
                        ->map(fn ($line): array => ['ingredient_id' => (int) $line->ingredient_id, 'qty' => (float) $line->qty])
                        ->values()
                        ->all(),
                ])
                ->all(),
            'stock' => Ingredient::query()
                ->whereKey($ingredientIds)
                ->get(['id', 'name', 'name_bn'])
                ->mapWithKeys(fn (Ingredient $ingredient): array => [
                    $ingredient->id => [
                        'name' => $ingredient->name,
                        'name_bn' => $ingredient->name_bn,
                        'qty' => (float) ($quantities[$ingredient->id] ?? 0),
                    ],
                ])
                ->all(),
        ];
    }

    /**
     * The branch tables, each with the next booking that is running or starts within three hours,
     * so the waiter sees a booked table before seating someone else there.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function tablesWithBookings(Branch $branch): Collection
    {
        // Each table with the first booking that touches it (a party can hold several tables).
        $bookings = collect();

        Reservation::query()
            ->where('branch_id', $branch->id)
            ->where('status', Reservation::BOOKED)
            ->where('ends_at', '>', now())
            ->where('starts_at', '<', now()->addHours(3))
            ->with('tables:id')
            ->orderBy('starts_at')
            ->get()
            ->each(function (Reservation $reservation) use ($bookings): void {
                foreach ($reservation->tables as $table) {
                    if (! $bookings->has($table->id)) {
                        $bookings->put($table->id, $reservation);
                    }
                }
            });

        $tables = RestaurantTable::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'area', 'seats', 'status']);

        // What the last bill on an occupied table came to, so staff can see paid or due before clearing it.
        $lastOrders = Order::query()
            ->where('branch_id', $branch->id)
            ->whereIn('restaurant_table_id', $tables->where('status', 'occupied')->pluck('id'))
            ->where('status', '!=', OrderStatus::Void->value)
            ->withSum(['payments as paid_amount' => fn (Builder $payments) => $payments->where('status', 'completed')], 'amount')
            ->with('customer:id,name,mobile')
            ->latest()
            ->get()
            ->unique('restaurant_table_id')
            ->keyBy('restaurant_table_id');

        return $tables->map(fn (RestaurantTable $table): array => $table->toArray() + [
            'booking' => $bookings->has($table->id) ? [
                'guest_name' => $bookings[$table->id]->guest_name,
                'starts_at' => $bookings[$table->id]->starts_at->toIso8601String(),
            ] : null,
            'last_order' => $lastOrders->has($table->id) ? [
                'invoice_no' => $lastOrders[$table->id]->invoice_no,
                'status' => $lastOrders[$table->id]->status->value,
                'is_due' => (bool) $lastOrders[$table->id]->is_due,
                'total' => (float) $lastOrders[$table->id]->total,
                'paid' => round((float) ($lastOrders[$table->id]->paid_amount ?? 0), 2),
                'balance' => max(0.0, round((float) $lastOrders[$table->id]->total - (float) ($lastOrders[$table->id]->paid_amount ?? 0), 2)),
                'at' => ($lastOrders[$table->id]->paid_at ?? $lastOrders[$table->id]->created_at)?->toIso8601String(),
                'customer' => $lastOrders[$table->id]->customer?->name,
            ] : null,
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

    /**
     * The order named by ?edit=<id>, in the shape the POS loads an order for editing. It is sent
     * on its own, so it opens even when it is not among the open orders listed on the screen
     * (an older order, a paid or due sale, or one that came from a delivery platform).
     *
     * @return array<string, mixed>|null
     */
    private function orderForEdit(Request $request, Branch $branch): ?array
    {
        $id = (int) $request->integer('edit');

        if ($id === 0) {
            return null;
        }

        $order = Order::query()
            ->where('branch_id', $branch->id)
            ->where('status', '!=', OrderStatus::Void->value)
            ->withSum(['payments as advance_paid' => fn (Builder $payments) => $payments->where('is_advance', true)->where('status', 'completed')], 'amount')
            ->with(['items:id,order_id,menu_item_id,variant_id,name,quantity,add_ons', 'customer:id,mobile'])
            ->find($id);

        if ($order === null) {
            return null;
        }

        $paid = $order->amountPaid();
        $settled = $order->status === OrderStatus::Paid;

        return $order->only(['id', 'invoice_no', 'order_type', 'restaurant_table_id', 'customer_id', 'status', 'discount_type', 'discount_value', 'total'])
            + [
                'items' => $order->items,
                'customer' => $order->customer,
                'is_due' => (bool) $order->is_due,
                'settled' => $settled,
                'paid' => $paid,
                'advance' => (float) ($order->advance_paid ?? 0),
                'balance' => max(0, round((float) $order->total - $paid, 2)),
            ];
    }
}
