<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Reservation;
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

            // Booking money already taken off the bill leaves only the balance to collect.
            $total = $order->balanceDue();

            if ($tendered < $total) {
                throw ValidationException::withMessages([
                    'tendered' => __('orders.tendered_too_low'),
                ]);
            }

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'method' => 'cash',
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
     * Pay the whole order with one card or bank POS payment (any method that
     * is neither cash nor a wallet). The amount is always the order total; an
     * optional slip reference is kept.
     */
    public function executeSingle(Order $order, PaymentMethod $method, ?string $reference = null, ?User $user = null): Payment
    {
        if ($method->isCash() || $method->isWallet()) {
            throw ValidationException::withMessages([
                'method' => __('payments.method_disabled'),
            ]);
        }

        return DB::transaction(function () use ($order, $method, $reference, $user): Payment {
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

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'method' => $method->code,
                'amount' => $order->balanceDue(),
                'reference' => $reference,
                'status' => Payment::STATUS_COMPLETED,
                'user_id' => $user?->id,
            ]);

            $order->update([
                'status' => OrderStatus::Paid->value,
                'paid_at' => now(),
                'paid_via_payment_id' => $payment->id,
                'cashier_id' => $user->id ?? $order->cashier_id,
            ]);

            app(AccountingService::class)->postOrderPaid($order->fresh(), $user);
            app(LoyaltyService::class)->earnForOrder($order);

            activity()
                ->performedOn($order)
                ->causedBy($user)
                ->log('order.paid_'.$method->code);

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

            if (abs($sum - $order->balanceDue()) > 0.001) {
                throw ValidationException::withMessages([
                    'split' => __('payments.split_mismatch'),
                ]);
            }

            $payments = [];

            foreach ($lines as $line) {
                $method = PaymentMethod::findByCode((string) $line['method']);

                if ($method === null) {
                    throw ValidationException::withMessages([
                        'split' => __('payments.invalid_payment'),
                    ]);
                }

                $payments[] = Payment::query()->create([
                    'order_id' => $order->id,
                    'method' => $method->code,
                    'amount' => (string) $line['amount'],
                    'reference' => $line['reference'] ?? null,
                    'status' => $method->isWallet()
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

            }

            activity()->performedOn($order)->causedBy($user)->log('order.paid_split');

            return $payments;
        });
    }

    /**
     * Complete the sale with money still to collect. Whatever was handed over is recorded as
     * payments (it may be nothing); the rest stays as a due on the customer, collected later
     * through CollectDueAction. A due needs a customer to collect from, and a bill that is
     * already covered is paid the normal way.
     *
     * @param  array<int, array{method: string, amount: float|string, reference?: ?string}>  $lines
     * @return array<int, Payment>
     */
    public function executeDue(Order $order, array $lines, ?User $user = null): array
    {
        return DB::transaction(function () use ($order, $lines, $user): array {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->isPaid()) {
                throw ValidationException::withMessages(['order' => __('orders.already_paid')]);
            }

            if ($order->isVoid()) {
                throw ValidationException::withMessages(['order' => __('orders.order_void')]);
            }

            if ($order->customer_id === null) {
                throw ValidationException::withMessages(['due' => __('orders.due_needs_customer')]);
            }

            $received = round(array_sum(array_map(fn (array $line): float => (float) $line['amount'], $lines)), 2);

            if ($received >= $order->balanceDue()) {
                throw ValidationException::withMessages(['due' => __('orders.due_fully_paid')]);
            }

            $payments = [];

            foreach ($lines as $line) {
                $method = PaymentMethod::findByCode((string) $line['method']);

                if ($method === null || $method->isWallet() || (float) $line['amount'] <= 0) {
                    throw ValidationException::withMessages(['due' => __('payments.invalid_payment')]);
                }

                $payments[] = Payment::query()->create([
                    'order_id' => $order->id,
                    'method' => $method->code,
                    'amount' => round((float) $line['amount'], 2),
                    'tendered' => $method->isCash() ? round((float) $line['amount'], 2) : null,
                    'change_due' => $method->isCash() ? 0 : null,
                    'reference' => $line['reference'] ?? null,
                    'status' => Payment::STATUS_COMPLETED,
                    'user_id' => $user?->id,
                ]);
            }

            $order->update([
                'status' => OrderStatus::Paid->value,
                'is_due' => true,
                'paid_at' => now(),
                'paid_via_payment_id' => $payments[0]->id ?? null,
                'cashier_id' => $user->id ?? $order->cashier_id,
            ]);

            app(AccountingService::class)->postOrderPaid($order->fresh(), $user);

            activity()
                ->performedOn($order)
                ->causedBy($user)
                ->withProperties(['due' => $order->balanceDue()])
                ->log('order.paid_due');

            return $payments;
        });
    }

    /**
     * Take a booking's held money off this order's bill. The deposit becomes a payment on the
     * order (kept apart from the payments that close it). If it covers the whole bill, the order
     * is paid; otherwise only the balance is left to collect.
     */
    public function applyAdvance(Order $order, Reservation $reservation, ?User $user = null): ?Payment
    {
        return DB::transaction(function () use ($order, $reservation, $user): ?Payment {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $reservation = Reservation::query()->withoutBranchScope()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($reservation->deposit_status !== Reservation::DEPOSIT_HELD || $order->isPaid() || $order->isVoid()) {
                return null;
            }

            $amount = min($reservation->depositBalance(), $order->balanceDue());

            if ($amount <= 0) {
                return null;
            }

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'method' => (string) $reservation->deposit_method,
                'amount' => (string) $amount,
                'reference' => $reservation->booking_no.($reservation->deposit_reference ? ' / '.$reservation->deposit_reference : ''),
                'status' => Payment::STATUS_COMPLETED,
                'is_advance' => true,
                'user_id' => $user?->id,
            ]);

            $applied = round((float) $reservation->deposit_applied + $amount, 2);

            $reservation->update([
                'deposit_applied' => $applied,
                'deposit_order_id' => $order->id,
                'deposit_status' => $applied >= (float) $reservation->deposit_amount ? Reservation::DEPOSIT_APPLIED : Reservation::DEPOSIT_HELD,
            ]);

            if ($order->balanceDue() <= 0.001) {
                $order->update([
                    'status' => OrderStatus::Paid->value,
                    'paid_at' => now(),
                    'paid_via_payment_id' => $payment->id,
                ]);

                app(AccountingService::class)->postOrderPaid($order->fresh(), $user);
                app(LoyaltyService::class)->earnForOrder($order);
            }

            activity()->performedOn($order)->causedBy($user)->log('order.advance_applied');

            return $payment;
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
                'method' => $method->code,
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

            }

            activity()->performedOn($payment)->causedBy($manager)->log('payment.manual_approved');

            return $payment;
        });
    }
}
