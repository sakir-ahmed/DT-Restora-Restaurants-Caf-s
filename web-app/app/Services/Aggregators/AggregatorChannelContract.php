<?php

declare(strict_types=1);

namespace App\Services\Aggregators;

use App\Models\AggregatorChannel;
use App\Models\Order;

/**
 * Contract every delivery-platform adapter implements: receive order,
 * map it to an internal order tagged with the channel, push status
 * updates back and sync menu availability. Real adapters are built
 * per platform once partner credentials exist; the manual channel
 * works from day one.
 */
interface AggregatorChannelContract
{
    public function channelKey(): string;

    /**
     * Accept a normalized platform payload, create the internal order
     * and return it. Must be idempotent by the platform order id.
     */
    public function receiveOrder(array $payload): Order;

    /**
     * Push a status update back to the platform.
     */
    public function pushStatus(Order $order, string $status): bool;

    /**
     * Sync item availability and prices for this channel.
     */
    public function syncMenu(AggregatorChannel $channel): bool;
}
