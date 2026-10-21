<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Sales\ApproveOrderVoidAction;
use App\Actions\Sales\CreateOrderAction;
use App\Actions\Sales\PayOrderAction;
use App\Actions\Sales\RequestOrderVoidAction;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateOrderRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shift;
use App\Services\Aggregators\Foodpanda\FoodpandaAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('orders.view');

        $branch = $this->currentBranch($request);

        $orders = Order::query()
            ->where('branch_id', $branch->id)
            ->with(['table:id,name', 'waiter:id,name', 'cashier:id,name', 'items:id,order_id,name,quantity,line_total', 'voidRequest', 'payments'])
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->when($request->string('search')->toString(), function ($query, $search): void {
                $query->where('invoice_no', 'like', "%{$search}%");
            })
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('admin/orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['status', 'search']),
            'canVoid' => $request->user('staff')?->can('orders.void'),
            'canApproveVoid' => $request->user('staff')?->can('orders.void'),
            'canApprovePayments' => $request->user('staff')?->can('payments.manage'),
        ]);
    }

    public function store(CreateOrderRequest $request, CreateOrderAction $action): RedirectResponse
    {
        $branch = $this->currentBranch($request);
        $user = $request->user('staff');

        $openShift = Shift::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $user->id)
            ->whereNull('closed_at')
            ->first();

        $customer = null;

        $mobile = $request->string('customer_mobile')->toString();

        if ($mobile !== '') {
            $customer = Customer::query()->firstOrCreate(
                ['mobile' => normalize_bd_mobile($mobile)],
                ['name' => __('orders.walk_in_customer')],
            );
        }

        $discount = null;

        if ($request->has('discount.type')) {
            $discount = [
                'type' => (string) $request->input('discount.type'),
                'value' => (float) $request->input('discount.value'),
            ];
        }

        $order = $action->execute(
            branch: $branch,
            type: OrderType::from((string) $request->validated('order_type')),
            lines: $request->validated('items'),
            cashier: $user,
            waiter: $user,
            tableId: $request->validated('restaurant_table_id'),
            customer: $customer,
            discount: $discount,
            note: $request->validated('note'),
            channel: OrderChannel::Pos,
            clientId: $request->validated('client_id'),
            shift: $openShift,
            terminalCode: $request->input('terminal', 'T01'),
        );

        broadcast(new OrderCreated($branch->id, [
            'order_id' => $order->id,
            'invoice_no' => $order->invoice_no,
            'order_type' => $order->order_type->value,
            'table' => $order->table?->name,
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->name,
                'quantity' => $item->quantity,
            ])->all(),
        ]))->toOthers();

        return back()->with('success', __('orders.created', ['invoice' => $order->invoice_no]))->with('order', [
            'id' => $order->id,
            'invoice_no' => $order->invoice_no,
            'total' => $order->total,
        ]);
    }

    /**
     * Change the items, table or discount of an order that is not paid yet.
     */
    public function update(CreateOrderRequest $request, Order $order, CreateOrderAction $action): RedirectResponse
    {
        $user = $request->user('staff');
        abort_unless($user->canAccessBranch($order->branch_id), 404);

        $customer = null;
        $mobile = $request->string('customer_mobile')->toString();

        if ($mobile !== '') {
            $customer = Customer::query()->firstOrCreate(
                ['mobile' => normalize_bd_mobile($mobile)],
                ['name' => __('orders.walk_in_customer')],
            );
        }

        $discount = $request->has('discount.type')
            ? ['type' => (string) $request->input('discount.type'), 'value' => (float) $request->input('discount.value')]
            : null;

        $order = $action->execute(
            branch: Branch::query()->findOrFail($order->branch_id),
            type: OrderType::from((string) $request->validated('order_type')),
            lines: $request->validated('items'),
            cashier: $user,
            tableId: $request->validated('restaurant_table_id'),
            customer: $customer,
            discount: $discount,
            note: $request->validated('note'),
            revising: $order,
        );

        activity()->performedOn($order)->causedBy($user)->log('order.revised');

        $this->pushToPlatform($order, null, cart: true);

        broadcast(new OrderCreated($order->branch_id, [
            'order_id' => $order->id,
            'invoice_no' => $order->invoice_no,
            'order_type' => $order->order_type->value,
            'table' => $order->table?->name,
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->name,
                'quantity' => $item->quantity,
            ])->all(),
        ]))->toOthers();

        return back()->with('success', __('orders.revised', ['invoice' => $order->invoice_no]))->with('order', [
            'id' => $order->id,
            'invoice_no' => $order->invoice_no,
            'total' => $order->total,
        ]);
    }

    public function pay(Request $request, Order $order, PayOrderAction $action): RedirectResponse
    {
        $this->authorize('orders.create');

        $user = $request->user('staff');
        abort_unless($user->canAccessBranch($order->branch_id), 404);

        $validated = $request->validate([
            'tendered' => ['required', 'numeric', 'min:0'],
        ]);

        $payment = $action->executeCash($order, (float) $validated['tendered'], $user);

        return back()->with('success', __('orders.paid', ['change' => $payment->change_due]))->with('payment', [
            'order_id' => $order->id,
            'invoice_no' => $order->invoice_no,
            'change_due' => $payment->change_due,
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('orders.create');

        abort_unless($request->user('staff')->canAccessBranch($order->branch_id), 404);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,preparing,ready,served,paid'],
        ]);

        $next = OrderStatus::from($validated['status']);
        $current = $order->status;

        if ($next !== OrderStatus::Paid && ! $current->canTransitionTo($next)) {
            return back()->with('error', __('orders.invalid_status'));
        }

        $order->update(['status' => $next->value]);

        $this->pushToPlatform($order, $next === OrderStatus::Ready ? 'ready' : null);

        broadcast(new OrderStatusChanged($order->branch_id, [
            'order_id' => $order->id,
            'invoice_no' => $order->invoice_no,
            'status' => $next->value,
        ]));

        return back()->with('success', __('orders.status_updated'));
    }

    /**
     * Tell a delivery platform about a kitchen status or an edited cart.
     * A failure never blocks the kitchen: it is kept on the channel.
     */
    private function pushToPlatform(Order $order, ?string $status, bool $cart = false): void
    {
        $channel = $order->aggregatorChannel;

        if ($channel === null || ! $channel->isFoodpanda() || ! $channel->hasApiCredentials()) {
            return;
        }

        $adapter = app(FoodpandaAdapter::class, ['channel' => $channel]);

        if ($cart) {
            $adapter->pushCart($order);
        } elseif ($status !== null) {
            $adapter->pushStatus($order, $status);
        }
    }

    public function requestVoid(Request $request, Order $order, RequestOrderVoidAction $action): RedirectResponse
    {
        $this->authorize('orders.create');

        $user = $request->user('staff');
        abort_unless($user->canAccessBranch($order->branch_id), 404);

        $validated = $request->validate([
            'reason_code' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $action->execute($order, $user, (string) $validated['reason_code'], $validated['note'] ?? null);

        return back()->with('success', __('orders.void_requested'));
    }

    public function approveVoid(Request $request, Order $order, ApproveOrderVoidAction $action): RedirectResponse
    {
        $this->authorize('orders.void');

        abort_unless($request->user('staff')->canAccessBranch($order->branch_id), 404);

        $pending = $order->voidRequest()->where('status', 'pending')->firstOrFail();

        $action->execute($pending, $request->user('staff'));

        return back()->with('success', __('orders.voided'));
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
