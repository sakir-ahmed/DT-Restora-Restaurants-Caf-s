<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Sales\CollectDueAction;
use App\Actions\Sales\ReviseSettledSaleAction;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateOrderRequest;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\PosSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DueController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('orders.view');

        $branch = $this->currentBranch($request);

        $orders = Order::query()
            ->where('branch_id', $branch->id)
            ->where('is_due', true)
            ->when(! Setting::showsDeletedItems(), fn ($query) => $query->where('status', '!=', OrderStatus::Void->value))
            ->with(['customer:id,name,mobile', 'table:id,name', 'items:id,order_id,name,name_bn,quantity,unit_price,add_ons,add_ons_total,line_total', 'payments:id,order_id,method,amount,status,is_due_collection,created_at'])
            ->orderBy('paid_at')
            ->get()
            ->map(function (Order $order): array {
                $paid = $order->amountPaid();
                $deleted = $order->status === OrderStatus::Void;

                return [
                    'id' => $order->id,
                    'deleted' => $deleted,
                    'invoice_no' => $order->invoice_no,
                    'status' => $order->status->value,
                    'kitchen_status' => $order->kitchen_status?->value,
                    'order_type' => $order->order_type->value,
                    'table_name' => $order->table?->name,
                    'note' => $order->note,
                    'items' => $order->items,
                    'subtotal' => (float) $order->subtotal,
                    'discount_amount' => (float) $order->discount_amount,
                    'service_charge' => (float) $order->service_charge,
                    'extra_charge' => (float) $order->extra_charge,
                    'vat_amount' => (float) $order->vat_amount,
                    'delivery_fee' => (float) $order->delivery_fee,
                    'rounding' => (float) $order->rounding,
                    'payments' => $order->payments,
                    'sold_at' => $order->paid_at?->toIso8601String(),
                    'total' => (float) $order->total,
                    'paid' => $paid,
                    'balance' => $deleted ? 0.0 : max(0.0, round((float) $order->total - $paid, 2)),
                    'customer' => $order->customer?->only(['id', 'name', 'mobile']),
                    'collections' => $order->payments
                        ->where('is_due_collection', true)
                        ->where('status', Payment::STATUS_COMPLETED)
                        ->map(fn (Payment $payment): array => [
                            'method' => $payment->method,
                            'amount' => (float) $payment->amount,
                            'at' => $payment->created_at?->toIso8601String(),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        $methods = collect(PosSettings::for($branch)['payment_methods'])
            ->filter(fn (array $entry): bool => $entry['enabled'] && $entry['type'] !== 'wallet')
            ->values();

        return Inertia::render('admin/dues/Index', [
            'orders' => $orders,
            'methods' => $methods,
            'canCollect' => (bool) $request->user('staff')?->can('orders.create'),
            'canEdit' => PosSettings::orderEditEnabled($branch) && $request->user('staff')?->can('orders.create'),
            'canDelete' => PosSettings::orderDeleteEnabled($branch) && $request->user('staff')?->can('orders.void'),
        ]);
    }

    public function collect(Request $request, Order $order, CollectDueAction $action): RedirectResponse
    {
        $this->authorize('orders.create');

        $user = $request->user('staff');
        abort_unless($user->canAccessBranch($order->branch_id), 404);

        $validated = $request->validate([
            'method' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        if (! in_array($validated['method'], PosSettings::enabledMethods($order->branch), true)) {
            throw ValidationException::withMessages(['method' => __('payments.method_disabled')]);
        }

        $action->execute($order, $validated['method'], (float) $validated['amount'], $validated['reference'] ?? null, $user);

        return back()->with('success', __('orders.due_collected'));
    }

    /**
     * Change the items of a due sale. The POS sends the new lines; who the sale is for, its table
     * and its payments stay as they are.
     */
    public function update(CreateOrderRequest $request, Order $order, ReviseSettledSaleAction $action): RedirectResponse
    {
        $user = $request->user('staff');
        abort_unless($user->canAccessBranch($order->branch_id), 404);

        $discount = $request->has('discount.type')
            ? ['type' => (string) $request->input('discount.type'), 'value' => (float) $request->input('discount.value')]
            : null;

        $order = $action->execute($order, $request->validated('items'), $discount, $request->validated('note'), $user);

        return redirect()->route('admin.dues.index')->with('success', __('orders.due_revised', ['invoice' => $order->invoice_no]));
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
