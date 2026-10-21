<?php

declare(strict_types=1);

namespace App\Services\Aggregators\Foodpanda;

use App\Actions\Sales\ApproveOrderVoidAction;
use App\Enums\OrderStatus;
use App\Models\AggregatorChannel;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\Aggregators\AggregatorChannelContract;
use App\Services\Aggregators\AggregatorOrderService;
use Illuminate\Support\Arr;
use RuntimeException;
use Throwable;

/**
 * Foodpanda (Delivery Hero Partner API v2) adapter: turns the order
 * webhook into a normal internal order, pushes kitchen status back,
 * and keeps prices and availability in step with the menu.
 */
class FoodpandaAdapter implements AggregatorChannelContract
{
    /** Items sent to the platform that have no explicit SKU use this prefix plus the menu item id. */
    public const SKU_PREFIX = 'DT-';

    public int $lastSyncCount = 0;

    private ?FoodpandaClient $client = null;

    public function __construct(
        private readonly AggregatorChannel $channel,
        private readonly AggregatorOrderService $orders,
        private readonly ApproveOrderVoidAction $voids,
    ) {}

    public function channelKey(): string
    {
        return AggregatorChannel::FOODPANDA;
    }

    public function client(): FoodpandaClient
    {
        return $this->client ??= new FoodpandaClient($this->channel);
    }

    public static function skuFor(MenuItem $item): string
    {
        return filled($item->aggregator_sku) ? (string) $item->aggregator_sku : self::SKU_PREFIX.$item->id;
    }

    /**
     * Handle an order payload from the webhook (or from order history).
     * New orders are created; later events only update the status.
     *
     * @param  array<string, mixed>  $payload
     */
    public function receiveOrder(array $payload): Order
    {
        $orderId = (string) ($payload['order_id'] ?? '');

        if ($orderId === '') {
            throw new RuntimeException('Foodpanda payload has no order_id.');
        }

        $existing = Order::query()
            ->where('aggregator_channel_id', $this->channel->id)
            ->where('aggregator_order_id', $orderId)
            ->first();

        if ($existing !== null) {
            return $this->applyStatus($existing, $payload);
        }

        $client = (array) ($payload['client'] ?? []);
        $branchId = $this->channel->branchIdForVendor($client['id'] ?? null)
            ?? $this->channel->branchIdForVendor($client['store_id'] ?? null);

        if ($branchId === null) {
            throw new RuntimeException('Foodpanda vendor '.($client['id'] ?? '?').' is not mapped to a branch.');
        }

        $status = (string) ($payload['status'] ?? 'RECEIVED');

        if (in_array($status, ['CANCELLED', 'CANCELED', 'DELIVERED'], true)) {
            throw new RuntimeException("Foodpanda order {$orderId} arrived already {$status}; nothing to create.");
        }

        [$lines, $unmapped] = $this->mapItems($branchId, (array) ($payload['items'] ?? []));

        if ($lines === []) {
            throw new RuntimeException("Foodpanda order {$orderId} has no item that matches the menu (set the SKU on the menu items).");
        }

        $code = (string) ($payload['order_code'] ?? '');
        $comment = trim((string) ($payload['comment'] ?? ''));

        $order = $this->orders->receive($this->channel, [
            'platform_order_id' => $orderId,
            'branch_id' => $branchId,
            'customer_name' => $this->customerName((array) ($payload['customer'] ?? [])),
            'customer_mobile' => null,
            'address' => $this->address((array) Arr::get($payload, 'customer.delivery_address', [])),
            'note' => trim('Foodpanda '.$code.($comment !== '' ? ' - '.$comment : '')),
            'items' => $lines,
        ]);

        $order->forceFill(['aggregator_meta' => $this->meta($payload, $unmapped)])->save();

        return $order->fresh();
    }

