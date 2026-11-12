<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Actions\Sales\ApproveOrderVoidAction;
use App\Actions\Sales\CollectDueAction;
use App\Actions\Sales\PayOrderAction;
use App\Actions\Sales\RequestOrderVoidAction;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Http\Controllers\Api\Concerns\InteractsWithApi;
use App\Http\Controllers\Api\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Preorder;
use App\Models\RestaurantTable;
use App\Models\Setting;
use App\Models\VoidRequest;
use App\Services\PosSettings;
use App\Services\PreorderService;
use App\Support\PreorderInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Due sales, one-step order delete and menu-book pre-orders for the mobile app. Each action calls
 * the same Action or Service the web panel uses, so the rules cannot drift.
 */
class SalesApiController extends Controller
{
    use InteractsWithApi;

    /** What each pre-order button does. */
    private const PREORDER_TRANSITIONS = [
        'complete' => Preorder::COMPLETED,
        'cancel' => Preorder::CANCELLED,
    ];

    /**
     * Website, QR and delivery platform orders that arrived after the given order id, so the app can
     * chime like the web POS. Works without websockets.
     */
    public function incomingOrders(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'orders.create');
        $branchModel = $this->branchOrFail($request, $branch);
        $after = (int) $request->integer('after');

        $query = Order::query()
            ->where('branch_id', $branchModel->id)
            ->where('channel', '!=', OrderChannel::Pos->value);

        if ($after === 0) {
            return $this->success(['last_id' => (int) $query->max('id'), 'orders' => []]);
        }

        $orders = $query->where('id', '>', $after)
            ->where('status', OrderStatus::Pending->value)
            ->with(['aggregatorChannel:id,key,name', 'table:id,name'])
            ->orderBy('id')
            ->limit(20)
            ->get();

