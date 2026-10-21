<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\AggregatorChannel;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\Aggregators\AggregatorOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Delivery platform channels with a few live orders per outlet, so the
 * orders list, the kitchen screen and the POS alerts have something real.
 */
class DemoAggregatorSeeder extends Seeder
{
    public function run(AggregatorOrderService $service): void
    {
        $branches = Branch::query()->orderBy('id')->get();

        foreach ([
            ['foodpanda', 'Foodpanda', '25.00'],
            ['pathao_food', 'Pathao Food', '20.00'],
            ['foodi', 'Foodi', '18.00'],
            ['hungrynaki', 'HungryNaki', '22.00'],
            ['shohoz_food', 'Shohoz Food', '20.00'],
        ] as [$key, $name, $commission]) {
            $channel = AggregatorChannel::query()->updateOrCreate(
                ['key' => $key],
                ['name' => $name, 'commission_percent' => $commission, 'is_active' => true],
            );

            if ($key === 'foodpanda') {
                $channel->forceFill([
                    'environment' => 'sandbox',
                    'chain_id' => 'ch-dtrestora-demo',
                    'client_id' => 'demo-client',
                    'vendor_map' => $branches->mapWithKeys(fn (Branch $branch): array => [(string) $branch->id => 'FP-'.$branch->code])->all(),
                ])->save();
            }

            $this->seedLiveOrders($service, $channel, $branches);
        }
    }

    /**
     * @param  Collection<int, Branch>  $branches
     */
    private function seedLiveOrders(AggregatorOrderService $service, AggregatorChannel $channel, $branches): void
    {
        $statuses = [OrderStatus::Pending, OrderStatus::Preparing, OrderStatus::Ready];

        foreach ($branches as $index => $branch) {
            $items = MenuItem::query()->withoutGlobalScopes()->where('branch_id', $branch->id)->where('is_available', true)->orderBy('id')->limit(12)->get();

            if ($items->count() < 3) {
                continue;
            }

            foreach ($statuses as $n => $status) {
                $platformId = strtoupper(substr($channel->key, 0, 2)).'-D'.$branch->id.$n.crc32($channel->key) % 1000;

                if (Order::query()->where('aggregator_channel_id', $channel->id)->where('aggregator_order_id', $platformId)->exists()) {
                    continue;
                }

                $pick = $items->slice(($index + $n) % ($items->count() - 2), 2)->values();

                try {
                    $order = $service->receive($channel, [
                        'platform_order_id' => $platformId,
                        'branch_id' => $branch->id,
                        'customer_name' => ['Rahim Uddin', 'Nusrat Jahan', 'Tanvir Hasan', 'Farhana Akter'][($index + $n) % 4],
                        'customer_mobile' => '0171'.str_pad((string) (1000000 + $branch->id * 111 + $n * 37), 7, '0', STR_PAD_LEFT),
                        'address' => 'Road '.(3 + $n).', House '.(10 + $index).', Dhaka',
                        'items' => $pick->map(fn (MenuItem $item): array => ['menu_item_id' => $item->id, 'quantity' => 1 + $n % 2])->all(),
                        'note' => $n === 0 ? 'Please send extra napkins' : null,
                    ]);
                } catch (\Throwable) {
                    continue;
                }

                $order->forceFill([
                    'status' => $status->value,
                    'aggregator_meta' => ['order_code' => $platformId, 'transport_type' => 'PLATFORM_DELIVERY', 'payment_type' => $n === 1 ? 'PAID' : 'CASH'],
                    'created_at' => now()->subMinutes(4 + $n * 7),
                ])->save();
            }
        }
    }
}
