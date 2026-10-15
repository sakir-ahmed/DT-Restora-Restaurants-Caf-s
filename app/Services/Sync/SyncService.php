<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Actions\Sales\CreateOrderAction;
use App\Actions\Sales\PayOrderAction;
use App\Enums\OrderType;
use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\DataVersion;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\SyncItem;
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
    private function applyPayment(User $user, array $payload): void
    {
        $order = Order::query()->findOrFail((int) ($payload['order_id'] ?? 0));

        $this->payments->executeCash($order, (float) ($payload['tendered'] ?? 0), $user);
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
            'branch' => $branch->only(['id', 'name', 'code', 'vat_percent', 'vat_inclusive', 'service_charge_percent']),
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