        return $this->success([
            'last_id' => (int) max($after, $orders->max('id') ?? 0),
            'orders' => $orders->map(fn (Order $order): array => [
                'id' => $order->id,
                'invoice_no' => $order->invoice_no,
                'channel' => $order->channel->value,
                'total' => (string) $order->total,
                'aggregator' => $order->aggregatorChannel?->name,
                'table' => $order->table?->name,
            ])->all(),
        ]);
    }

    public function dues(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'orders.view');
        $branchModel = $this->branchOrFail($request, $branch);

        $orders = Order::query()
            ->where('branch_id', $branchModel->id)
            ->where('is_due', true)
            ->when(! Setting::showsDeletedItems(), fn ($query) => $query->where('status', '!=', OrderStatus::Void->value))
            ->with(['customer:id,name,mobile', 'table:id,name', 'items', 'payments'])
            ->orderBy('paid_at')
            ->get();

        $rows = $orders->map(function (Order $order): array {
            $paid = $order->amountPaid();
            $deleted = $order->status === OrderStatus::Void;

            return $this->orderPayload($order) + [
                'is_due' => true,
                'deleted' => $deleted,
                'paid' => number_format($paid, 2, '.', ''),
                'balance' => number_format($deleted ? 0.0 : max(0.0, round((float) $order->total - $paid, 2)), 2, '.', ''),
                'customer_name' => $order->customer?->name,
                'sold_at' => $order->paid_at?->toIso8601String(),
                'collections' => $order->payments
                    ->where('is_due_collection', true)
                    ->where('status', Payment::STATUS_COMPLETED)
                    ->map(fn (Payment $payment): array => [
                        'method' => $payment->method,
                        'amount' => (string) $payment->amount,
                        'at' => $payment->created_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            ];
        })->values();

        return $this->success([
            'dues' => $rows->all(),
            'outstanding' => number_format((float) $rows->sum(fn (array $row): float => (float) $row['balance']), 2, '.', ''),
            'methods' => $this->collectableMethods($branchModel),
        ]);
    }

    public function collectDue(Request $request, string $branch, Order $order, CollectDueAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'orders.create');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($order->branch_id === $branchModel->id, 404);

        return $this->rememberIdempotent($request, 'staff-due-collect', function () use ($request, $order, $action, $user, $branchModel): JsonResponse {
            $validated = $request->validate([
                'method' => ['required', 'string'],
                'amount' => ['required', 'numeric', 'gt:0'],
                'reference' => ['nullable', 'string', 'max:100'],
            ]);

            if (! in_array($validated['method'], PosSettings::enabledMethods($branchModel), true)) {
                throw ValidationException::withMessages(['method' => __('payments.method_disabled')]);
            }

            $action->execute($order, $validated['method'], (float) $validated['amount'], $validated['reference'] ?? null, $user);

            return $this->success($this->orderPayload($order->fresh()) + [
                'balance' => number_format($order->fresh()->balanceDue(), 2, '.', ''),
            ], __('orders.due_collected'));
        });
    }

    /**
     * Put what is left of an order on due. The customer is picked by mobile number when the order
     * has none yet, as on the web.
     */
    public function dueSale(Request $request, string $branch, Order $order, PayOrderAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'orders.create');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($order->branch_id === $branchModel->id, 404);

        return $this->rememberIdempotent($request, 'staff-due-sale', function () use ($request, $order, $action, $user, $branchModel): JsonResponse {
            $validated = $request->validate([
                'lines' => ['nullable', 'array'],
                'lines.*.method' => ['required', 'string'],
                'lines.*.amount' => ['required', 'numeric', 'gt:0'],
                'lines.*.reference' => ['nullable', 'string', 'max:100'],
                'customer_mobile' => ['nullable', 'string', 'max:20'],
                'customer_name' => ['nullable', 'string', 'max:100'],
            ]);

            if ($order->customer_id === null && filled($validated['customer_mobile'] ?? null)) {
                $customer = Customer::query()->firstOrCreate(
                    ['mobile' => normalize_bd_mobile($validated['customer_mobile'])],
                    ['name' => trim((string) ($validated['customer_name'] ?? '')) ?: __('orders.walk_in_customer')],
                );

                $order->update(['customer_id' => $customer->id]);
            }

            $enabled = PosSettings::enabledMethods($branchModel);

            foreach ($validated['lines'] ?? [] as $line) {
                if (! in_array($line['method'], $enabled, true)) {
                    throw ValidationException::withMessages(['due' => __('payments.method_disabled')]);
                }
            }

            $action->executeDue($order, $validated['lines'] ?? [], $user);

            return $this->success($this->orderPayload($order->fresh()) + [
                'balance' => number_format($order->fresh()->balanceDue(), 2, '.', ''),
            ], __('orders.due_recorded', ['due' => number_format($order->fresh()->balanceDue(), 2)]));
        });
    }

    /**
     * Delete an order in one step: the reason is recorded and the order is voided at once for
     * staff who may void. A pending void request is approved instead of making a second one.
     */
    public function deleteOrder(Request $request, string $branch, Order $order, RequestOrderVoidAction $requestVoid, ApproveOrderVoidAction $approveVoid): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'orders.void');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($order->branch_id === $branchModel->id, 404);

        $validated = $request->validate([
            'reason_code' => ['required', 'string', Rule::in(VoidRequest::REASONS)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($order, $user, $validated, $requestVoid, $approveVoid): void {
            $pending = $order->voidRequest()->where('status', 'pending')->first();

            $pending ??= $requestVoid->execute($order, $user, (string) $validated['reason_code'], $validated['note'] ?? null);

            $approveVoid->execute($pending, $user);
        });

        return $this->success($this->orderPayload($order->fresh()), __('orders.deleted'));
    }

    public function preorders(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'preorders.view');
        $branchModel = $this->branchOrFail($request, $branch);

        $preorders = Preorder::query()
            ->where('branch_id', $branchModel->id)
            ->when($request->input('filter.status'), fn ($query, $status) => $query->where('status', $status))
            ->with('table:id,name')
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (Preorder $preorder): array => $this->preorderPayload($preorder))
            ->values();

        return $this->success([
            'preorders' => $preorders->all(),
            'share_url' => route('web.preorder', ['branchCode' => strtolower($branchModel->code)]),
            'can_manage' => $user->can('preorders.manage'),
        ]);
    }

    public function storePreorder(Request $request, string $branch, PreorderService $service): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'preorders.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        return $this->rememberIdempotent($request, 'staff-preorder', function () use ($request, $service, $user, $branchModel): JsonResponse {
            $validated = $request->validate(PreorderInput::rules($branchModel->id, withTable: true), [
                'items.required' => __('preorders.cart_empty'),
                'items.min' => __('preorders.cart_empty'),
            ]);

            $preorder = $service->place($branchModel, $validated['items'], PreorderInput::details($validated), Preorder::SOURCE_STAFF, $user->id);

            activity()->performedOn($preorder)->causedBy($user)->log('preorders.created');

            return $this->success($this->preorderPayload($preorder->load('table:id,name')), __('preorders.placed'), 201);
        });
    }

    public function transitionPreorder(Request $request, string $branch, Preorder $preorder, string $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'preorders.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($preorder->branch_id === $branchModel->id, 404);
        abort_unless(isset(self::PREORDER_TRANSITIONS[$action]), 404);

        if ($preorder->status !== Preorder::PENDING) {
            throw ValidationException::withMessages(['status' => __('preorders.cannot_change')]);
        }

        $status = self::PREORDER_TRANSITIONS[$action];
        $preorder->update(['status' => $status]);

        activity()->performedOn($preorder)->causedBy($user)->log('preorders.'.$status);

        return $this->success($this->preorderPayload($preorder->fresh('table')), __('preorders.status_'.$status.'_done'));
    }

    /**
     * The menu and tables a pre-order form needs, besides the menu the POS already loads.
     */
    public function preorderTables(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'preorders.view');
        $branchModel = $this->branchOrFail($request, $branch);

        return $this->success(RestaurantTable::query()
            ->where('branch_id', $branchModel->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'area', 'seats'])
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function preorderPayload(Preorder $preorder): array
    {
        return [
            'id' => $preorder->id,
            'order_no' => $preorder->order_no,
            'status' => $preorder->status,
            'source' => $preorder->source,
            'customer_name' => $preorder->customer_name,
            'customer_mobile' => $preorder->customer_mobile,
            'address' => $preorder->address,
            'party_size' => $preorder->party_size,
            'expected_at' => $preorder->expected_at?->toIso8601String(),
            'table' => $preorder->table?->name,
            'note' => $preorder->note,
            'items' => $preorder->items,
            'item_count' => $preorder->itemCount(),
            'total' => (string) $preorder->total,
            'advance_amount' => (string) $preorder->advance_amount,
            'balance_due' => number_format($preorder->balanceDue(), 2, '.', ''),
            'sms_sent' => $preorder->sms_sent_at !== null,
            'created_at' => $preorder->created_at->toIso8601String(),
            'print_url' => route('admin.preorders.print', ['preorder' => $preorder->id]),
            'slip_url' => route('web.preorder.slip', ['token' => $preorder->token]),
        ];
    }

    /**
     * Payment methods a due can be collected with: enabled for the branch, not a manual wallet.
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectableMethods(Branch $branch): array
    {
        return collect(PosSettings::for($branch)['payment_methods'])
            ->filter(fn (array $entry): bool => $entry['enabled'] && $entry['type'] !== 'wallet')
            ->values()
            ->all();
    }
}
