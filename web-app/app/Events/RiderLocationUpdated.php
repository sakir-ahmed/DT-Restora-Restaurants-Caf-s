<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiderLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, int>  $orderIds
     */
    public function __construct(
        public readonly int $riderId,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly array $orderIds,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            fn (int $orderId): Channel => new PrivateChannel("order.{$orderId}.tracking"),
            $this->orderIds,
        );
    }

    public function broadcastAs(): string
    {
        return 'RiderLocationUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'rider_id' => $this->riderId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'recorded_at' => now()->toIso8601String(),
        ];
    }
}
