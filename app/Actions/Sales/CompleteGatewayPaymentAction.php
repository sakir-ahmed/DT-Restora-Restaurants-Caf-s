<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Services\LoyaltyService;
use App\Services\Payments\BkashService;
use App\Services\Payments\SslcommerzService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Complete (or fail) a gateway payment after server-to-server
 * verification. Duplicate callbacks are acknowledged but never
 * double-pay: the payment row is locked and the status guard makes
 * the transition one-shot.
 */
class CompleteGatewayPaymentAction
{
    /**
     * @param  array{status: 'completed'|'failed', message: string, trx_id?: ?string, gateway_payment_id?: ?string}  $verification
     */
    public function execute(Payment $payment, array $verification): Payment
    {
        return DB::transaction(function () use ($payment, $verification): Payment {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            // Duplicate callback: already decided, acknowledge only.
            if ($payment->status === Payment::STATUS_COMPLETED) {
                return $payment;
            }

            if ($verification['status'] === 'completed') {
                $payment->update([
                    'status' => Payment::STATUS_COMPLETED,
                    'gateway_trx_id' => $verification['trx_id'] ?? $payment->gateway_trx_id,
                    'gateway_payment_id' => $verification['gateway_payment_id'] ?? $payment->gateway_payment_id,
                    'verified_at' => now(),
                ]);

                $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

                if (! $order->isPaid() && ! $order->isVoid()) {
                    $order->update([
                        'status' => OrderStatus::Paid->value,
                        'paid_at' => now(),
                        'paid_via_payment_id' => $payment->id,
                    ]);

                    if ($order->restaurant_table_id !== null) {
                        RestaurantTable::query()
                            ->whereKey($order->restaurant_table_id)
                            ->update(['status' => 'free']);
                    }
                }

                app(LoyaltyService::class)->earnForOrder($order);

                activity()->performedOn($order)->log('order.paid_'.$payment->gateway);
            } else {
                $payment->update([
                    'status' => Payment::STATUS_FAILED,
                    'verified_at' => now(),
                ]);
            }

            return $payment;
        });
    }

    /**
     * Verify a bKash payment (execute + query) and complete it.
     */
    public function verifyAndCompleteBkash(Payment $payment): Payment
    {
        if ($payment->gateway !== 'bkash' || $payment->gateway_payment_id === null) {
            throw ValidationException::withMessages(['payment' => __('payments.invalid_payment')]);
        }

        // Already decided: acknowledge without hitting the gateway again.
        if ($payment->status === Payment::STATUS_COMPLETED) {
            return $payment;
        }

        $result = app(BkashService::class)->executeAndVerify(
            $payment->gateway_payment_id,
            (string) $payment->amount,
        );

        $result['trx_id'] ??= null;

        return $this->execute($payment, $result);
    }

    /**
     * Verify an SSLCommerz payment through the validation API.
     */
    public function verifyAndCompleteSslcommerz(Payment $payment, string $valId): Payment
    {
        $order = $payment->order;

        $result = app(SslcommerzService::class)->validateByValId(
            $valId,
            (string) $payment->amount,
            $order->invoice_no,
        );

        return $this->execute($payment, $result);
    }
}
