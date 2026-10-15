<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payments;

use App\Actions\Sales\StartGatewayPaymentAction;
use App\Http\Controllers\Api\Concerns\InteractsWithApi;
use App\Http\Controllers\Api\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\GatewayNotConfiguredException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentApiController extends Controller
{
    use InteractsWithApi;

    public function bkash(Request $request, StartGatewayPaymentAction $action): JsonResponse
    {
        return $this->start($request, $action, 'bkash');
    }

    public function sslcommerz(Request $request, StartGatewayPaymentAction $action): JsonResponse
    {
        return $this->start($request, $action, 'sslcommerz');
    }

    private function start(Request $request, StartGatewayPaymentAction $action, string $gateway): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $order = Order::query()->findOrFail($validated['order_id']);
        $this->authorizeOrder($request, $order);

        return $this->rememberIdempotent($request, 'gateway-'.$gateway, function () use ($request, $order, $action, $gateway): JsonResponse {
            try {
                $started = $action->execute($order, $gateway, $request->header('Idempotency-Key'));
            } catch (GatewayNotConfiguredException) {
                return $this->error('SERVER_ERROR', __('payments.gateway_not_configured', ['gateway' => $gateway]), 503);
            }

            $payload = [
                'payment_id' => $started['payment']->id,
                'redirect_url' => $started['redirect_url'],
            ];

            if ($gateway === 'bkash') {
                $payload['bkashURL'] = $started['redirect_url'];
            }

            return $this->success($payload);
        });
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        $user = $request->user();

        if ($user instanceof User && $user->tokenCan('staff')) {
            $this->permit($user, 'orders.create');
            abort_unless($user->canAccessBranch($order->branch_id), 404);

            return;
        }

        if ($user instanceof Customer && $user->tokenCan('customer')) {
            abort_unless($order->customer_id === $user->id, 404);

            return;
        }

        abort(401, __('api.unauthenticated'));
    }
}
