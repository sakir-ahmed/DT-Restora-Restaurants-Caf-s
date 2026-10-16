<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\IngredientStock;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Recipe;
use App\Models\RestaurantTable;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\CouponService;
use App\Services\InvoiceNumberService;
use App\Services\StockService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single order creation path for POS, website, API and aggregators.
 * Prices, VAT, service charge, rounding and stock deduction all behave
 * the same wherever an order comes from.
 *
 * @phpstan-type OrderLineInput array{menu_item_id: int, variant_id?: int|null, quantity: int, add_ons?: array<int, array{id: int, qty?: int}>|null, discount?: array{type?: string, value?: float|int|string}|null, vat_percent?: float|int|string|null, extra_charge?: float|int|string|null}
 * @phpstan-type DiscountInput array{type: 'percent'|'fixed', value: float}|null
 */
class CreateOrderAction
{
    public function __construct(
        private readonly StockService $stock,
        private readonly InvoiceNumberService $invoices,
    ) {}

    /**
     * @param  Collection<int, OrderLineInput>|array<int, OrderLineInput>  $lines
     */
    public function execute(
        Branch $branch,
        OrderType $type,
        array|Collection $lines,
        ?User $cashier = null,
        ?User $waiter = null,
        ?int $tableId = null,
        ?Customer $customer = null,
        ?array $discount = null,
        ?string $note = null,
        OrderChannel $channel = OrderChannel::Pos,
        ?string $clientId = null,
        ?Shift $shift = null,
        ?string $terminalCode = 'T01',
        ?float $deliveryFee = null,
        ?int $deliveryZoneId = null,
        ?string $deliveryAddress = null,
        ?\DateTimeInterface $scheduledFor = null,
        ?string $couponCode = null,
        ?string $invoiceNo = null,
    ): Order {
        if ($clientId !== null) {
            $existing = Order::query()->where('client_id', $clientId)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $lines = collect($lines);

        return DB::transaction(function () use ($branch, $type, $lines, $cashier, $waiter, $tableId, $customer, $discount, $note, $channel, $clientId, $shift, $terminalCode, $deliveryFee, $deliveryZoneId, $deliveryAddress, $scheduledFor, $couponCode, $invoiceNo): Order {
            $branch = Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();

            // Percent discounts above the limit need the override permission.
            // Fixed amounts are checked against the subtotal once lines are priced.
            if ($discount !== null && $discount['type'] === 'percent' && (float) $discount['value'] > 0) {
                $limit = (float) Setting::current()->discount_limit_percent;

                if ((float) $discount['value'] > $limit && ($cashier === null || ! $cashier->can('discounts.override'))) {
                    throw ValidationException::withMessages([
                        'discount' => __('orders.discount_limit_exceeded'),
                    ]);
                }
            }

            $items = $lines->map(function (array $line) use ($branch): array {
                return $this->resolveLine($branch, $line);
            });

            $subtotal = round($items->sum(fn (array $item) => (float) $item['line_total']), 2);
            $adjustments = $this->lineAdjustments(
                $lines->values(),
                $items->map(fn (array $item): float => (float) $item['line_total'])->values(),
            );
            $lineDiscounts = $adjustments['discount'];
            $extraTotal = $adjustments['extra'];

            $billBase = max(0, round($subtotal - $lineDiscounts, 2));
            $manualDiscount = $this->discountAmount($discount, $billBase);

            $coupon = null;
            $couponDiscount = 0.0;

            if ($couponCode !== null && $couponCode !== '') {
                $result = app(CouponService::class)->validate(
                    $couponCode,
                    round(max(0, $billBase - $manualDiscount), 2),
                    ['channel' => $channel->value, 'branch_id' => $branch->id, 'customer_id' => $customer?->id],
                );

                $coupon = $result['coupon'];
                $couponDiscount = $result['discount'];
            }

            $discountBeforeCoupon = round($lineDiscounts + $manualDiscount, 2);

            if ($subtotal > 0 && ($discountBeforeCoupon / $subtotal * 100) > (float) Setting::current()->discount_limit_percent && ($cashier === null || ! $cashier->can('discounts.override'))) {
                throw ValidationException::withMessages([
                    'discount' => __('orders.discount_limit_exceeded'),
                ]);
            }

            $discountAmount = round($discountBeforeCoupon + $couponDiscount, 2);

            $afterDiscount = round($subtotal - $discountAmount, 2);

            $serviceCharge = round($afterDiscount * ((float) $branch->service_charge_percent) / 100, 2);

            $vatAmount = $this->combinedVat(
                $branch,
                $adjustments['lines'],
                round($manualDiscount + $couponDiscount, 2),
                $serviceCharge,
            );

            $vatBase = round($afterDiscount + $extraTotal + $serviceCharge, 2);

            $fee = $type === OrderType::Delivery ? round((float) ($deliveryFee ?? 0), 2) : 0.0;

            $rawTotal = ($branch->vat_inclusive
                ? $vatBase
                : round($vatBase + $vatAmount, 2)) + $fee;

            $total = (int) round($rawTotal);
            $rounding = round($total - $rawTotal, 2);

            $foodCost = round($items->sum(fn (array $item) => (float) $item['line_cost']), 4);

            $terminal = strtoupper((string) ($terminalCode ?? 'T01'));

            $order = Order::query()->create([
                'branch_id' => $branch->id,
                'invoice_no' => is_string($invoiceNo) && $invoiceNo !== ''
                    ? $this->invoices->claim($branch, $terminal, $invoiceNo)
                    : $this->invoices->next($branch, $terminal),
                'order_type' => $type->value,
                'channel' => $channel->value,
                'restaurant_table_id' => $type === OrderType::DineIn ? $tableId : null,
                'waiter_id' => $waiter?->id,
                'cashier_id' => $cashier?->id,
                'customer_id' => $customer?->id,
                'shift_id' => $shift?->id,
                'status' => OrderStatus::Pending->value,
                'subtotal' => $subtotal,
                'discount_type' => $discount['type'] ?? null,
                'discount_value' => $discount['value'] ?? 0,
                'coupon_id' => $coupon?->id,
                'discount_amount' => $discountAmount,
                'service_charge' => $serviceCharge,
                'extra_charge' => $extraTotal,
                'vat_amount' => $vatAmount,
                'delivery_fee' => $fee,
                'rounding' => $rounding,
                'total' => $total,
                'food_cost' => $foodCost,
                'client_id' => $clientId,
                'note' => $note,
                'delivery_zone_id' => $type === OrderType::Delivery ? $deliveryZoneId : null,
                'delivery_address' => $type === OrderType::Delivery ? $deliveryAddress : null,
                'scheduled_for' => $scheduledFor,
                'tracking_token' => bin2hex(random_bytes(20)),
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'menu_item_id' => $item['menu_item_id'],
                    'variant_id' => $item['variant_id'],
                    'name' => $item['name'],
                    'name_bn' => $item['name_bn'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'add_ons' => $item['add_ons'],
                    'add_ons_total' => $item['add_ons_total'],
                    'line_total' => $item['line_total'],
                    'line_cost' => $item['line_cost'],
                ]);
            }

            if ($coupon !== null) {
                app(CouponService::class)->redeem($coupon, $order);
            }

            if ($type === OrderType::DineIn && $tableId !== null) {
                RestaurantTable::query()->whereKey($tableId)->update(['status' => 'occupied']);
            }

            try {
                $this->deductStock($branch, $order);
            } catch (InsufficientStockException $exception) {
                throw ValidationException::withMessages([
                    'items' => __('inventory.insufficient_stock', ['ingredient' => $exception->ingredientId]),
                ]);
            }

            return $order->load('items');
        });
    }

    /**
     * Price a line from the current menu: variant price or base price,
     * add-on prices, and the recipe food cost snapshot.
     *
     * @param  OrderLineInput  $line
     * @return array{menu_item_id: int, variant_id: ?int, name: string, name_bn: ?string, unit_price: string, quantity: int, add_ons: ?array, add_ons_total: string, line_total: string, line_cost: string}
     */
    private function resolveLine(Branch $branch, array $line): array
    {
        $item = MenuItem::query()
            ->where('branch_id', $branch->id)
            ->with(['variants', 'addOns'])
            ->find((int) $line['menu_item_id']);

        if ($item === null) {
            throw ValidationException::withMessages([
                'items' => __('orders.item_not_available'),
            ]);
        }

        $variant = null;

        if ($item->variants->isNotEmpty()) {
            $variantId = $line['variant_id'] ?? null;

            $variant = $variantId !== null
                ? $item->variants->firstWhere('id', (int) $variantId)
                : $item->variants->firstWhere('is_default', true) ?? $item->variants->first();

            if ($variant === null) {
                throw ValidationException::withMessages([
                    'items' => __('orders.variant_required'),
                ]);
            }
        }

        $unitPrice = $variant !== null ? (string) $variant->price : (string) $item->base_price;

        $addOnsPayload = [];
        $addOnsTotal = 0.0;
        $addOnQty = [];

        foreach ((array) ($line['add_ons'] ?? []) as $requested) {
            $addOn = $item->addOns->firstWhere('id', (int) $requested['id']);

            if ($addOn === null || ! $addOn->is_active) {
                continue;
            }

            $qty = max(1, (int) ($requested['qty'] ?? 1));
            $addOnQty[$addOn->id] = ($addOnQty[$addOn->id] ?? 0) + $qty;

            $addOnsPayload[] = [
                'id' => $addOn->id,
                'name' => $addOn->name,
                'name_bn' => $addOn->name_bn,
                'price' => (string) $addOn->price,
                'qty' => $qty,
            ];

            $addOnsTotal += ((float) $addOn->price) * $qty;
        }

        $quantity = max(1, (int) $line['quantity']);

        $lineTotal = round(((float) $unitPrice * $quantity) + ($addOnsTotal * $quantity), 2);

        $lineCost = $this->recipeCost($branch, $item) * $quantity;

        return [
            'menu_item_id' => $item->id,
            'variant_id' => $variant?->id,
            'name' => $item->name,
            'name_bn' => $item->name_bn,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'add_ons' => $addOnsPayload ?: null,
            'add_ons_total' => number_format($addOnsTotal, 2, '.', ''),
            'line_total' => number_format($lineTotal, 2, '.', ''),
            'line_cost' => number_format(round($lineCost, 4), 4, '.', ''),
        ];
    }

    private function recipeCost(Branch $branch, MenuItem $item): float
    {
        $recipe = Recipe::query()->with('items')->where('menu_item_id', $item->id)->first();

        if ($recipe === null) {
            return 0.0;
        }

        $stocks = IngredientStock::query()
            ->where('branch_id', $branch->id)
            ->whereIn('ingredient_id', $recipe->items->pluck('ingredient_id'))
            ->pluck('avg_cost', 'ingredient_id');

        return round($recipe->items->sum(function ($line) use ($stocks): float {
            return (float) $line->qty * (float) ($stocks[$line->ingredient_id] ?? 0);
        }), 4);
    }

    private function deductStock(Branch $branch, Order $order): void
    {
        $order->loadMissing('items');

        $recipes = Recipe::query()
            ->with('items')
            ->whereIn('menu_item_id', $order->items->pluck('menu_item_id'))
            ->get()
            ->keyBy('menu_item_id');

        foreach ($order->items as $orderItem) {
            $recipe = $recipes->get($orderItem->menu_item_id);

            if ($recipe === null) {
                continue;
            }

            foreach ($recipe->items as $line) {
                $this->stock->adjust(
                    branchId: $branch->id,
                    ingredientId: $line->ingredient_id,
                    baseQtyDelta: -((float) $line->qty * $orderItem->quantity),
                    movementType: 'sale',
                    reference: $order,
                    userId: $order->cashier_id,
                );
            }
        }
    }

    /**
     * @param  Collection<int, OrderLineInput>  $lines
     * @param  Collection<int, float>  $grosses
     * @return array{discount: float, extra: float, lines: array<int, array{net: float, vat_percent: float|null}>}
     */
    private function lineAdjustments(Collection $lines, Collection $grosses): array
    {
        $discount = 0.0;
        $extra = 0.0;
        $priced = [];

        foreach ($grosses as $index => $gross) {
            $input = $lines[$index];
            $lineDiscount = $this->lineDiscountAmount($input, $gross);
            $lineExtra = round(max((float) ($input['extra_charge'] ?? 0), 0), 2);
            $vatPercent = array_key_exists('vat_percent', $input) && $input['vat_percent'] !== null && $input['vat_percent'] !== ''
                ? (float) $input['vat_percent']
                : null;

            $discount += $lineDiscount;
            $extra += $lineExtra;
            $priced[] = [
                'net' => round($gross - $lineDiscount + $lineExtra, 2),
                'vat_percent' => $vatPercent,
            ];
        }

        return [
            'discount' => round($discount, 2),
            'extra' => round($extra, 2),
            'lines' => $priced,
        ];
    }

    /**
     * @param  OrderLineInput  $line
     */
    private function lineDiscountAmount(array $line, float $gross): float
    {
        $discount = $line['discount'] ?? null;

        if (! is_array($discount) || (float) ($discount['value'] ?? 0) <= 0) {
            return 0.0;
        }

        $amount = ($discount['type'] ?? 'percent') === 'fixed'
            ? (float) $discount['value']
            : $gross * ((float) $discount['value']) / 100;

        return round(min(max($amount, 0), $gross), 2);
    }

    /**
     * @param  array<int, array{net: float, vat_percent: float|null}>  $lines
     */
    private function combinedVat(Branch $branch, array $lines, float $billDiscount, float $serviceCharge): float
    {
        $netTotal = array_sum(array_column($lines, 'net'));
        $vat = 0.0;

        foreach ($lines as $line) {
            $share = $netTotal > 0 ? ($line['net'] / $netTotal) * $billDiscount : 0.0;
            $taxable = max(0, $line['net'] - $share);
            $vat += $this->vatOn($taxable, $line['vat_percent'] ?? (float) $branch->vat_percent, (bool) $branch->vat_inclusive);
        }

        $vat += $this->vatOn($serviceCharge, (float) $branch->vat_percent, (bool) $branch->vat_inclusive);

        return round($vat, 2);
    }

    private function vatOn(float $amount, float $rate, bool $inclusive): float
    {
        if ($amount <= 0 || $rate <= 0) {
            return 0.0;
        }

        if ($inclusive) {
            return $amount - ($amount * 100 / (100 + $rate));
        }

        return $amount * $rate / 100;
    }

    /**
     * @param  DiscountInput  $discount
     */
    private function discountAmount(?array $discount, float $subtotal): float
    {
        if ($discount === null || (float) $discount['value'] <= 0) {
            return 0.0;
        }

        $amount = $discount['type'] === 'percent'
            ? round($subtotal * ((float) $discount['value']) / 100, 2)
            : round((float) $discount['value'], 2);

        return min($amount, $subtotal);
    }
}
