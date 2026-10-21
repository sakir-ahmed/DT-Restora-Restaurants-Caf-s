<?php

declare(strict_types=1);

namespace App\Services\Aggregators\UberEats;

use App\Actions\Sales\ApproveOrderVoidAction;
use App\Enums\OrderStatus;
use App\Models\AggregatorChannel;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\Aggregators\AggregatorChannelContract;
use App\Services\Aggregators\AggregatorOrderService;
use App\Services\Aggregators\Foodpanda\FoodpandaAdapter;
use Illuminate\Support\Arr;
use RuntimeException;
use Throwable;

/**
 * Uber Eats adapter. A new order webhook only carries ids, so the order is
 * fetched, created as a normal internal order and accepted on Uber (it is
 * auto-cancelled after 11.5 minutes otherwise). An order that cannot be
 * created is denied with a reason.
 */
class UberEatsAdapter implements AggregatorChannelContract
{
    public int $lastSyncCount = 0;

    private ?UberEatsClient $client = null;

    public function __construct(
        private readonly AggregatorChannel $channel,
        private readonly AggregatorOrderService $orders,
        private readonly ApproveOrderVoidAction $voids,
    ) {}

    public function channelKey(): string
    {
        return AggregatorChannel::UBEREATS;
    }

    public function client(): UberEatsClient
    {
        return $this->client ??= new UberEatsClient($this->channel);
    }

    /**
     * Handle a webhook body: orders.notification fetches and creates the
     * order, orders.cancel cancels a pending one.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload): ?Order
    {
        $orderId = (string) Arr::get($payload, 'meta.resource_id', '');
        $storeId = (string) Arr::get($payload, 'meta.user_id', '');
        $event = (string) ($payload['event_type'] ?? '');

        if ($orderId === '') {
            return null;
        }

        if (str_contains($event, 'cancel')) {
            $order = Order::query()->where('aggregator_channel_id', $this->channel->id)->where('aggregator_order_id', $orderId)->first();

            if ($order !== null) {
                $this->remember($order, ['platform_status' => 'CANCELED']);

                if ($order->status === OrderStatus::Pending) {
                    $this->voids->cancelPending($order);
                }
            }

            return $order;
        }

        if (! str_contains($event, 'notification')) {
            return null;
        }

        $exists = Order::query()->where('aggregator_channel_id', $this->channel->id)->where('aggregator_order_id', $orderId)->first();

        if ($exists !== null) {
            return $exists;
        }

        return $this->receiveOrder($this->client()->order($orderId) + ['store_hint' => $storeId, 'id' => $orderId]);
    }

    /**
     * @param  array<string, mixed>  $payload  Get Order response
     */
    public function receiveOrder(array $payload): Order
    {
        $orderId = (string) ($payload['id'] ?? '');

        if ($orderId === '') {
            throw new RuntimeException('Uber Eats payload has no order id.');
        }

        $storeId = (string) (Arr::get($payload, 'store.id') ?? $payload['store_hint'] ?? '');
        $branchId = $this->channel->branchIdForVendor($storeId) ?? $this->channel->branchIdForVendor((string) ($payload['store_hint'] ?? ''));

        if ($branchId === null) {
            $this->denyQuietly($orderId, 'STORE_CLOSED', 'Store is not mapped to a branch.');

            throw new RuntimeException('Uber Eats store '.($storeId ?: '?').' is not mapped to a branch.');
        }

        [$lines, $unmapped] = $this->mapItems($branchId, (array) Arr::get($payload, 'cart.items', []));

        if ($lines === []) {
            $this->denyQuietly($orderId, 'ITEM_AVAILABILITY', 'No item of the order matches the menu.');

            throw new RuntimeException("Uber Eats order {$orderId} has no item that matches the menu (set the SKU on the menu items).");
        }

        $eater = (array) ($payload['eater'] ?? []);
        $name = trim(($eater['first_name'] ?? '').' '.($eater['last_name'] ?? ''));
        $instructions = trim((string) Arr::get($payload, 'cart.special_instructions', ''));
        $display = (string) ($payload['display_id'] ?? substr($orderId, 0, 8));

        $order = $this->orders->receive($this->channel, [
            'platform_order_id' => $orderId,
            'branch_id' => $branchId,
            'customer_name' => $name !== '' ? $name : 'Uber Eats customer',
            'customer_mobile' => null,
            'address' => $this->address($payload),
            'note' => trim('Uber Eats '.$display.($instructions !== '' ? ' - '.$instructions : '')),
            'items' => $lines,
        ]);

        $total = Arr::get($payload, 'payment.charges.total.amount');

        $order->forceFill(['aggregator_meta' => [
            'order_code' => $display,
            'transport_type' => $payload['type'] ?? null,
            'platform_status' => 'CREATED',
            'platform_total' => is_numeric($total) ? round(((float) $total) / 100, 2) : null,
            'unmapped_items' => $unmapped,
        ]])->save();

        $this->guard(function () use ($orderId, $order): bool {
            $this->client()->accept($orderId, $order->invoice_no);
            $this->remember($order, ['platform_status' => 'ACCEPTED']);

            return true;
        });

        return $order->fresh();
    }