    /**
     * Push a kitchen or decision status to the platform.
     * $status is ready, dispatched, reject or cancel.
     */
    public function pushStatus(Order $order, string $status, ?string $reason = null): bool
    {
        $meta = (array) $order->aggregator_meta;
        $platformId = (string) $order->aggregator_order_id;

        if ($platformId === '') {
            return false;
        }

        $vendorDelivery = ($meta['transport_type'] ?? '') === 'VENDOR_DELIVERY';

        $platformStatus = match ($status) {
            'ready' => $vendorDelivery ? 'DISPATCHED' : 'READY_FOR_PICKUP',
            'dispatched' => 'DISPATCHED',
            'reject', 'cancel' => 'CANCELLED',
            default => null,
        };

        if ($platformStatus === null) {
            return false;
        }

        $body = [
            'order_id' => $platformId,
            'status' => $platformStatus,
            'items' => $this->cartItems($order),
        ];

        if ($platformStatus === 'CANCELLED') {
            $body['cancellation'] = ['reason' => $reason ?? 'ITEM_UNAVAILABLE'];
        }

        return $this->guard(function () use ($platformId, $body, $order, $platformStatus): bool {
            $this->client()->updateOrder($platformId, $body);
            $this->remember($order, ['platform_status' => $platformStatus]);

            return true;
        });
    }

    /**
     * Send the current items of an edited order (status UPDATE_CART).
     */
    public function pushCart(Order $order): bool
    {
        $platformId = (string) $order->aggregator_order_id;

        if ($platformId === '') {
            return false;
        }

        return $this->guard(function () use ($platformId, $order): bool {
            $this->client()->updateOrder($platformId, [
                'order_id' => $platformId,
                'status' => 'UPDATE_CART',
                'items' => $this->cartItems($order),
            ]);

            return true;
        });
    }

    /**
     * Push prices and availability of every menu item for each mapped outlet.
     */
    public function syncMenu(AggregatorChannel $channel): bool
    {
        $this->lastSyncCount = 0;
        $vendors = array_filter((array) ($channel->vendor_map ?? []), fn ($vendor): bool => filled($vendor));

        if ($vendors === []) {
            throw new RuntimeException(__('aggregators.vendor_map_missing'));
        }

        foreach ($vendors as $branchId => $vendorId) {
            $products = MenuItem::query()
                ->where('branch_id', (int) $branchId)
                ->get()
                ->map(fn (MenuItem $item): array => [
                    'sku' => self::skuFor($item),
                    'price' => (float) $item->base_price,
                    'active' => (bool) $item->is_available,
                ])
                ->all();

            foreach (array_chunk($products, 1000) as $chunk) {
                $this->client()->updateProducts((string) $vendorId, $chunk);
            }

            $this->lastSyncCount += count($products);
        }

        $channel->forceFill(['last_synced_at' => now(), 'last_error' => null])->save();

        return true;
    }

    /**
     * Pull recent orders from the platform and create any we missed
     * (for example while the webhook was unreachable).
     *
     * @return array{imported: int, skipped: int}
     */
    public function importRecent(int $hours = 24): array
    {
        $imported = 0;
        $skipped = 0;
        $to = now();
        $from = now()->subHours(min($hours, 24 * 60));

        foreach (array_filter((array) ($this->channel->vendor_map ?? []), fn ($vendor): bool => filled($vendor)) as $vendorId) {
            $page = 1;

            do {
                $data = $this->client()->listOrders((string) $vendorId, $from, $to, $page);
                $rows = (array) ($data['orders'] ?? $data['items'] ?? $data['data'] ?? []);

                foreach ($rows as $row) {
                    if (! is_array($row) || ! in_array((string) ($row['status'] ?? ''), ['RECEIVED', 'READY_FOR_PICKUP', 'DISPATCHED'], true)) {
                        $skipped++;

                        continue;
                    }

                    $known = Order::query()
                        ->where('aggregator_channel_id', $this->channel->id)
                        ->where('aggregator_order_id', (string) ($row['order_id'] ?? ''))
                        ->exists();

                    if ($known) {
                        $skipped++;

                        continue;
                    }

                    try {
                        $this->receiveOrder($row);
                        $imported++;
                    } catch (Throwable) {
                        $skipped++;
                    }
                }

                $page++;
            } while (count($rows) >= 100 && $page <= 20);
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

            $itemStatus = (string) ($item['status'] ?? 'IN_CART');

            if ($itemStatus !== 'IN_CART') {
                continue;
            }

            $menuItem = $this->menuItemFor($branchId, (string) ($item['sku'] ?? ''), (array) ($item['barcode'] ?? []));
            $quantity = max(1, (int) round((float) (Arr::get($item, 'pricing.quantity') ?? Arr::get($item, 'original_pricing.quantity') ?? 1)));

            if ($menuItem === null) {
                $unmapped[] = (string) ($item['name'] ?? $item['sku'] ?? '?');

                continue;
            }

            $lines[] = ['menu_item_id' => $menuItem->id, 'quantity' => $quantity];
        }

        return [$lines, $unmapped];
    }

