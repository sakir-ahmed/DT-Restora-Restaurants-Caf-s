<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One year of realistic demo orders across both branches so the
 * dashboard, reports and charts have data to show.
 */
class DemoSalesSeeder extends Seeder
{
    public function run(): void
    {
        if (config('app.env') === 'testing') {
            return;
        }

        $cashier = User::query()->where('username', 'cashier')->first();

        $branches = Branch::query()->whereIn('code', ['BR01', 'BR02'])->get();

        foreach ($branches as $branch) {
            $categories = MenuCategory::query()
                ->where('branch_id', $branch->id)
                ->with(['items.variants'])
                ->get();

            $items = $categories->flatMap->items;

            if ($items->isEmpty()) {
                continue;
            }

            $this->seedYear($branch, $items, $cashier?->id, $branch->code === 'BR01' ? 'BR01-T01' : 'BR02-T01');
        }
    }

    private function seedYear(Branch $branch, $items, ?int $userId, string $terminal): void
    {
        $start = now()->subYear()->startOfDay();
        $sequence = 0;
        $batch = [];

        // 2-6 orders per day over a year: keeps seeding fast in bulk.
        for ($day = 0; $day < 365; $day++) {
            $date = $start->copy()->addDays($day);

            // Slow growth trend and a weekend boost.
            $weekend = in_array($date->dayOfWeekIso, [5, 6], true);
            $trend = 1 + ($day / 365) * 0.5;
            $count = (int) round((mt_rand(2, 5) + ($weekend ? 2 : 0)) * $trend);

            for ($i = 0; $i < $count; $i++) {
                $sequence += 1;

                $batch[] = $this->makeOrder($branch, $items, $date, $sequence, $terminal, $userId);
            }

            if (count($batch) >= 300) {
                $this->insertBatch($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->insertBatch($batch);
        }
    }

    private function makeOrder(Branch $branch, $items, $date, int $sequence, string $terminal, ?int $userId): array
    {
        $lineCount = mt_rand(1, 4);
        $subtotal = 0.0;
        $foodCost = 0.0;
        $orderItems = [];

        for ($i = 0; $i < $lineCount; $i++) {
            /** @var MenuItem $item */
            $item = $items->random();
            $variant = $item->variants->isNotEmpty() ? $item->variants->random() : null;
            $qty = mt_rand(1, 3);
            $unitPrice = (float) ($variant->price ?? $item->base_price);

            $lineTotal = $unitPrice * $qty;
            $subtotal += $lineTotal;

            $orderItems[] = [
                'menu_item_id' => $item->id,
                'variant_id' => $variant?->id,
                'name' => $item->name,
                'name_bn' => $item->name_bn,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'quantity' => $qty,
                'add_ons' => null,
                'add_ons_total' => '0.00',
                'line_total' => number_format($lineTotal, 2, '.', ''),
                'line_cost' => number_format($lineTotal * 0.32, 4, '.', ''),
            ];

            $foodCost += $lineTotal * 0.32;
        }

        $serviceCharge = round($subtotal * ((float) $branch->service_charge_percent) / 100, 2);
        $vatAmount = round(($subtotal + $serviceCharge) * ((float) $branch->vat_percent) / 100, 2);
        $rawTotal = $subtotal + $serviceCharge + $vatAmount;
        $total = (int) round($rawTotal);

        $createdAt = $date->copy()->addHours(mt_rand(10, 22))->addMinutes(mt_rand(0, 59));

        return [
            'branch_id' => $branch->id,
            'invoice_no' => sprintf('%s-%s-%s-%04d', $branch->code, $terminal, $createdAt->format('Ymd'), $sequence),
            'order_type' => collect(['dine_in', 'takeaway', 'delivery'])->random(),
            'channel' => collect(['pos', 'pos', 'pos', 'website', 'aggregator'])->random(),
            'status' => OrderStatus::Paid->value,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'service_charge' => number_format($serviceCharge, 2, '.', ''),
            'vat_amount' => number_format($vatAmount, 2, '.', ''),
            'total' => number_format((float) $total, 2, '.', ''),
            'food_cost' => number_format($foodCost, 4, '.', ''),
            'rounding' => number_format($total - $rawTotal, 2, '.', ''),
            'cashier_id' => $userId,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'items' => $orderItems,
            'paid_at' => $createdAt,
        ];
    }

    private function insertBatch(array $batch): void
    {
        foreach ($batch as $order) {
            $items = $order['items'];
            unset($order['items']);

            $orderId = \DB::table('orders')->insertGetId($order);

            foreach ($items as $item) {
                \DB::table('order_items')->insert([
                    ...$item,
                    'order_id' => $orderId,
                    'created_at' => $order['created_at'],
                    'updated_at' => $order['updated_at'],
                ]);
            }

            \DB::table('payments')->insert([
                'order_id' => $orderId,
                'method' => 'cash',
                'amount' => $order['total'],
                'status' => Payment::STATUS_COMPLETED,
                'user_id' => $order['cashier_id'],
                'created_at' => $order['created_at'],
                'updated_at' => $order['updated_at'],
            ]);
        }
    }
}
