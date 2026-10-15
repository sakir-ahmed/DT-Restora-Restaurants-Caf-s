<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayOrderAction
{
    /**
     * Complete a cash payment. The tendered amount must cover the total;
     * change is calculated server-side. Split and gateway payments
     * arrive in Phase 4 through the same action.
     */
    public function executeCash(Order $order, float $tendered, ?User $user = null): Payment
    {
        return DB::transaction(function () use ($order, $tendered, $user): Payment {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->isPaid()) {
                throw ValidationException::withMessages([
                    'order' => __('orders.already_paid'),
                ]);
            }

            if ($order->isVoid()) {
                throw ValidationException::withMessages([
                    'order' => __('orders.order_void'),
                ]);
            }

            $total = (float) $order->total;

            if ($tendered < $total) {
                throw ValidationException::withMessages([
                    'tendered' => __('orders.tendered_too_low'),
                ]);
            }

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'method' => PaymentMethod::Cash->value,
                'amount' => $total,
                'tendered' => $tendered,
                'change_due' => round($tendered - $total, 2),
                'status' => Payment::STATUS_COMPLETED,
                'user_id' => $user?->id,
            ]);

            $order->update([
                'status' => OrderStatus::Paid->value,
                'paid_at' => now(),
                'paid_via_payment_id' => $payment->id,
                'cashier_id' => $user->id ?? $order->cashier_id,
            ]);

            if ($order->restaurant_table_id !== null) {
                RestaurantTable::query()
                    ->whereKey($order->restaurant_table_id)
                    ->update(['status' => 'free']);
            }

            activity()
                ->performedOn($order)
                ->causedBy($user)
                ->log('order.paid_cash');

            return $payment;
        });
    }
}
