<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Actions\Delivery\AssignRiderAction;
use App\Actions\Inventory\ApproveStockTransferAction;
use App\Actions\Inventory\LogWastageAction;
use App\Actions\Inventory\ReceivePurchaseAction;
use App\Actions\Inventory\ReceiveStockTransferAction;
use App\Actions\Inventory\SaveRecipeAction;
use App\Actions\Sales\ApproveOrderVoidAction;
use App\Actions\Sales\CloseShiftAction;
use App\Actions\Sales\CreateOrderAction;
use App\Actions\Sales\OpenShiftAction;
use App\Actions\Sales\PayOrderAction;
use App\Actions\Sales\RequestOrderVoidAction;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Events\OrderCreated;
use App\Http\Controllers\Api\Concerns\InteractsWithApi;
use App\Http\Controllers\Api\Controller;
use App\Models\AggregatorChannel;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Recipe;
use App\Models\Rider;
use App\Models\Shift;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\VoidRequest;
use App\Services\Aggregators\AggregatorOrderService;
use App\Services\CostingService;
use App\Services\CouponService;
use App\Services\LoyaltyService;
use App\Services\Metrics\MetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffApiController extends Controller
{
    use InteractsWithApi;

    public function menu(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'menu.view');
        $branchModel = $this->branchOrFail($request, $branch);

        $categories = MenuCategory::query()
            ->where('branch_id', $branchModel->id)
            ->active()
            ->ordered()
            ->with(['items' => fn ($query) => $query->available()->with(['variants', 'addOns'])])
            ->get();

        return $this->success($categories);
    }

    public function orders(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'orders.view');
        $branchModel = $this->branchOrFail($request, $branch);

        $orders = Order::query()
            ->where('branch_id', $branchModel->id)
            ->when($request->input('filter.status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('filter.date_from'), fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->latest()
            ->paginate(min(100, (int) $request->input('per_page', 25)));

        return $this->success([
            'orders' => collect($orders->items())->map(fn (Order $order) => $this->orderPayload($order))->all(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function storeOrder(Request $request, string $branch, CreateOrderAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'orders.create');
        $branchModel = $this->branchOrFail($request, $branch);

        return $this->rememberIdempotent($request, 'staff-order', function () use ($request, $user, $branchModel, $action): JsonResponse {
            $validated = $request->validate([
                'order_type' => ['required', 'in:dine_in,takeaway,delivery'],
                'restaurant_table_id' => ['nullable', 'integer'],
                'customer_mobile' => ['nullable', 'string'],
                'note' => ['nullable', 'string', 'max:500'],
                'terminal' => ['nullable', 'string', 'max:20'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.menu_item_id' => ['required', 'integer'],
                'items.*.variant_id' => ['nullable', 'integer'],
                'items.*.quantity' => ['required', 'integer', 'min:1'],
                'discount' => ['nullable', 'array'],
                'discount.type' => ['required_with:discount', 'in:percent,fixed'],
                'discount.value' => ['required_with:discount', 'numeric', 'min:0'],
            ]);

            $shift = Shift::query()
                ->where('branch_id', $branchModel->id)
                ->where('user_id', $user->id)
                ->whereNull('closed_at')
                ->first();

            $customer = null;
            $mobile = (string) ($validated['customer_mobile'] ?? '');

            if ($mobile !== '') {
                $customer = Customer::query()->firstOrCreate(
                    ['mobile' => normalize_bd_mobile($mobile)],
                    ['name' => __('orders.walk_in_customer')],
                );
            }

            $order = $action->execute(
                branch: $branchModel,
                type: OrderType::from($validated['order_type']),
                lines: $validated['items'],
                cashier: $user,
                waiter: $user,
                tableId: $validated['restaurant_table_id'] ?? null,
                customer: $customer,
                discount: $validated['discount'] ?? null,
                note: $validated['note'] ?? null,
                channel: OrderChannel::Pos,
                shift: $shift,
                terminalCode: $validated['terminal'] ?? 'T01',
            );

            broadcast(new OrderCreated($branchModel->id, [
                'order_id' => $order->id,
                'invoice_no' => $order->invoice_no,
            ]))->toOthers();

            return $this->success($this->orderPayload($order), __('orders.created', ['invoice' => $order->invoice_no]), 201);
        });
    }

    public function showOrder(Request $request, string $branch, Order $order): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'orders.view');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($order->branch_id === $branchModel->id, 404);

        return $this->success($this->orderPayload($order));
    }

    public function updateStatus(Request $request, string $branch, Order $order): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'orders.create');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($order->branch_id === $branchModel->id, 404);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,preparing,ready,served'],
        ]);

        $next = OrderStatus::from($validated['status']);
        abort_unless($order->status->canTransitionTo($next), 422, __('orders.invalid_status'));
        $order->update(['status' => $next]);

        return $this->success($this->orderPayload($order->fresh()));
    }

    public function pay(Request $request, string $branch, Order $order, PayOrderAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'orders.create');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($order->branch_id === $branchModel->id, 404);

        return $this->rememberIdempotent($request, 'staff-payment', function () use ($request, $order, $action, $user): JsonResponse {
            $validated = $request->validate([
                'tendered' => ['required_without:lines', 'numeric', 'min:0'],
                'lines' => ['required_without:tendered', 'array', 'min:1'],
                'lines.*.method' => ['required_with:lines', 'string'],
                'lines.*.amount' => ['required_with:lines', 'numeric', 'gt:0'],
            ]);

            if (isset($validated['lines'])) {
                $action->executeSplit($order, $validated['lines'], $user);
            } else {
                $action->executeCash($order, (float) $validated['tendered'], $user);
            }

            return $this->success($this->orderPayload($order->fresh()), __('orders.paid_success'));
        });
    }

    public function requestVoid(Request $request, string $branch, Order $order, RequestOrderVoidAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'orders.create');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($order->branch_id === $branchModel->id, 404);

        $validated = $request->validate([
            'reason_code' => ['required', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $void = $action->execute($order, $user, $validated['reason_code'], $validated['note'] ?? null);

        return $this->success(['id' => $void->id, 'status' => $void->status], __('orders.void_requested'), 201);
    }

    public function approveVoid(Request $request, VoidRequest $voidRequest, ApproveOrderVoidAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'orders.void');
        abort_unless($user->canAccessBranch($voidRequest->order->branch_id), 404);

        $order = $action->execute($voidRequest, $user);

        return $this->success($this->orderPayload($order));
    }

    public function stock(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'inventory.view');
        $branchModel = $this->branchOrFail($request, $branch);

        $rows = IngredientStock::query()
            ->where('branch_id', $branchModel->id)
            ->with('ingredient:id,name,unit_id')
            ->get()
            ->map(fn (IngredientStock $stock) => [
                'ingredient_id' => $stock->ingredient_id,
                'name' => $stock->ingredient?->name,
                'stock_qty' => (string) $stock->stock_qty,
                'avg_cost' => (string) $stock->avg_cost,
            ]);

        return $this->success($rows);
    }

    public function wastage(Request $request, string $branch, LogWastageAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'wastage.log');
        $branchModel = $this->branchOrFail($request, $branch);

        $validated = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'reason_code' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $wastage = $action->execute(
            branch: $branchModel,
            ingredient: Ingredient::query()->findOrFail($validated['ingredient_id']),
            unit: Unit::query()->findOrFail($validated['unit_id']),
            qty: (float) $validated['qty'],
            reasonCode: $validated['reason_code'],
            note: $validated['note'] ?? null,
            user: $user,
        );

        return $this->success(['id' => $wastage->id, 'cost' => (string) $wastage->cost], null, 201);
    }

    public function recipes(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'recipes.manage');

        return $this->success(Recipe::query()->with('items')->latest()->limit(100)->get());
    }

    public function storeRecipe(Request $request, SaveRecipeAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'recipes.manage');

        $validated = $request->validate([
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'note' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.ingredient_id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
        ]);

        $recipe = $action->execute(
            MenuItem::query()->findOrFail($validated['menu_item_id']),
            $validated['lines'],
            $validated['note'] ?? null,
            $user,
        );

        return $this->success(['id' => $recipe->id], null, 201);
    }

    public function updateRecipe(Request $request, Recipe $recipe, SaveRecipeAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'recipes.manage');

        $validated = $request->validate([
            'note' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.ingredient_id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
        ]);

        $item = $recipe->menuItem;
        abort_if($item === null, 404);

        $recipe = $action->execute($item, $validated['lines'], $validated['note'] ?? null, $user);

        return $this->success(['id' => $recipe->id]);
    }

    public function recipeCost(Request $request, Recipe $recipe, CostingService $costing): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'recipes.manage');
        $item = $recipe->menuItem;
        abort_if($item === null, 404);
        $branchId = $user->branch_id ?? (int) $request->input('branch_id', $item->branch_id);

        return $this->success($costing->recipeCost($item, $branchId));
    }

    public function purchases(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'purchases.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        return $this->success(
            Purchase::query()->where('branch_id', $branchModel->id)->with('items')->latest('purchased_at')->limit(100)->get()
        );
    }

    public function storePurchase(Request $request, string $branch, ReceivePurchaseAction $receive): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'purchases.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'invoice_no' => ['nullable', 'string', 'max:80'],
            'purchased_at' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'receive' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'integer'],
            'items.*.unit_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $purchase = DB::transaction(function () use ($validated, $branchModel, $user): Purchase {
            $purchase = Purchase::query()->create([
                'branch_id' => $branchModel->id,
                'supplier_id' => $validated['supplier_id'],
                'invoice_no' => $validated['invoice_no'] ?? null,
                'purchased_at' => $validated['purchased_at'],
                'note' => $validated['note'] ?? null,
                'status' => Purchase::STATUS_DRAFT,
                'user_id' => $user->id,
            ]);

            foreach ($validated['items'] as $line) {
                $purchase->items()->create([
                    'ingredient_id' => (int) $line['ingredient_id'],
                    'unit_id' => (int) $line['unit_id'],
                    'qty' => $line['qty'],
                    'unit_cost' => $line['unit_cost'],
                    'line_total' => round((float) $line['qty'] * (float) $line['unit_cost'], 2),
                ]);
            }

            return $purchase;
        });

        if ($request->boolean('receive')) {
            $receive->execute($purchase, $user);
        }

        return $this->success(['id' => $purchase->id], null, 201);
    }

    public function storeTransfer(Request $request, ApproveStockTransferAction $approve, ReceiveStockTransferAction $receive): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'transfers.manage');

        $validated = $request->validate([
            'from_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'to_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'integer'],
            'items.*.unit_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
        ]);

        $from = $user->branch_id ?? (int) ($validated['from_branch_id'] ?? 0);
        abort_if($from === (int) $validated['to_branch_id'], 422, __('inventory.transfer_same_branch'));

        $transfer = DB::transaction(function () use ($validated, $user, $from): StockTransfer {
            $transfer = StockTransfer::query()->create([
                'from_branch_id' => $from,
                'to_branch_id' => $validated['to_branch_id'],
                'status' => StockTransfer::STATUS_REQUESTED,
                'note' => $validated['note'] ?? null,
                'requested_by' => $user->id,
            ]);

            foreach ($validated['items'] as $line) {
                $transfer->items()->create([
                    'ingredient_id' => (int) $line['ingredient_id'],
                    'unit_id' => (int) $line['unit_id'],
                    'qty' => $line['qty'],
                ]);
            }

            return $transfer;
        });

        if ($request->boolean('approve')) {
            $approve->execute($transfer, $user);
        }

        if ($request->boolean('receive')) {
            $receive->execute($transfer->fresh(), $user);
        }

        return $this->success(['id' => $transfer->id], null, 201);
    }

    public function approveTransfer(Request $request, StockTransfer $transfer, ApproveStockTransferAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'transfers.manage');
        abort_unless($user->canAccessBranch($transfer->from_branch_id) || $user->canAccessBranch($transfer->to_branch_id), 404);

        $action->execute($transfer, $user);
        $transfer->refresh();

        return $this->success(['id' => $transfer->id, 'status' => $transfer->status]);
    }

    public function receiveTransfer(Request $request, StockTransfer $transfer, ReceiveStockTransferAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'transfers.manage');
        abort_unless($user->canAccessBranch($transfer->to_branch_id), 404);

        $action->execute($transfer, $user);
        $transfer->refresh();

        return $this->success(['id' => $transfer->id, 'status' => $transfer->status]);
    }

    public function dashboard(Request $request, MetricsService $metrics): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'dashboard.view');
        $branchId = $request->input('branch') === 'all' || $request->input('branch') === null
            ? $user->branch_id
            : (int) $request->input('branch');

        if ($user->branch_id !== null) {
            $branchId = $user->branch_id;
        }

        $from = CarbonImmutable::now()->startOfDay();
        $to = CarbonImmutable::now()->endOfDay();

        return $this->success([
            'summary' => $metrics->summary($branchId, $from, $to),
            'low_stock' => $metrics->lowStock($branchId),
            'wastage_spike' => $metrics->wastageSpike($branchId),
        ]);
    }

    public function report(Request $request, string $type, MetricsService $metrics): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'reports.view');
        $branchId = $user->branch_id;
        $from = CarbonImmutable::parse((string) $request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = CarbonImmutable::parse((string) $request->input('to', now()->toDateString()))->endOfDay();

        $data = match ($type) {
            'sales' => $metrics->salesByDayDetailed($branchId, $from, $to),
            'channel' => $metrics->salesByChannel($branchId, $from, $to),
            'payment-method' => $metrics->salesByPaymentMethod($branchId, $from, $to),
            'cashier' => $metrics->salesByCashier($branchId, $from, $to),
            'discount-void' => $metrics->discountsAndVoids($branchId, $from, $to),
            'delivery' => $metrics->deliveryPerformance($branchId, $from, $to),
            'loyalty' => $metrics->loyaltySummary($branchId, $from, $to),
            'wastage' => $metrics->wastageSpike($branchId),
            'item-sales' => $metrics->topItems($branchId, $from, $to, 50),
            'food-cost' => $metrics->summary($branchId, $from, $to),
            'branch-pl' => $metrics->branchComparison($from, $to),
            'stock-valuation' => IngredientStock::query()
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->with('ingredient:id,name')
                ->orderBy('ingredient_id')
                ->get()
                ->map(fn (IngredientStock $stock): array => [
                    'ingredient' => (string) ($stock->ingredient->name ?? ''),
                    'stock_qty' => (string) $stock->stock_qty,
                    'avg_cost' => (string) $stock->avg_cost,
                    'value' => number_format((float) $stock->stock_qty * (float) $stock->avg_cost, 2, '.', ''),
                ])
                ->all(),
            default => abort(404),
        };

        return $this->success($data);
    }

    public function employees(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'employees.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        return $this->success(Employee::query()->where('branch_id', $branchModel->id)->orderBy('name')->get());
    }

    public function storeEmployee(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'employees.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'designation' => ['required', 'string', 'max:80'],
            'monthly_salary' => ['required_without:pay_rate', 'nullable', 'numeric', 'min:0'],
            'pay_basis' => ['nullable', 'in:monthly,daily,hourly'],
            'pay_rate' => ['nullable', 'numeric', 'min:0'],
            'device_user_id' => ['nullable', 'string', 'max:64'],
            'mobile' => ['nullable', 'string', 'max:20'],
        ]);

        $employee = Employee::query()->create([
            'name' => $validated['name'],
            'designation' => $validated['designation'],
            'mobile' => $validated['mobile'] ?? null,
            'branch_id' => $branchModel->id,
            'is_active' => true,
            ...Employee::payAttributes($validated),
        ]);

        return $this->success($employee, null, 201);
    }

    public function openShift(Request $request, string $branch, OpenShiftAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'shifts.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        $validated = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'terminal' => ['nullable', 'string', 'max:20'],
        ]);

        $shift = $action->execute($branchModel, $user, (float) $validated['opening_cash'], $validated['terminal'] ?? 'T01');

        return $this->success(['id' => $shift->id, 'opening_cash' => (string) $shift->opening_cash], null, 201);
    }

    public function closeShift(Request $request, Shift $shift, CloseShiftAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'shifts.manage');
        abort_unless($shift->user_id === $user->id, 404);

        $validated = $request->validate(['counted_cash' => ['required', 'numeric', 'min:0']]);
        $shift = $action->execute($shift, (float) $validated['counted_cash']);

        return $this->success([
            'id' => $shift->id,
            'expected_cash' => (string) $shift->expected_cash,
            'counted_cash' => (string) $shift->counted_cash,
            'difference' => (string) $shift->difference,
        ]);
    }

    public function deliveries(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'delivery.dispatch');
        $branchModel = $this->branchOrFail($request, $branch);

        $orders = Order::query()
            ->where('branch_id', $branchModel->id)
            ->where('order_type', OrderType::Delivery)
            ->with('delivery')
            ->latest()
            ->limit(50)
            ->get();

        return $this->success($orders->map(fn (Order $order) => $this->orderPayload($order))->all());
    }

    public function assignDelivery(Request $request, Delivery $delivery, AssignRiderAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'delivery.dispatch');
        abort_unless($user->canAccessBranch($delivery->order->branch_id), 404);

        $validated = $request->validate(['rider_id' => ['required', 'integer', 'exists:riders,id']]);
        $delivery = $action->execute($delivery->order, Rider::query()->findOrFail($validated['rider_id']), $user);

        return $this->success(['id' => $delivery->id, 'rider_id' => $delivery->rider_id]);
    }

    public function customers(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'customers.view');
        $mobile = (string) $request->input('filter.mobile', '');

        $customers = Customer::query()
            ->when($mobile !== '', fn ($query) => $query->where('mobile', normalize_bd_mobile($mobile)))
            ->limit(25)
            ->get(['id', 'name', 'mobile']);

        return $this->success($customers);
    }

    public function redeemLoyalty(Request $request, Order $order, LoyaltyService $loyalty): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'loyalty.manage');
        abort_unless($user->canAccessBranch($order->branch_id), 404);

        $validated = $request->validate(['points' => ['required', 'integer', 'min:1']]);
        abort_unless($order->customer !== null, 422);
        $entry = $loyalty->redeem($order->customer, (int) $validated['points'], $order, $user);

        return $this->success(['balance' => $loyalty->balance($order->customer), 'entry_id' => $entry->id]);
    }

    public function adjustLoyalty(Request $request, LoyaltyService $loyalty): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'loyalty.manage');

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'points' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $customer = Customer::query()->findOrFail($validated['customer_id']);
        $loyalty->adjust($customer, (int) $validated['points'], $validated['reason'], $user);

        return $this->success(['balance' => $loyalty->balance($customer)]);
    }

    public function coupons(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'coupons.manage');

        return $this->success(Coupon::query()->latest()->limit(100)->get());
    }

    public function storeCoupon(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'coupons.manage');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:coupons,code'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'gt:0'],
        ]);

        $coupon = Coupon::query()->create([
            ...$validated,
            'is_active' => true,
        ]);

        return $this->success($coupon, null, 201);
    }

    public function updateCoupon(Request $request, Coupon $coupon): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'coupons.manage');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:coupons,code,'.$coupon->id],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'gt:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $coupon->update([
            ...$validated,
            'code' => strtoupper($validated['code']),
        ]);

        return $this->success($coupon->fresh());
    }

    public function validateCoupon(Request $request, CouponService $coupons): JsonResponse
    {
        $this->staffUser($request);

        $validated = $request->validate([
            'code' => ['required', 'string'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        return $this->success($coupons->validate($validated['code'], (float) $validated['subtotal']));
    }

    public function aggregatorChannels(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'aggregators.manage');

        return $this->success(AggregatorChannel::query()->orderBy('name')->get());
    }

    public function updateAggregatorChannel(Request $request, AggregatorChannel $channel): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'aggregators.manage');

        $validated = $request->validate([
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);

        $channel->update($validated);

        return $this->success($channel->fresh());
    }

    public function syncAggregatorMenu(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'aggregators.manage');

        $validated = $request->validate([
            'aggregator_channel_id' => ['required', 'integer', 'exists:aggregator_channels,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $channel = AggregatorChannel::query()->findOrFail($validated['aggregator_channel_id']);
        $branchId = $user->branch_id ?? (isset($validated['branch_id']) ? (int) $validated['branch_id'] : null);

        if ($branchId !== null) {
            abort_unless($user->canAccessBranch($branchId), 404);
        }

        $count = MenuItem::query()
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->where('is_available', true)
            ->count();

        activity()->performedOn($channel)->causedBy($user)->withProperties(['items' => $count])->log('aggregator.menu_synced');

        return $this->success(['items' => $count]);
    }

    public function aggregatorDecision(Request $request, Order $order): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'aggregators.manage');
        abort_unless($user->canAccessBranch($order->branch_id), 404);

        $decision = str_ends_with($request->path(), 'reject') ? 'reject' : 'accept';

        activity()->performedOn($order)->causedBy($user)->withProperties(['decision' => $decision])->log('aggregator.decision');

        return $this->success(['decision' => $decision]);
    }

    public function manualAggregator(Request $request, string $branch, AggregatorOrderService $service): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'aggregators.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        $validated = $request->validate([
            'aggregator_channel_id' => ['required', 'integer', 'exists:aggregator_channels,id'],
            'platform_order_id' => ['required', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $channel = AggregatorChannel::query()->findOrFail($validated['aggregator_channel_id']);
        $order = $service->receive($channel, [...$validated, 'branch_id' => $branchModel->id]);

        return $this->success($this->orderPayload($order), null, 201);
    }
}