    /**
     * @param  array<int, mixed>  $barcodes
     */
    private function menuItemFor(int $branchId, string $sku, array $barcodes): ?MenuItem
    {
        $query = MenuItem::query()->where('branch_id', $branchId);

        if ($sku !== '') {
            $found = (clone $query)->where('aggregator_sku', $sku)->first();

            if ($found !== null) {
                return $found;
            }

            if (preg_match('/^'.preg_quote(self::SKU_PREFIX, '/').'(\d+)$/i', $sku, $matches) === 1) {
                $found = (clone $query)->whereKey((int) $matches[1])->first();

                if ($found !== null) {
                    return $found;
                }
            }
        }

        foreach ($barcodes as $barcode) {
            $found = (clone $query)->where('barcode', (string) $barcode)->first();

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyStatus(Order $order, array $payload): Order
    {
        $status = (string) ($payload['status'] ?? '');

        if ($status === '') {
            return $order;
        }

        $this->remember($order, array_filter([
            'platform_status' => $status,
            'cancelled_by' => Arr::get($payload, 'cancellation.cancelled_by'),
            'cancel_reason' => Arr::get($payload, 'cancellation.reason'),
        ], fn ($value): bool => $value !== null));

        if (in_array($status, ['CANCELLED', 'CANCELED'], true) && $order->status === OrderStatus::Pending) {
            $this->voids->cancelPending($order);
        }

        if ($status === 'DELIVERED' && $order->status->canTransitionTo(OrderStatus::Served) && $order->status !== OrderStatus::Served) {
            $order->update(['status' => OrderStatus::Served->value]);
        }

        activity()->performedOn($order)->withProperties(['platform_status' => $status])->log('aggregator.status');

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $unmapped
     * @return array<string, mixed>
     */
    private function meta(array $payload, array $unmapped): array
    {
        return [
            'order_code' => $payload['order_code'] ?? null,
            'external_order_id' => $payload['external_order_id'] ?? null,
            'transport_type' => $payload['transport_type'] ?? null,
            'platform_order_type' => $payload['order_type'] ?? null,
            'platform_status' => $payload['status'] ?? 'RECEIVED',
            'promised_for' => $payload['promised_for'] ?? null,
            'is_preorder' => (bool) ($payload['isPreorder'] ?? false),
            'payment_type' => Arr::get($payload, 'payment.type'),
            'collect_at_pickup' => Arr::get($payload, 'payment.collect_at_pickup'),
            'platform_total' => Arr::get($payload, 'payment.order_total'),
            'platform_delivery_fee' => Arr::get($payload, 'payment.delivery_fee'),
            'unmapped_items' => $unmapped,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function remember(Order $order, array $values): void
    {
        $order->forceFill(['aggregator_meta' => array_merge((array) $order->aggregator_meta, $values)])->save();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cartItems(Order $order): array
    {
        $order->loadMissing('items');
        $skus = MenuItem::withoutGlobalScopes()
            ->whereIn('id', $order->items->pluck('menu_item_id'))
            ->get()
            ->keyBy('id');

        return $order->items->map(function ($line) use ($skus): array {
            $menuItem = $skus->get($line->menu_item_id);

            return [
                'sku' => $menuItem !== null ? self::skuFor($menuItem) : self::SKU_PREFIX.$line->menu_item_id,
                'status' => 'IN_CART',
                'pricing' => [
                    'pricing_type' => 'UNIT',
                    'quantity' => (int) $line->quantity,
                    'unit_price' => (float) $line->unit_price,
                ],
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private function customerName(array $customer): string
    {
        $name = trim(($customer['first_name'] ?? '').' '.($customer['last_name'] ?? ''));

        return $name === '' || str_contains($name, '*') ? 'Foodpanda customer' : $name;
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function address(array $address): ?string
    {
        $parts = array_filter(array_map(
            fn (string $key): string => trim((string) ($address[$key] ?? '')),
            ['number', 'street', 'building', 'floor', 'apartment', 'suburb', 'city', 'zipcode', 'instructions'],
        ));

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Run a platform call: record the failure on the channel instead of
     * breaking the kitchen flow.
     *
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
