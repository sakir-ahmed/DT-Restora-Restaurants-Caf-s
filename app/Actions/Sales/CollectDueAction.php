<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\LoyaltyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CollectDueAction
{
    /**
     * Take a payment against a due sale. It may be part of what is owed; the order stays on the
     * dues list until the balance reaches zero.
     */
    public function execute(Order $order, string $methodCode, float $amount, ?string $reference = null, ?User $user = null): Payment
    {
        return DB::transaction(function () use ($order, $methodCode, $amount, $reference, $user): Payment {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $order->is_due || $order->isVoid()) {
                throw ValidationException::withMessages(['amount' => __('orders.due_not_open')]);
            }

            $method = PaymentMethod::findByCode($methodCode);

            if ($method === null || $method->isWallet()) {
                throw ValidationException::withMessages(['method' => __('payments.invalid_payment')]);
            }

            $amount = round($amount, 2);

            if ($amount <= 0 || $amount > $order->balanceDue()) {
                throw ValidationException::withMessages(['amount' => __('orders.due_amount_invalid')]);
            }

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'method' => $method->code,
                'amount' => $amount,
                'tendered' => $method->isCash() ? $amount : null,
                'change_due' => $method->isCash() ? 0 : null,
                'reference' => $reference,
                'status' => Payment::STATUS_COMPLETED,
                'is_due_collection' => true,
                'user_id' => $user?->id,
            ]);

            app(AccountingService::class)->postDueCollection($payment, $user);

            if ($order->balanceDue() <= 0) {
                $order->update(['is_due' => false]);
                app(LoyaltyService::class)->earnForOrder($order);
            }

            activity()
                ->performedOn($order)
                ->causedBy($user)
                ->withProperties(['amount' => $amount, 'method' => $method->code])
                ->log('order.due_collected');

            return $payment;
        });
    }
}