    /**
     * Kitchen and decision updates. Uber has no ready push in this API, so
     * only a rejection or cancellation is sent.
     */
    public function pushStatus(Order $order, string $status, ?string $reason = null): bool
    {
        $platformId = (string) $order->aggregator_order_id;

        if ($platformId === '' || ! in_array($status, ['reject', 'cancel'], true)) {
            return false;
        }

        $allowed = ['OUT_OF_ITEMS', 'KITCHEN_CLOSED', 'CUSTOMER_CALLED_TO_CANCEL', 'RESTAURANT_TOO_BUSY', 'CANNOT_COMPLETE_CUSTOMER_NOTE', 'OTHER'];
        $code = in_array($reason, $allowed, true) ? $reason : 'OUT_OF_ITEMS';

        return $this->guard(function () use ($platformId, $order, $code): bool {
            $this->client()->cancel($platformId, $code, $code === 'OTHER' ? 'Cancelled by the restaurant' : null);
            $this->remember($order, ['platform_status' => 'CANCELED']);

            return true;
        });
    }

    /**
     * The cart patch format differs per order type, so edits stay local.
     */
    public function pushCart(Order $order): bool
    {
        return false;
    }

    /**
     * Price and availability of every menu item, per mapped store.
     */
    public function syncMenu(AggregatorChannel $channel): bool
    {
        $this->lastSyncCount = 0;
        $stores = array_filter((array) ($channel->vendor_map ?? []), fn ($store): bool => filled($store));

        if ($stores === []) {
            throw new RuntimeException(__('aggregators.vendor_map_missing'));
        }

        foreach ($stores as $branchId => $storeId) {
            foreach (MenuItem::query()->where('branch_id', (int) $branchId)->get() as $item) {
                $this->client()->updateItem((string) $storeId, FoodpandaAdapter::skuFor($item), array_filter([
                    'price_info' => ['price' => (int) round(((float) $item->base_price) * 100)],
                    'suspension_info' => $item->is_available ? null : ['suspension' => ['suspend_until' => now()->addDays(1)->timestamp, 'reason' => 'Out of stock']],
                ]));

                $this->lastSyncCount++;
            }
        }

        $channel->forceFill(['last_synced_at' => now(), 'last_error' => null])->save();

        return true;
    }

    /**
     * Created orders that Uber still waits on (for example while the
     * webhook was unreachable).
     *
     * @return array{imported: int, skipped: int}
     */
    public function importRecent(int $hours = 24): array
    {
        $imported = 0;
        $skipped = 0;

        foreach (array_filter((array) ($this->channel->vendor_map ?? []), fn ($store): bool => filled($store)) as $storeId) {
            $data = $this->client()->createdOrders((string) $storeId);

            foreach ((array) ($data['orders'] ?? $data['data'] ?? []) as $row) {
                $id = is_array($row) ? (string) ($row['id'] ?? '') : (string) $row;

                if ($id === '' || Order::query()->where('aggregator_channel_id', $this->channel->id)->where('aggregator_order_id', $id)->exists()) {
                    $skipped++;

                    continue;
                }

                try {
                    $this->receiveOrder($this->client()->order($id) + ['store_hint' => (string) $storeId, 'id' => $id]);
                    $imported++;
                } catch (Throwable) {
                    $skipped++;
                }
            }
        }

        $this->channel->forceFill(['last_synced_at' => now(), 'last_error' => null])->save();

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array{0: array<int, array{menu_item_id: int, quantity: int}>, 1: array<int, string>}
     */
    private function mapItems(int $branchId, array $items): array
    {
        $lines = [];
        $unmapped = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $sku = (string) ($item['external_data'] ?? $item['id'] ?? '');
            $query = MenuItem::query()->where('branch_id', $branchId);
            $menuItem = $sku !== '' ? (clone $query)->where('aggregator_sku', $sku)->first() : null;

            if ($menuItem === null && preg_match('/^'.preg_quote(FoodpandaAdapter::SKU_PREFIX, '/').'(\d+)$/i', $sku, $matches) === 1) {
                $menuItem = (clone $query)->whereKey((int) $matches[1])->first();
            }

            if ($menuItem === null) {
                $unmapped[] = (string) ($item['title'] ?? $sku ?: '?');

                continue;
            }

            $lines[] = ['menu_item_id' => $menuItem->id, 'quantity' => max(1, (int) ($item['quantity'] ?? 1))];
        }

        return [$lines, $unmapped];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function address(array $payload): ?string
    {
        $location = (array) (Arr::get($payload, 'delivery.location') ?? Arr::get($payload, 'eater.delivery.location') ?? []);
        $parts = array_filter([
            $location['address'] ?? null,
            $location['street_address_line_one'] ?? null,
            $location['street_address_line_two'] ?? null,
            $location['city'] ?? null,
        ], fn ($part): bool => filled($part));

        return $parts === [] ? null : implode(', ', array_map('strval', $parts));
    }

    private function denyQuietly(string $orderId, string $code, string $explanation): void
    {
        $this->guard(function () use ($orderId, $code, $explanation): bool {
            $this->client()->deny($orderId, $code, $explanation);

            return true;
        });
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function remember(Order $order, array $values): void
    {
        $order->forceFill(['aggregator_meta' => array_merge((array) $order->aggregator_meta, $values)])->save();
    }

    /**
     * @param  callable(): bool  $callback
     */
    private function guard(callable $callback): bool
    {
        try {
            $result = $callback();
            $this->channel->forceFill(['last_error' => null])->save();

            return $result;
        } catch (Throwable $exception) {
            $this->channel->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 500)])->save();

            return false;
        }
    }
}
