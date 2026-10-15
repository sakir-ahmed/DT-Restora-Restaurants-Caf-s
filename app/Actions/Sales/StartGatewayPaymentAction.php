<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\BkashService;
use App\Services\Payments\GatewayNotConfiguredException;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\SslcommerzService;
use Illuminate\Validation\ValidationException;

/**
 * Start an online payment. Idempotent: the same Idempotency-Key within
 * 24 hours returns the original payment instead of creating a new one.
 */
class StartGatewayPaymentAction
{
    /**
     * @return array{payment: Payment, redirect_url: string}
     */
    public function execute(Order $order, string $gatewayKey, ?string $idempotencyKey = null): array
    {
        if ($order->isPaid() || $order->isVoid()) {
            throw ValidationException::withMessages([
                'order' => __('orders.already_paid'),
            ]);
        }

        if ($idempotencyKey !== null) {
            $existing = Payment::query()
                ->where('idempotency_key', $idempotencyKey)
                ->where('created_at', '>=', now()->subDay())
                ->first();

            if ($existing !== null) {
                // Same key within 24h: return the original payment.
                return [
                    'payment' => $existing,
                    'redirect_url' => '',
                ];
            }
        }

        $gateway = $this->resolveGateway($gatewayKey);

        if (! $gateway->isConfigured()) {
            throw new GatewayNotConfiguredException(__('payments.gateway_not_configured', ['gateway' => $gatewayKey]));
        }

        $amount = (string) $order->total;

        $result = $gateway->createPayment(
            $order,
            $amount,
            url("/payments/{$gatewayKey}/callback"),
        );

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'method' => $gatewayKey === 'sslcommerz' ? 'card' : $gatewayKey,
            'amount' => $amount,
            'status' => Payment::STATUS_PENDING,
            'gateway' => $gateway->key(),
            'gateway_payment_id' => $result['gateway_payment_id'],
            'idempotency_key' => $idempotencyKey,
            'user_id' => auth('staff')->id(),
        ]);

        return [
            'payment' => $payment,
            'redirect_url' => $result['redirect_url'],
        ];
    }

    private function resolveGateway(string $key): PaymentGateway
    {
        return match ($key) {
            'bkash' => app(BkashService::class),
            'sslcommerz' => app(SslcommerzService::class),
            default => throw new GatewayNotConfiguredException("Unknown gateway: {$key}"),
        };
    }
}
