<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Sales\StartGatewayPaymentAction;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\GatewayNotConfiguredException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WebPaymentController extends Controller
{
    public function start(Request $request, Order $order, string $gateway, StartGatewayPaymentAction $action): RedirectResponse
    {
        $customer = $request->user('customer');

        // Customers can only pay for their own orders.
        abort_unless($customer !== null && $customer->id === $order->customer_id, 404);

        abort_unless(in_array($gateway, ['bkash', 'sslcommerz'], true), 404);

        try {
            $result = $action->execute($order, $gateway);
        } catch (GatewayNotConfiguredException) {
            return redirect()->route('web.track', ['token' => $order->tracking_token])
                ->with('error', __('payments.gateway_not_configured', ['gateway' => $gateway]));
        }

        return redirect()->away($result['redirect_url']);
    }
}
