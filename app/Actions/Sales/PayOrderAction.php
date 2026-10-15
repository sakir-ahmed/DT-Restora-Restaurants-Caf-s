<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\LoyaltyService;
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

            app(AccountingService::class)->postOrderPaid($order->fresh(), $user);
            app(LoyaltyService::class)->earnForOrder($order);

            activity()
                ->performedOn($order)
                ->causedBy($user)
                ->log('order.paid_cash');

            return $payment;
        });
    }

    /**
     * Split payment across two or more methods. The lines must sum to
     * the grand total exactly; a mismatch is rejected.
     *
     * @param  array<int, array{method: string, amount: float|string, reference?: ?string}>  $lines
     * @return array<int, Payment>
     */
    public function executeSplit(Order $order, array $lines, ?User $user = null): array
    {
        return DB::transaction(function () use ($order, $lines, $user): array {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->isPaid()) {
                throw ValidationException::withMessages([
                    'order' => __('orders.already_paid'),
                ]);
            }

            if (count($lines) < 2) {
                throw ValidationException::withMessages([
                    'split' => __('payments.split_needs_two'),
                ]);
            }

            $sum = round(array_sum(array_map(fn (array $line) => (float) $line['amount'], $lines)), 2);

            if (abs($sum - (float) $order->total) > 0.001) {
                throw ValidationException::withMessages([
                    'split' => __('payments.split_mismatch'),
                ]);
            }

            $payments = [];

            foreach ($lines as $line) {
                $method = PaymentMethod::from($line['method']);

                $payments[] = Payment::query()->create([
                    'order_id' => $order->id,
                    'method' => $method->value,
                    'amount' => (string) $line['amount'],
                    'reference' => $line['reference'] ?? null,
                    'status' => $method === PaymentMethod::Bkash || $method === PaymentMethod::Nagad
                        ? Payment::STATUS_PENDING_VERIFICATION
                        : Payment::STATUS_COMPLETED,
                    'user_id' => $user->id ?? $order->cashier_id,
                ]);
            }

            $allCompleted = collect($payments)->every(fn (Payment $payment) => $payment->status === Payment::STATUS_COMPLETED);

            if ($allCompleted) {
                $order->update([
                    'status' => OrderStatus::Paid->value,
                    'paid_at' => now(),
                    'paid_via_payment_id' => $payments[0]->id,
                ]);

                if ($order->restaurant_table_id !== null) {
                    RestaurantTable::query()
                        ->whereKey($order->restaurant_table_id)
                        ->update(['status' => 'free']);
                }
            }

            activity()->performedOn($order)->causedBy($user)->log('order.paid_split');

            return $payments;
        });
    }

    /**
     * Record a manual mobile-wallet payment (TrxID typed by the
     * cashier) for later manager verification.
     */
    public function recordManualWallet(Order $order, PaymentMethod $method, float $amount, string $trxId, ?User $user = null): Payment
    {
        return DB::transaction(function () use ($order, $method, $amount, $trxId, $user): Payment {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->isPaid() || $order->isVoid()) {
                throw ValidationException::withMessages([
                    'order' => __('orders.already_paid'),
                ]);
            }

            return Payment::query()->create([
                'order_id' => $order->id,
                'method' => $method->value,
                'amount' => (string) $amount,
                'reference' => $trxId,
                'status' => Payment::STATUS_PENDING_VERIFICATION,
                'user_id' => $user->id ?? $order->cashier_id,
            ]);
        });
    }

    /**
     * Manager approves a manually recorded wallet payment.
     */
    public function approveManual(Payment $payment, User $manager): Payment
    {
        return DB::transaction(function () use ($payment, $manager): Payment {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== Payment::STATUS_PENDING_VERIFICATION) {
                throw ValidationException::withMessages([
                    'payment' => __('payments.already_verified'),
                ]);
            }

            $payment->update([
                'status' => Payment::STATUS_COMPLETED,
                'verified_at' => now(),
            ]);

            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

            $paidSum = Payment::query()
                ->where('order_id', $order->id)
                ->where('status', Payment::STATUS_COMPLETED)
                ->sum('amount');

            if (! $order->isPaid() && abs((float) $paidSum - (float) $order->total) < 0.001) {
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

            activity()->performedOn($payment)->causedBy($manager)->log('payment.manual_approved');

            return $payment;
        });
    }
}
