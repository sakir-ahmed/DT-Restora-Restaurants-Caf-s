<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Actions\Inventory\CloseStockCountAction;
use App\Actions\Inventory\LogWastageAction;
use App\Actions\Sales\CloseShiftAction;
use App\Actions\Sales\CreateOrderAction;
use App\Actions\Sales\OpenShiftAction;
use App\Actions\Sales\PayOrderAction;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\DataVersion;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\InvoiceSequence;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Shift;
use App\Models\StockCount;
use App\Models\SyncItem;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Offline-first sync engine. The POS PWA queues writes locally with a
 * client_id (ULID) and pushes them here; every item is processed
 * idempotently by that id, in device order, and one failing item
 * never blocks the others.
 */
class SyncService
{
    public function __construct(
        private readonly CreateOrderAction $orders,
        private readonly PayOrderAction $payments,
        private readonly OpenShiftAction $openShifts,
        private readonly CloseShiftAction $closeShifts,
        private readonly LogWastageAction $wastage,
        private readonly CloseStockCountAction $closeCounts,
    ) {}

    /**
     * @return array<string, array{id: string, status: string, reason: ?string}>
     */
    public function push(User $user, array $items): array
    {
        $results = [];

        foreach ($items as $item) {
            $clientId = (string) ($item['client_id'] ?? '');

            if ($clientId === '') {
                continue;
            }

            try {
                $results[$clientId] = $this->process($user, $clientId, $item);
            } catch (\Throwable $exception) {
                report($exception);

                $results[$clientId] = [
                    'id' => $clientId,
                    'status' => SyncItem::STATUS_CONFLICT,
                    'reason' => $this->reasonFor($exception),
                ];

                SyncItem::query()->create([
                    'client_id' => $clientId,
                    'type' => (string) ($item['type'] ?? 'unknown'),
                    'payload' => $item['payload'] ?? [],
                    'data_version' => $item['data_version'] ?? null,
                    'status' => SyncItem::STATUS_CONFLICT,
                    'conflict_reason' => $this->reasonFor($exception),
                    'user_id' => $user->id,
                ]);
            }
        }

        return $results;
    }

    /**
     * @return array{id: string, status: string, reason: ?string}
     */
    private function process(User $user, string $clientId, array $item): array
    {
        return DB::transaction(function () use ($user, $clientId, $item): array {
            // Idempotency: a retry returns the earlier outcome.
            $existing = SyncItem::query()->where('client_id', $clientId)->lockForUpdate()->first();

            if ($existing !== null) {
                return [
                    'id' => $clientId,
                    'status' => $existing->status === SyncItem::STATUS_ACCEPTED ? SyncItem::STATUS_DUPLICATE : $existing->status,
                    'reason' => $existing->conflict_reason,
                ];
            }

            $type = (string) ($item['type'] ?? '');
            $payload = (array) ($item['payload'] ?? []);

            switch ($type) {
                case 'order':
                    $this->applyOrder($user, $clientId, $payload, (int) ($item['data_version'] ?? 0));
                    break;

                case 'payment':
                    $this->applyPayment($user, $payload);
                    break;

                case 'shift':
                    $this->applyShift($user, $payload);
                    break;

                case 'wastage':
                    $this->applyWastage($user, $payload);
                    break;

                case 'stock_count':
                    $this->applyStockCount($user, $payload);
                    break;

                default:
                    throw ValidationException::withMessages(['type' => 'Unknown sync type.']);
            }

            SyncItem::query()->create([
                'client_id' => $clientId,
                'type' => $type,
                'payload' => $payload,
                'data_version' => $item['data_version'] ?? null,
                'status' => SyncItem::STATUS_ACCEPTED,
                'user_id' => $user->id,
                'accepted_at' => now(),
            ]);

            return ['id' => $clientId, 'status' => SyncItem::STATUS_ACCEPTED, 'reason' => null];
        });
    }

