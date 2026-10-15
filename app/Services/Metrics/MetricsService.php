<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Delivery;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\LoyaltyEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\VoidRequest;
use App\Models\Wastage;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The single source of truth for every dashboard and report number.
 * Dashboard totals equal report totals because both read from here.
 */
class MetricsService
{
    /**
     * @return array{gross_sales: string, net_sales: string, discount: string, vat: string, service_charge: string, food_cost: string, wastage_cost: string, salary_cost: string, expenses: string, commission: string, net_profit: string, orders: int, order_items: int}
     */
    public function summary(?int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $orderRow = DB::table('orders')
            ->where('status', '!=', OrderStatus::Void->value)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(total), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as subtotal_sum')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) as discount_sum')
            ->selectRaw('COALESCE(SUM(vat_amount), 0) as vat_sum')
            ->selectRaw('COALESCE(SUM(service_charge), 0) as service_sum')
            ->selectRaw('COALESCE(SUM(food_cost), 0) as food_cost_sum')
            ->selectRaw('COALESCE(SUM(commission_amount), 0) as commission_sum')
            ->first();

        $orderItems = (int) Order::query()
            ->where('orders.status', '!=', OrderStatus::Void->value)
            ->when($branchId, fn ($query) => $query->where('orders.branch_id', $branchId))
            ->whereBetween('orders.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->count();

        $wastageCost = (float) Wastage::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->sum('cost');

        $days = (float) $from->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;

        $monthlySalary = (float) Employee::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('is_active', true)
            ->sum('monthly_salary');

        $salaryCost = round($monthlySalary / 30 * $days, 2);

        $expenses = (float) Expense::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('expense_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->sum('amount');

        $gross = (float) ($orderRow->gross_sales ?? 0);
        $net = (float) ($orderRow->subtotal_sum ?? 0) - (float) ($orderRow->discount_sum ?? 0);
        $foodCost = (float) ($orderRow->food_cost_sum ?? 0);
        $commission = (float) ($orderRow->commission_sum ?? 0);

        $netProfit = $net - $foodCost - $wastageCost - $salaryCost - $expenses - $commission;

        return [
            'gross_sales' => number_format($gross, 2, '.', ''),
            'net_sales' => number_format($net, 2, '.', ''),
            'discount' => number_format((float) ($orderRow->discount_sum ?? 0), 2, '.', ''),
            'vat' => number_format((float) ($orderRow->vat_sum ?? 0), 2, '.', ''),
            'service_charge' => number_format((float) ($orderRow->service_sum ?? 0), 2, '.', ''),
            'food_cost' => number_format($foodCost, 2, '.', ''),
            'wastage_cost' => number_format($wastageCost, 2, '.', ''),
            'salary_cost' => number_format($salaryCost, 2, '.', ''),
            'expenses' => number_format($expenses, 2, '.', ''),
            'commission' => number_format($commission, 2, '.', ''),
            'net_profit' => number_format($netProfit, 2, '.', ''),
            'orders' => (int) ($orderRow->orders_count ?? 0),
            'order_items' => $orderItems,
        ];
    }

    /**
     * @return array<int, array{date: string, total: string, orders: int}>
     */
    public function salesByDay(?int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = DB::table('orders')
            ->where('status', '!=', OrderStatus::Void->value)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupBy('day')
            ->orderBy('day')
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('SUM(total) as total')
            ->selectRaw('COUNT(*) as orders_count')
            ->get();

        return $rows->map(fn ($row) => [
            'date' => (string) $row->day,
            'total' => number_format((float) $row->total, 2, '.', ''),
            'orders' => (int) $row->orders_count,
        ])->all();
    }

    /**
     * @return array<int, array{channel: string, total: string, orders: int}>
     */
    public function salesByChannel(?int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = DB::table('orders')
            ->where('status', '!=', OrderStatus::Void->value)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupBy('channel')
            ->selectRaw('channel')
            ->selectRaw('SUM(total) as total')
            ->selectRaw('COUNT(*) as orders_count')
            ->get();

        return $rows->map(fn ($row) => [
            'channel' => (string) $row->channel,
            'total' => number_format((float) $row->total, 2, '.', ''),
            'orders' => (int) $row->orders_count,
        ])->all();
    }

    /**
     * @return array<int, array{name: string, qty: int, revenue: string}>
     */
    public function topItems(?int $branchId, CarbonInterface $from, CarbonInterface $to, int $limit = 8): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', OrderStatus::Void->value)
            ->when($branchId, fn ($query) => $query->where('orders.branch_id', $branchId))
            ->whereBetween('orders.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupBy('order_items.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->selectRaw('order_items.name as name')
            ->selectRaw('SUM(order_items.quantity) as qty')
            ->selectRaw('SUM(order_items.line_total) as revenue')
            ->get();

        return $rows->map(fn ($row) => [
            'name' => (string) $row->name,
            'qty' => (int) $row->qty,
            'revenue' => number_format((float) $row->revenue, 2, '.', ''),
        ])->all();
    }

    /**
     * @return array<int, array{branch_id: int, code: string, name: string, gross_sales: string, net_sales: string, food_cost: string, wastage_cost: string, salary_cost: string, expenses: string, net_profit: string, orders: int}>
     */
    public function branchComparison(CarbonInterface $from, CarbonInterface $to): array
    {
        return Branch::query()
            ->orderBy('code')
            ->get()
            ->map(fn (Branch $branch) => [
                'branch_id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                ...collect($this->summary($branch->id, $from, $to))
                    ->only(['gross_sales', 'net_sales', 'food_cost', 'wastage_cost', 'salary_cost', 'expenses', 'net_profit', 'orders'])
                    ->all(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, stock_qty: string, reorder_level: string, unit_symbol: string}>
     */
    public function lowStock(?int $branchId): array
    {
        $rows = DB::table('ingredient_stocks')
            ->join('ingredients', 'ingredients.id', '=', 'ingredient_stocks.ingredient_id')
            ->join('units', 'units.id', '=', 'ingredients.unit_id')
            ->when($branchId, fn ($query) => $query->where('ingredient_stocks.branch_id', $branchId))
            ->where('ingredients.is_active', true)
            ->where('ingredients.reorder_level', '>', 0)
            ->whereColumn('ingredient_stocks.stock_qty', '<=', 'ingredients.reorder_level')
            ->orderBy('ingredients.name')
            ->select('ingredient_stocks.*', 'ingredients.name as ingredient_name', 'ingredients.reorder_level', 'units.symbol as unit_symbol')
            ->get();

        return $rows->map(fn ($row) => [
            'id' => (int) $row->ingredient_id,
            'name' => (string) $row->ingredient_name,
            'stock_qty' => (string) (0 + (float) $row->stock_qty),
            'reorder_level' => (string) (0 + (float) $row->reorder_level),
            'unit_symbol' => (string) $row->unit_symbol,
        ])->all();
    }

    public function wastageSpike(?int $branchId, ?float $thresholdMultiplier = null): ?array
    {
        $thresholdMultiplier ??= (float) Setting::current()->wastage_spike_multiplier;
        $todayFrom = now()->startOfDay();
        $todayTo = now()->endOfDay();

        $today = (float) Wastage::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('created_at', [$todayFrom, $todayTo])
            ->sum('cost');

        $weekAvg = (float) Wastage::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('created_at', [now()->subDays(7)->startOfDay(), now()->subDay()->endOfDay()])
            ->sum('cost') / 7;

        if ($weekAvg <= 0 || $today < $weekAvg * $thresholdMultiplier) {
            return null;
        }

        return [
            'today' => number_format($today, 2, '.', ''),
            'week_average' => number_format($weekAvg, 2, '.', ''),
        ];
    }

    /**
     * Sales summary table rows for the sales report.
     *
     * @return array<int, array{date: string, orders: int, gross: string, discount: string, net: string, vat: string, total: string}>
     */
    public function salesByDayDetailed(?int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = DB::table('orders')
            ->where('status', '!=', OrderStatus::Void->value)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupBy('day')
            ->orderBy('day')
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(total) as gross')
            ->selectRaw('SUM(discount_amount) as discount_sum')
            ->selectRaw('SUM(subtotal - discount_amount) as net_sum')
            ->selectRaw('SUM(vat_amount) as vat_sum')
            ->get();

        return $rows->map(fn ($row) => [
            'date' => (string) $row->day,
            'orders' => (int) $row->orders_count,
            'gross' => number_format((float) $row->gross, 2, '.', ''),
            'discount' => number_format((float) $row->discount_sum, 2, '.', ''),
            'net' => number_format((float) $row->net_sum, 2, '.', ''),
            'vat' => number_format((float) $row->vat_sum, 2, '.', ''),
            'total' => number_format((float) $row->gross, 2, '.', ''),
        ])->all();
    }

    /**
     * @return array<int, array{method: string, amount: string, payments: int}>
     */
    public function salesByPaymentMethod(?int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('payments.status', Payment::STATUS_COMPLETED)
            ->where('orders.status', '!=', OrderStatus::Void->value)
            ->when($branchId, fn ($query) => $query->where('orders.branch_id', $branchId))
            ->whereBetween('payments.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupBy('payments.method')
            ->orderBy('payments.method')
            ->selectRaw('payments.method as method')
            ->selectRaw('SUM(payments.amount) as amount')
            ->selectRaw('COUNT(*) as payments_count')
            ->get();

        return $rows->map(fn ($row) => [
            'method' => (string) $row->method,
            'amount' => number_format((float) $row->amount, 2, '.', ''),
            'payments' => (int) $row->payments_count,
        ])->all();
    }

    /**
     * @return array<int, array{cashier: string, orders: int, gross: string, discount: string}>
     */
    public function salesByCashier(?int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = DB::table('orders')
            ->leftJoin('users', 'users.id', '=', 'orders.cashier_id')
            ->where('orders.status', '!=', OrderStatus::Void->value)
            ->when($branchId, fn ($query) => $query->where('orders.branch_id', $branchId))
            ->whereBetween('orders.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupBy('orders.cashier_id', 'users.name')
            ->orderBy('users.name')
            ->selectRaw('COALESCE(users.name, ?) as cashier', [__('reports.unassigned')])
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(orders.total) as gross')
            ->selectRaw('SUM(orders.discount_amount) as discount_sum')
            ->get();

        return $rows->map(fn ($row) => [
            'cashier' => (string) $row->cashier,
            'orders' => (int) $row->orders_count,
            'gross' => number_format((float) $row->gross, 2, '.', ''),
            'discount' => number_format((float) $row->discount_sum, 2, '.', ''),
        ])->all();
    }

    /**
     * @return array<int, array{kind: string, reference: string, amount: string, reason: string}>
     */
    public function discountsAndVoids(?int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $fromAt = $from->copy()->startOfDay();
        $toAt = $to->copy()->endOfDay();

        $discounts = Order::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('status', '!=', OrderStatus::Void)
            ->where('discount_amount', '>', 0)
            ->whereBetween('created_at', [$fromAt, $toAt])
            ->get()
            ->map(fn (Order $order) => [
                'kind' => __('reports.discount'),
                'reference' => $order->invoice_no,
                'amount' => number_format((float) $order->discount_amount, 2, '.', ''),
                'reason' => (string) ($order->discount_type ?? ''),
            ]);

        $voids = VoidRequest::query()
            ->with('order:id,invoice_no,branch_id,total')
            ->where('status', 'approved')
            ->whereBetween('decided_at', [$fromAt, $toAt])
            ->get()
            ->filter(fn (VoidRequest $request) => $branchId === null || $request->order?->branch_id === $branchId)
            ->map(function (VoidRequest $request): array {
                $order = $request->order;

                return [
                    'kind' => __('reports.void'),
                    'reference' => $order->invoice_no,
                    'amount' => number_format((float) $order->total, 2, '.', ''),
                    'reason' => (string) $request->reason_code,
                ];
            });

        return $discounts->concat($voids)->values()->all();
    }

    /**
     * @return array<int, array{rider: string, deliveries: int, delivered: int, failed: int, avg_minutes: string}>
     */
    public function deliveryPerformance(?int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $deliveries = Delivery::query()
            ->with('rider.user:id,name')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereHas('order', function ($query) use ($branchId): void {
                if ($branchId !== null) {
                    $query->where('branch_id', $branchId);
                }
            })
            ->get()
            ->groupBy(fn (Delivery $delivery) => (string) ($delivery->rider_id ?? '0'));

        return $deliveries->map(function ($group): array {
            $minutes = $group
                ->filter(fn (Delivery $delivery) => $delivery->delivered_at !== null && $delivery->assigned_at !== null)
                ->map(fn (Delivery $delivery) => $delivery->assigned_at->diffInMinutes($delivery->delivered_at));

            $rider = $group->first()?->rider?->user->name ?? __('reports.unassigned');

            return [
                'rider' => $rider,
                'deliveries' => $group->count(),
                'delivered' => $group->where('status', Delivery::STATUS_DELIVERED)->count(),
                'failed' => $group->where('status', Delivery::STATUS_FAILED)->count(),
                'avg_minutes' => $minutes->isEmpty() ? '0' : number_format($minutes->avg(), 0, '.', ''),
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array{type: string, entries: int, points: int}>
     */
    public function loyaltySummary(?int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = LoyaltyEntry::query()
            ->when($branchId !== null, function ($query) use ($branchId): void {
                $query->whereHas('order', fn ($order) => $order->where('branch_id', $branchId));
            })
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('type')
            ->selectRaw('COUNT(*) as entries_count')
            ->selectRaw('SUM(points) as points_sum')
            ->groupBy('type')
            ->orderBy('type')
            ->get();

        return $rows->map(fn (LoyaltyEntry $row): array => [
            'type' => (string) $row->getAttribute('type'),
            'entries' => (int) $row->getAttribute('entries_count'),
            'points' => (int) $row->getAttribute('points_sum'),
        ])->all();
    }
}
