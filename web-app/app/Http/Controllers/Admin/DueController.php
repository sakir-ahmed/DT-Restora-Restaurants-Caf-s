<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Sales\CollectDueAction;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
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
            ->with(['customer:id,name,mobile', 'payments:id,order_id,method,amount,status,is_due_collection,created_at'])
            ->orderBy('paid_at')
            ->get()
            ->map(function (Order $order): array {
                $paid = $order->amountPaid();

                return [
                    'id' => $order->id,
                    'invoice_no' => $order->invoice_no,
                    'sold_at' => $order->paid_at?->toIso8601String(),
                    'total' => (float) $order->total,
                    'paid' => $paid,
                    'balance' => max(0.0, round((float) $order->total - $paid, 2)),
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

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