    /**
     * Offline orders keep the device's price snapshot: the server
     * prices from the snapshot, not from the current menu.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyOrder(User $user, string $clientId, array $payload, int $dataVersion): void
    {
        $branch = Branch::query()->findOrFail((int) ($payload['branch_id'] ?? 0));

        $order = $this->orders->execute(
            branch: $branch,
            type: OrderType::from((string) ($payload['order_type'] ?? 'takeaway')),
            lines: $this->snapshotLines((array) ($payload['items'] ?? [])),
            cashier: $user,
            clientId: $clientId,
            terminalCode: (string) ($payload['terminal'] ?? 'T01'),
            invoiceNo: isset($payload['invoice_no']) ? (string) $payload['invoice_no'] : null,
        );

        if (! empty($payload['tendered'])) {
            $this->payments->executeCash($order, (float) $payload['tendered'], $user);
        }
    }

    /**
     * Price snapshots from the device: each line carries name, unit
     * price and totals that the server keeps verbatim.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function snapshotLines(array $lines): array
    {
        // The CreateOrderAction validates menu ids; offline items were
        // captured from the cached menu so ids are valid snapshots.
        return array_values(array_filter($lines, fn (array $line) => ! empty($line['menu_item_id'])));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyShift(User $user, array $payload): void
    {
        $this->assertCan($user, 'shifts.manage');

        $branch = $this->branchFor($user, $payload);
        $action = (string) ($payload['action'] ?? 'open');

        if ($action === 'close') {
            $shift = Shift::query()
                ->where('branch_id', $branch->id)
                ->where('user_id', $user->id)
                ->whereNull('closed_at')
                ->first();

            if ($shift === null) {
                throw ValidationException::withMessages([
                    'shift' => __('orders.shift_already_closed'),
                ]);
            }

            $this->closeShifts->execute($shift, (float) ($payload['counted_cash'] ?? 0));

            return;
        }

        $this->openShifts->execute(
            branch: $branch,
            cashier: $user,
            openingCash: (float) ($payload['opening_cash'] ?? 0),
            terminalCode: (string) ($payload['terminal'] ?? 'T01'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyWastage(User $user, array $payload): void
    {
        $this->assertCan($user, 'wastage.log');

        $branch = $this->branchFor($user, $payload);
        $ingredient = Ingredient::query()->findOrFail((int) ($payload['ingredient_id'] ?? 0));
        $unit = Unit::query()->findOrFail((int) ($payload['unit_id'] ?? 0));

        $this->wastage->execute(
            branch: $branch,
            ingredient: $ingredient,
            unit: $unit,
            qty: (float) ($payload['qty'] ?? 0),
            reasonCode: (string) ($payload['reason_code'] ?? ''),
            note: isset($payload['note']) ? (string) $payload['note'] : null,
            user: $user,
        );
    }

    /**
     * Opens a count when none is open, records counted quantities, and
     * closes it unless the device asked only to open.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyStockCount(User $user, array $payload): void
    {
        $this->assertCan($user, 'inventory.manage');

        $branch = $this->branchFor($user, $payload);
        $action = (string) ($payload['action'] ?? 'close');

        $count = StockCount::query()
            ->where('branch_id', $branch->id)
            ->where('status', StockCount::STATUS_OPEN)
            ->first();

        if ($count === null) {
            $count = StockCount::query()->create([
                'branch_id' => $branch->id,
                'status' => StockCount::STATUS_OPEN,
                'note' => isset($payload['note']) ? (string) $payload['note'] : null,
                'user_id' => $user->id,
            ]);

            $stocks = IngredientStock::query()
                ->where('branch_id', $branch->id)
                ->get();

            foreach ($stocks as $stock) {
                $count->items()->create([
                    'ingredient_id' => $stock->ingredient_id,
                    'system_qty' => $stock->stock_qty,
                ]);
            }
        }

        foreach ((array) ($payload['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $ingredientId = (int) ($item['ingredient_id'] ?? 0);
            $counted = $item['counted_qty'] ?? null;

            if ($ingredientId < 1 || $counted === null || $counted === '') {
                continue;
            }

            $line = $count->items()->where('ingredient_id', $ingredientId)->first();

            if ($line === null) {
                $systemQty = IngredientStock::query()
                    ->where('branch_id', $branch->id)
                    ->where('ingredient_id', $ingredientId)
                    ->value('stock_qty') ?? 0;

                $count->items()->create([
                    'ingredient_id' => $ingredientId,
                    'system_qty' => $systemQty,
                    'counted_qty' => $counted,
                ]);
            } else {
                $line->update(['counted_qty' => $counted]);
            }
        }

        if ($action === 'close') {
            $this->closeCounts->execute($count, $user);
        }
    }

    private function assertCan(User $user, string $permission): void
    {
        if (! $user->can($permission)) {
            throw ValidationException::withMessages([
                'type' => __('sync.forbidden'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function branchFor(User $user, array $payload): Branch
    {
        $branchId = (int) ($payload['branch_id'] ?? $user->branch_id ?? 0);

        if ($user->branch_id !== null && $branchId !== (int) $user->branch_id) {
            throw ValidationException::withMessages([
                'branch_id' => __('sync.branch_mismatch'),
            ]);
        }

        return Branch::query()->findOrFail($branchId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyPayment(User $user, array $payload): void
    {
        $order = $this->orderForPayment($payload);
        $method = (string) ($payload['method'] ?? 'cash');

        if ($method === 'split') {
            $this->payments->executeSplit($order, array_values((array) ($payload['lines'] ?? [])), $user);

            return;
        }

        if ($method === 'cash') {
            $this->payments->executeCash($order, (float) ($payload['tendered'] ?? $payload['amount'] ?? 0), $user);

            return;
        }

        $this->payments->recordManualWallet(
            $order,
            PaymentMethod::from($method),
            (float) ($payload['amount'] ?? $order->total),
            (string) ($payload['reference'] ?? ''),
            $user,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function orderForPayment(array $payload): Order
    {
        if (! empty($payload['order_client_id'])) {
            $order = Order::query()->where('client_id', (string) $payload['order_client_id'])->first();

            if ($order !== null) {
                return $order;
            }
        }

        $orderId = (int) ($payload['order_id'] ?? 0);

        if ($orderId > 0) {
            $order = Order::query()->find($orderId);

            if ($order !== null) {
                return $order;
            }
        }

        throw ValidationException::withMessages([
            'order' => __('sync.order_missing'),
        ]);
    }

    private function reasonFor(\Throwable $exception): string
    {
        if ($exception instanceof InsufficientStockException) {
            return 'NEGATIVE_STOCK';
        }

        if ($exception instanceof ValidationException) {
            $messages = collect($exception->errors())->flatten()->all();

            return $messages[0] ?? 'VALIDATION_FAILED';
        }

        if ($exception instanceof \InvalidArgumentException) {
            return $exception->getMessage();
        }

        return 'SERVER_ERROR';
    }

    /**
     * Full reference data snapshot for a branch plus the current
     * data_version.
     *
     * @return array<string, mixed>
     */
    public function bootstrap(User $user): array
    {
        $branchId = $user->branch_id ?? (int) session('branch_id', Branch::query()->min('id'));

        $branch = Branch::query()->with('zones')->findOrFail($branchId);

        return [
            'data_version' => DataVersion::current(),
            'branch' => $branch->only(['id', 'name', 'name_bn', 'code', 'phone', 'address', 'vat_percent', 'vat_inclusive', 'service_charge_percent', 'receipt_footer']),
            'categories' => MenuCategory::query()
                ->where('branch_id', $branch->id)
                ->active()
                ->ordered()
                ->with(['items' => fn ($query) => $query->available()->with(['variants', 'addOns'])])
                ->get(),
            'tables' => RestaurantTable::query()
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->get(['id', 'name', 'status']),
            'invoice_sequences' => InvoiceSequence::query()
                ->where('branch_id', $branch->id)
                ->whereDate('for_date', now()->toDateString())
                ->get(['terminal_code', 'last_number', 'for_date']),
        ];
    }

    /**
     * Delta updates since a version. In v1 menu changes bump the
     * version wholesale, so a changed version returns the full
     * bootstrap again; deletions are included by absence.
     *
     * @return array<string, mixed>
     */
    public function changes(User $user, int $since): array
    {
        $data = $this->bootstrap($user);

        if ($data['data_version'] === $since) {
            return ['data_version' => $since, 'changed' => false];
        }

        return ['data_version' => $data['data_version'], 'changed' => true] + $data;
    }
}
