<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\AggregatorChannel;
use App\Models\Branch;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\RestaurantTable;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A year of counter, website and aggregator orders for every outlet,
 * plus a live service so the kitchen and delivery boards are not empty.
 */
class DemoSalesSeeder extends Seeder
{
    /** @var array<string, array{0: int, 1: int, 2: int}> */
    private const VOLUME = [
        'BR01' => [24, 42, 12],
        'BR02' => [14, 26, 8],
        'BR03' => [10, 20, 6],
    ];

    /** @var list<string|null> */
    private const NOTES = [
        null, null, null, null, null, null,
        'Less spicy',
        'No onion',
        'Extra borhani with the kacchi',
        'Birthday table, bring the cake later',
        'Cutlery for four',
        'Call on arrival',
        'Leave at the gate',
        'Well done, no salad',
    ];

    /** @var list<int> */
    private const HOURS = [11, 12, 12, 12, 13, 13, 13, 14, 14, 16, 18, 19, 19, 19, 20, 20, 20, 21, 21, 22];

    public function run(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        DB::disableQueryLog();
        $this->forgetPreviousDemoOrders();

        $taken = array_fill_keys(DB::table('orders')->pluck('invoice_no')->all(), true);
        $customers = Customer::query()->where('mobile', 'like', '8801713%')->pluck('id')->all();
        $showcaseId = Customer::query()->where('mobile', '8801713000001')->value('id');
        $coupons = Coupon::query()->where('is_active', true)->get();
        $aggregators = AggregatorChannel::query()->where('is_active', true)->get();
        $riders = Rider::query()->where('is_active', true)->pluck('id')->all();
        $users = User::query()->whereIn('username', ['cashier', 'cashier.dhanmondi', 'cashier.uttara', 'waiter'])->get()->keyBy('username');

        $branches = Branch::query()->whereIn('code', array_keys(self::VOLUME))->orderBy('code')->get();

        foreach ($branches as $branch) {
            $pool = $this->itemPool($branch);

            if ($pool === [] || $customers === []) {
                continue;
            }

            $this->command->info('Orders for '.$branch->name.'...');

            $tables = RestaurantTable::query()->where('branch_id', $branch->id)->where('is_active', true)->get(['id', 'name']);
            $zones = $branch->zones()->where('is_active', true)->get();
            $cashier = match ($branch->code) {
                'BR02' => $users->get('cashier.dhanmondi'),
                'BR03' => $users->get('cashier.uttara'),
                default => $users->get('cashier'),
            };
            $waiter = $branch->code === 'BR01' ? $users->get('waiter') : null;

            $this->seedBranch($branch, $pool, $tables, $zones, $customers, $showcaseId, $coupons, $aggregators, $riders, $cashier, $waiter, $taken);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $pool
     * @param  list<int>  $customers
     * @param  list<int>  $riders
     * @param  array<string, true>  $taken
     */
    private function seedBranch(
        Branch $branch,
        array $pool,
        $tables,
        $zones,
        array $customers,
        ?int $showcaseId,
        $coupons,
        $aggregators,
        array $riders,
        ?User $cashier,
        ?User $waiter,
        array &$taken,
    ): void {
        $start = now()->subDays(370)->startOfDay();
        $batch = [];
        $occupied = [];
        [$min, $max, $weekendExtra] = self::VOLUME[$branch->code];

        for ($day = 0; $day < 370; $day++) {
            $date = $start->copy()->addDays($day);
            $seed = abs(crc32($branch->code.'|'.$day));
            $weekend = in_array($date->dayOfWeekIso, [5, 6], true);
            $trend = 1 + ($day / 370) * 0.4;
            $count = (int) round(($min + ($seed % (($max - $min) + 1)) + ($weekend ? $weekendExtra : 0)) * $trend);

            for ($n = 0; $n < $count; $n++) {
                $row = $this->makeOrder(
                    branch: $branch,
                    pool: $pool,
                    tables: $tables,
                    zones: $zones,
                    customers: $customers,
                    showcaseId: $showcaseId,
                    coupons: $coupons,
                    aggregators: $aggregators,
                    riders: $riders,
                    cashier: $cashier,
                    waiter: $waiter,
                    date: $date,
                    salt: $day.'-'.$n,
                    taken: $taken,
                    live: false,
                );

                if ($row === null) {
                    continue;
                }

                $batch[] = $row;

                if (count($batch) >= 12) {
                    $this->flush($batch);
                    $batch = [];
                }
            }
        }

        foreach (range(1, 8) as $n) {
            $row = $this->makeOrder(
                branch: $branch,
                pool: $pool,
                tables: $tables,
                zones: $zones,
                customers: $customers,
                showcaseId: $showcaseId,
                coupons: $coupons,
                aggregators: $aggregators,
                riders: $riders,
                cashier: $cashier,
                waiter: $waiter,
                date: now(),
                salt: 'live-'.$n,
                taken: $taken,
                live: true,
            );

            if ($row === null) {
                continue;
            }

            if ($row['order']['restaurant_table_id'] !== null) {
                $occupied[] = $row['order']['restaurant_table_id'];
            }

            $batch[] = $row;
        }

        $this->flush($batch);

        if ($occupied !== []) {
            DB::table('restaurant_tables')->whereIn('id', $occupied)->update(['status' => 'occupied', 'updated_at' => now()]);
        }

        foreach (['T01', 'T02', 'WEB'] as $terminal) {
            $prefix = $branch->code.'-'.$terminal.'-'.now()->format('Ymd').'-';
            $last = (int) (DB::table('orders')
                ->where('invoice_no', 'like', $prefix.'%')
                ->pluck('invoice_no')
                ->map(fn (string $invoice): int => (int) substr($invoice, -4))
                ->max() ?? 0);

            DB::table('invoice_sequences')->updateOrInsert(
                ['branch_id' => $branch->id, 'terminal_code' => $terminal, 'for_date' => now()->toDateString()],
                ['last_number' => $last, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $pool
     * @param  list<int>  $customers
     * @param  list<int>  $riders
     * @param  array<string, true>  $taken
     * @return array<string, mixed>|null
     */
    private function makeOrder(
        Branch $branch,
        array $pool,
        $tables,
        $zones,
        array $customers,
        ?int $showcaseId,
        $coupons,
        $aggregators,
        array $riders,
        ?User $cashier,
        ?User $waiter,
        Carbon $date,
        string $salt,
        array &$taken,
        bool $live,
    ): ?array {
        $seed = abs(crc32($branch->code.'|'.$salt));
        $hour = self::HOURS[$seed % count(self::HOURS)];
        $created = $live
            ? now()->subMinutes(8 + ($seed % 100))
            : $date->copy()->setTime($hour, $seed % 60, $seed % 50);

        if (! $live && $created->greaterThan(now()->subMinutes(5))) {
            return null;
        }

        $channelRoll = $seed % 10;
        $channel = $channelRoll < 7 ? 'pos' : ($channelRoll < 9 ? 'website' : 'aggregator');
        $terminal = $channel === 'website' ? 'WEB' : (($seed % 5 === 0) ? 'T02' : 'T01');
        $typeRoll = $seed % 10;
        $orderType = $channel === 'aggregator'
            ? 'delivery'
            : ($typeRoll < 5 ? 'dine_in' : ($typeRoll < 8 ? 'takeaway' : 'delivery'));

        $invoice = $this->invoice($branch->code, $terminal, $created, $seed, $taken);
        $lines = $this->lines($pool, $seed, $created);
        $subtotal = array_sum(array_map(fn (array $line): float => (float) $line['line_total'], $lines));

        $coupon = null;
        $discount = 0.0;

        if (! $live && $seed % 11 === 0) {
            $coupon = $coupons->first(function (Coupon $candidate) use ($channel, $subtotal, $branch): bool {
                $channels = $candidate->channels;

                return ($channels === null || in_array($channel, $channels, true))
                    && ($candidate->branch_id === null || (int) $candidate->branch_id === $branch->id)
                    && $subtotal >= (float) $candidate->min_order
                    && ($candidate->ends_at === null || $candidate->ends_at->isFuture());
            });

            if ($coupon !== null) {
                $discount = $coupon->type === 'percent'
                    ? round($subtotal * ((float) $coupon->value) / 100, 2)
                    : min((float) $coupon->value, $subtotal);
            }
        }

        $extra = $orderType === 'takeaway' && $seed % 5 === 0 ? 20.0 : 0.0;
        $service = round(($subtotal - $discount) * ((float) $branch->service_charge_percent) / 100, 2);
        $vat = round(($subtotal - $discount + $service + $extra) * ((float) $branch->vat_percent) / 100, 2);
        $zone = $orderType === 'delivery' && $zones->isNotEmpty() ? $zones[$seed % $zones->count()] : null;
        $deliveryFee = $zone !== null ? (float) $zone->fee : 0.0;
        $raw = $subtotal - $discount + $extra + $service + $vat + $deliveryFee;
        $total = (float) (int) round($raw);
        $foodCost = round($subtotal * (0.28 + ($seed % 8) / 100), 4);

        $customerId = null;

        if ($customers !== []) {
            if ($showcaseId !== null && $channel === 'website' && $seed % 80 === 0) {
                $customerId = $showcaseId;
            } elseif ($seed % 5 !== 0) {
                $customerId = $customers[$seed % count($customers)];
            }
        }

        $status = OrderStatus::Paid;
        $openStatuses = [OrderStatus::Pending, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Served];

        if ($live) {
            $status = $openStatuses[$seed % count($openStatuses)];
        } elseif ($seed % 70 === 0) {
            $status = OrderStatus::Void;
        }

        $aggregator = $channel === 'aggregator' && $aggregators->isNotEmpty() ? $aggregators[$seed % $aggregators->count()] : null;
        $commission = $aggregator !== null ? round($total * ((float) $aggregator->commission_percent) / 100, 2) : 0.0;
        $tableId = $orderType === 'dine_in' && $tables->isNotEmpty() ? $tables[$seed % $tables->count()]->id : null;

        $address = null;

        if ($orderType === 'delivery') {
            $address = 'House '.(($seed % 40) + 2).', '.($zone->name ?? 'Dhaka').', Dhaka';
        }

        $order = [
            'branch_id' => $branch->id,
            'invoice_no' => $invoice,
            'order_type' => $orderType,
            'channel' => $channel,
            'restaurant_table_id' => $tableId,
            'waiter_id' => $orderType === 'dine_in' ? $waiter?->id : null,
            'cashier_id' => $cashier?->id,
            'customer_id' => $customerId,
            'status' => $status->value,
            'subtotal' => $this->money($subtotal),
            'discount_type' => $discount > 0 ? $coupon?->type : null,
            'discount_value' => $this->money($discount > 0 ? (float) $coupon->value : 0),
            'discount_amount' => $this->money($discount),
            'service_charge' => $this->money($service),
            'extra_charge' => $this->money($extra),
            'vat_amount' => $this->money($vat),
            'delivery_fee' => $this->money($deliveryFee),
            'rounding' => $this->money(round($total - $raw, 2)),
            'total' => $this->money($total),
            'food_cost' => number_format($foodCost, 4, '.', ''),
            'client_id' => 'demo-'.$invoice,
            'note' => self::NOTES[$seed % count(self::NOTES)],
            'paid_at' => $status === OrderStatus::Paid ? $created->copy()->addMinutes(12) : null,
            'coupon_id' => $coupon?->id,
            'points_redeemed' => 0,
            'aggregator_channel_id' => $aggregator?->id,
            'aggregator_order_id' => $aggregator !== null ? strtoupper(substr($aggregator->key, 0, 2)).'-'.substr((string) $seed, -6) : null,
            'commission_amount' => $this->money($commission),
            'delivery_zone_id' => $zone?->id,
            'delivery_address' => $address,
            'tracking_token' => $orderType === 'delivery' ? substr(hash('sha256', $invoice), 0, 32) : null,
            'created_at' => $created,
            'updated_at' => $created,
        ];

        $payments = $status === OrderStatus::Paid ? $this->payments($order, $seed, $cashier?->id) : [];
        $delivery = null;

        if ($orderType === 'delivery' && ($live || $created->greaterThan(now()->subDays(21)))) {
            $delivery = $this->delivery($status, $live, $seed, $total, $created, $riders);
        }

        return [
            'order' => $order,
            'items' => $lines,
            'payments' => $payments,
            'delivery' => $delivery,
            'coupon' => $status === OrderStatus::Paid ? $coupon : null,
            'customer_id' => $customerId,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $pool
     * @return list<array<string, mixed>>
     */
    private function lines(array $pool, int $seed, Carbon $created): array
    {
        $count = 1 + ($seed % 4);
        $lines = [];

        for ($i = 0; $i < $count; $i++) {
            $item = $pool[abs(crc32($seed.'|'.$i)) % count($pool)];
            $variant = null;

            if ($item['variants'] !== []) {
                $variant = ($seed + $i) % 3 === 0
                    ? $item['variants'][array_key_last($item['variants'])]
                    : $item['variants'][0];
            }

            $qty = 1 + (($seed + $i) % 3);
            $unit = (float) ($variant['price'] ?? $item['price']);
            $addOns = [];
            $addOnTotal = 0.0;

            if ($item['addons'] !== [] && ($seed + $i) % 3 === 0) {
                $addOn = $item['addons'][($seed + $i) % count($item['addons'])];
                $addOns[] = [
                    'id' => $addOn['id'],
                    'name' => $addOn['name'],
                    'name_bn' => $addOn['name_bn'],
                    'price' => $addOn['price'],
                    'qty' => 1,
                ];
                $addOnTotal = (float) $addOn['price'];
            }

            $lineTotal = round(($unit * $qty) + ($addOnTotal * $qty), 2);

            $lines[] = [
                'menu_item_id' => $item['id'],
                'variant_id' => $variant['id'] ?? null,
                'name' => $item['name'],
                'name_bn' => $item['name_bn'],
                'unit_price' => $this->money($unit),
                'quantity' => $qty,
                'add_ons' => $addOns === [] ? null : json_encode($addOns),
                'add_ons_total' => $this->money($addOnTotal),
                'line_total' => $this->money($lineTotal),
                'line_cost' => number_format($lineTotal * 0.32, 4, '.', ''),
                'created_at' => $created,
                'updated_at' => $created,
            ];
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return list<array<string, mixed>>
     */
    private function payments(array $order, int $seed, ?int $userId): array
    {
        $total = (float) $order['total'];
        $methods = ['cash', 'cash', 'cash', 'cash', 'bkash', 'bkash', 'nagad', 'card'];
        $created = $order['paid_at'];
        $rows = [];

        if ($seed % 14 === 0 && $total >= 2) {
            $cash = floor($total / 2);
            $rows[] = $this->paymentRow('cash', $cash, $order, $userId, $created, $seed);
            $rows[] = $this->paymentRow('bkash', $total - $cash, $order, $userId, $created, $seed + 1);
        } else {
            $rows[] = $this->paymentRow($methods[$seed % count($methods)], $total, $order, $userId, $created, $seed);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function paymentRow(string $method, float $amount, array $order, ?int $userId, mixed $created, int $seed): array
    {
        $tendered = null;
        $change = null;

        if ($method === 'cash') {
            $tendered = (float) (ceil($amount / 50) * 50);
            $change = round($tendered - $amount, 2);
        }

        $trx = $method === 'cash' ? null : strtoupper(substr(hash('sha256', $order['invoice_no'].$method.$seed), 0, 10));

        return [
            'method' => $method,
            'amount' => $this->money($amount),
            'tendered' => $tendered === null ? null : $this->money($tendered),
            'change_due' => $change === null ? null : $this->money($change),
            'reference' => $trx,
            'status' => 'completed',
            'user_id' => $userId,
            'gateway' => $method === 'cash' ? null : $method,
            'gateway_trx_id' => $trx,
            'verified_at' => $method === 'cash' ? null : $created,
            'created_at' => $created,
            'updated_at' => $created,
        ];
    }

    /**
     * @param  list<int>  $riders
     * @return array<string, mixed>
     */
    private function delivery(OrderStatus $status, bool $live, int $seed, float $total, Carbon $created, array $riders): array
    {
        $riderId = $riders === [] ? null : $riders[$seed % count($riders)];
        $stage = $live ? ['assigned', 'picked_up', 'on_the_way'][$seed % 3] : 'delivered';

        if (! $live && $status === OrderStatus::Void) {
            $stage = 'failed';
        }

        return [
            'rider_id' => $riderId,
            'status' => $stage,
            'cod_amount' => $seed % 2 === 0 ? $this->money($total) : '0.00',
            'cod_received_at' => $stage === 'delivered' ? $created->copy()->addMinutes(40) : null,
            'otp_code' => $live ? str_pad((string) ($seed % 1000000), 6, '0', STR_PAD_LEFT) : null,
            'assigned_at' => $created->copy()->addMinutes(4),
            'picked_up_at' => in_array($stage, ['picked_up', 'on_the_way', 'delivered'], true) ? $created->copy()->addMinutes(18) : null,
            'delivered_at' => $stage === 'delivered' ? $created->copy()->addMinutes(40) : null,
            'failed_reason' => $stage === 'failed' ? 'Guest was not at the address' : null,
            'created_at' => $created,
            'updated_at' => $created,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itemPool(Branch $branch): array
    {
        return MenuItem::query()
            ->where('branch_id', $branch->id)
            ->where('is_available', true)
            ->with(['variants:id,menu_item_id,price,is_default', 'addOns:id,menu_item_id,name,name_bn,price'])
            ->get(['id', 'name', 'name_bn', 'base_price'])
            ->map(fn (MenuItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'name_bn' => $item->name_bn,
                'price' => (string) $item->base_price,
                'variants' => $item->variants->map(fn ($variant): array => [
                    'id' => $variant->id,
                    'price' => (string) $variant->price,
                ])->values()->all(),
                'addons' => $item->addOns->map(fn ($addOn): array => [
                    'id' => $addOn->id,
                    'name' => $addOn->name,
                    'name_bn' => $addOn->name_bn,
                    'price' => (string) $addOn->price,
                ])->values()->all(),
            ])
            ->all();
    }

    /**
     * @param  array<string, true>  $taken
     */
    private function invoice(string $code, string $terminal, Carbon $created, int $seed, array &$taken): string
    {
        $sequence = ($seed % 9000) + 1;

        do {
            $invoice = sprintf('%s-%s-%s-%04d', $code, $terminal, $created->format('Ymd'), $sequence);
            $sequence++;
        } while (isset($taken[$invoice]));

        $taken[$invoice] = true;

        return $invoice;
    }

    /**
     * @param  list<array<string, mixed>>  $batch
     */
    private function flush(array $batch): void
    {
        if ($batch === []) {
            return;
        }

        $orders = array_map(fn (array $row): array => $row['order'], $batch);
        DB::table('orders')->insert($orders);

        $ids = DB::table('orders')
            ->whereIn('invoice_no', array_column($orders, 'invoice_no'))
            ->pluck('id', 'invoice_no');

        $items = [];
        $payments = [];
        $deliveries = [];
        $redemptions = [];

        foreach ($batch as $row) {
            $orderId = (int) $ids[$row['order']['invoice_no']];

            foreach ($row['items'] as $item) {
                $item['order_id'] = $orderId;
                $items[] = $item;
            }

            foreach ($row['payments'] as $payment) {
                $payment['order_id'] = $orderId;
                $payments[] = $payment;
            }

            if ($row['delivery'] !== null) {
                $deliveries[] = ['order_id' => $orderId, ...$row['delivery']];
            }

            if ($row['coupon'] !== null) {
                $redemptions[] = [
                    'coupon_id' => $row['coupon']->id,
                    'order_id' => $orderId,
                    'customer_id' => $row['customer_id'],
                    'created_at' => $row['order']['created_at'],
                    'updated_at' => $row['order']['updated_at'],
                ];
            }
        }

        foreach (array_chunk($items, 40) as $chunk) {
            DB::table('order_items')->insert($chunk);
        }

        foreach (array_chunk($payments, 40) as $chunk) {
            DB::table('payments')->insert($chunk);
        }

        if ($deliveries !== []) {
            DB::table('deliveries')->insert($deliveries);
        }

        if ($redemptions !== []) {
            DB::table('coupon_redemptions')->insert($redemptions);
        }
    }

    private function forgetPreviousDemoOrders(): void
    {
        do {
            $ids = DB::table('orders')
                ->where(function ($query): void {
                    $query->where('client_id', 'like', 'demo-%')
                        ->orWhere('invoice_no', 'like', 'BR__-BR__-%');
                })
                ->limit(300)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            DB::table('coupon_redemptions')->whereIn('order_id', $ids)->delete();
            DB::table('deliveries')->whereIn('order_id', $ids)->delete();
            DB::table('payments')->whereIn('order_id', $ids)->delete();
            DB::table('order_items')->whereIn('order_id', $ids)->delete();
            DB::table('void_requests')->whereIn('order_id', $ids)->delete();
            DB::table('loyalty_entries')->whereIn('order_id', $ids)->update(['order_id' => null]);
            DB::table('orders')->whereIn('id', $ids)->delete();
        } while (true);
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
