<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Branch;
use App\Models\Order;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\LoyaltyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviseSettledSaleAction
{
    public function __construct(private readonly CreateOrderAction $createOrder) {}

    /**
     * Change the items, discount or note of a sale that is already paid or was completed on due.
     * A coupon stays if it still fits the new bill, and a platform order's commission is worked out again.
     *
     * The sale keeps its invoice, customer, table, type and every payment already taken. Its
     * stock is given back and taken again for the new items, its sales entry is reversed and
     * posted again, and loyalty points are re-earned on the new figures. If the new total is
     * more than what was received, the difference becomes a due (the sale needs a customer);
     * if it is the same, the sale stays paid. A total below what was received is refused,
     * because that needs a refund, which is a separate step.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array{type: string, value: float}|null  $discount
     */
    public function execute(Order $order, array $lines, ?array $discount, ?string $note, ?User $user = null): Order
    {
        return DB::transaction(function () use ($order, $lines, $discount, $note, $user): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== OrderStatus::Paid) {
                throw ValidationException::withMessages(['order' => __('orders.invalid_status')]);
            }

            $accounting = app(AccountingService::class);
            $loyalty = app(LoyaltyService::class);

            $accounting->postOrderVoided($order, $user);
            $loyalty->takeBackEarned($order);

            $order = $this->createOrder->execute(
                branch: Branch::query()->findOrFail($order->branch_id),
                type: $order->order_type instanceof OrderType ? $order->order_type : OrderType::from((string) $order->order_type),
                lines: $lines,
                cashier: $user,
                tableId: $order->restaurant_table_id,
                customer: $order->customer,
                discount: $discount,
                note: $note,
                deliveryFee: (float) $order->delivery_fee,
                deliveryZoneId: $order->delivery_zone_id,
                deliveryAddress: $order->delivery_address,
                revising: $order,
                revisingSettled: true,
            );

            $received = $order->amountPaid();
            $owed = round((float) $order->total - $received, 2);

            if ($owed < 0) {
                throw ValidationException::withMessages(['items' => __('orders.due_edit_below_paid', ['paid' => number_format($received, 2)])]);
            }

            if ($owed > 0 && $order->customer_id === null) {
                throw ValidationException::withMessages(['items' => __('orders.due_needs_customer')]);
            }

            $order->update(['is_due' => $owed > 0]);

            // A delivery platform's cut follows the new bill.
            if ($order->aggregator_channel_id !== null && (float) $order->commission_amount > 0) {
                $order->update(['commission_amount' => $order->aggregatorChannel->commissionFor((float) $order->total)]);
            }

            $accounting->postOrderPaid($order->fresh(), $user);

            if ($owed <= 0) {
                $loyalty->earnForOrder($order->fresh());
            }

            activity()->performedOn($order)->causedBy($user)->log('order.revised_after_payment');

            return $order;
        });
    }
}
