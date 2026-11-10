<?php

declare(strict_types=1);

namespace App\Services\Aggregators;

use App\Actions\Sales\CreateOrderAction;
use App\Enums\OrderChannel;
use App\Enums\OrderType;
use App\Events\OrderCreated;
use App\Models\AggregatorChannel;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shared intake logic for every aggregator channel: normalized
 * payloads from webhooks, polling or the manual entry screen all
 * create orders through this one service, which wraps the same
 * CreateOrderAction used everywhere else.
 */
class AggregatorOrderService
{
    public function __construct(private readonly CreateOrderAction $orders) {}

    /**
     * @param  array{platform_order_id: string, branch_id: int, customer_name?: ?string, customer_mobile?: ?string, address?: ?string, items: array<int, array{menu_item_id: int, quantity: int}>, note?: ?string}  $payload
     */
    public function receive(AggregatorChannel $channel, array $payload): Order
    {
        // Duplicate webhooks create exactly one order.
        $existing = Order::query()
            ->where('aggregator_channel_id', $channel->id)
            ->where('aggregator_order_id', (string) $payload['platform_order_id'])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $order = DB::transaction(function () use ($channel, $payload): Order {
            $customer = null;

            if (! empty($payload['customer_mobile'])) {
                $customer = Customer::query()->firstOrCreate(
                    ['mobile' => normalize_bd_mobile((string) $payload['customer_mobile'])],
                    ['name' => $payload['customer_name'] ?? __('web.customer_default_name')],
                );
            }

            try {
                $order = $this->orders->execute(
                    branch: Branch::query()->findOrFail((int) $payload['branch_id']),
                    type: OrderType::Delivery,
                    lines: $payload['items'],
                    customer: $customer,
                    channel: OrderChannel::Aggregator,
                    terminalCode: strtoupper(substr($channel->key, 0, 6)),
                    deliveryAddress: $payload['address'] ?? null,
                );
            } catch (ValidationException $exception) {
                throw $exception;
            }

            $commission = $channel->commissionFor((float) $order->total);

            $order->forceFill([
                'aggregator_channel_id' => $channel->id,
                'aggregator_order_id' => (string) $payload['platform_order_id'],
                'commission_amount' => $commission,
                'note' => $payload['note'] ?? $order->note,
            ])->save();

            return $order->fresh();
        });

        $this->announce($channel, $order);

        return $order;
    }

    /**
     * Tell the kitchen and the POS terminals that a platform order arrived.
     */
    private function announce(AggregatorChannel $channel, Order $order): void
    {
        try {
            broadcast(new OrderCreated($order->branch_id, [
                'order_id' => $order->id,
                'invoice_no' => $order->invoice_no,
                'order_type' => $order->order_type->value,
                'channel' => 'aggregator',
                'aggregator' => $channel->name,
                'items' => $order->items->map(fn ($item): array => ['name' => $item->name, 'quantity' => $item->quantity])->all(),
            ]));
        } catch (\Throwable) {
            // A broadcast outage must not reject the platform order.
        }
    }
}
