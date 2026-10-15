<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Sales\PayOrderAction;
use App\Actions\Sales\StartGatewayPaymentAction;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\GatewayNotConfiguredException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function startGateway(Request $request, Order $order, StartGatewayPaymentAction $action): RedirectResponse
    {
        $this->authorize('orders.create');

        abort_unless($request->user('staff')->canAccessBranch($order->branch_id), 404);

        $validated = $request->validate([
            'gateway' => ['required', 'in:bkash,sslcommerz'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $result = $action->execute($order, $validated['gateway'], $validated['idempotency_key'] ?? null);
        } catch (GatewayNotConfiguredException) {
            return back()->with('error', __('payments.gateway_not_configured', ['gateway' => $validated['gateway']]));
        }

        if ($result['redirect_url'] === '') {
            return back()->with('success', __('payments.already_paid_online'));
        }

        return back()->with('gateway_redirect', $result['redirect_url']);
    }

    public function split(Request $request, Order $order, PayOrderAction $action): RedirectResponse
    {
        $this->authorize('orders.create');

        abort_unless($request->user('staff')->canAccessBranch($order->branch_id), 404);

        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.method' => ['required', 'in:cash,bkash,nagad,card'],
            'lines.*.amount' => ['required', 'numeric', 'gt:0'],
            'lines.*.reference' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $action->executeSplit($order, $validated['lines'], $request->user('staff'));
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('payments.split_completed'));
    }

    public function recordManual(Request $request, Order $order, PayOrderAction $action): RedirectResponse
    {
        $this->authorize('orders.create');

        abort_unless($request->user('staff')->canAccessBranch($order->branch_id), 404);

        $validated = $request->validate([
            'method' => ['required', 'in:bkash,nagad'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['required', 'string', 'max:100'],
        ]);

        $action->recordManualWallet(
            $order,
            PaymentMethod::from($validated['method']),
            (float) $validated['amount'],
            $validated['reference'],
            $request->user('staff'),
        );

        return back()->with('success', __('payments.manual_recorded'));
    }

    public function approveManual(Request $request, Payment $payment, PayOrderAction $action): RedirectResponse
    {
        $this->authorize('payments.manage');

        abort_unless($request->user('staff')->canAccessBranch($payment->order->branch_id), 404);

        $action->approveManual($payment, $request->user('staff'));

        return back()->with('success', __('payments.manual_approved'));
    }
}
