<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Sales\ApproveOrderVoidAction;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\AggregatorChannel;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\Aggregators\AggregatorOrderService;
use App\Services\Aggregators\Foodpanda\FoodpandaClient;
use App\Services\Aggregators\PartnerCatalog;
use App\Services\Aggregators\PlatformAdapters;
use App\Services\Aggregators\UberEats\UberEatsClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AggregatorController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('aggregators.manage');

        PartnerCatalog::ensureRows();

        $channels = AggregatorChannel::query()
            ->withCount(['payouts'])
            ->orderBy('name')
            ->get()
            ->map(fn (AggregatorChannel $channel): array => $channel->toArray() + [
                'fields' => PartnerCatalog::fields($channel->key),
                'has_api' => PartnerCatalog::hasApi($channel->key),
                'logo' => config("aggregators.partners.{$channel->key}.logo") ? asset(config("aggregators.partners.{$channel->key}.logo")) : null,
            ]);

        $aggregatorOrders = Order::query()
            ->whereNotNull('aggregator_channel_id')
            ->with('aggregatorChannel:id,key,name')
            ->latest()
            ->limit(50)
            ->get();

        return Inertia::render('admin/aggregators/Index', [
            'channels' => $channels,
            'orders' => $aggregatorOrders,
            'commissionTotals' => [
                'today' => number_format((float) Order::query()->whereNotNull('aggregator_channel_id')->whereDate('created_at', today())->sum('commission_amount'), 2, '.', ''),
                'month' => number_format((float) Order::query()->whereNotNull('aggregator_channel_id')->whereMonth('created_at', now()->month)->sum('commission_amount'), 2, '.', ''),
            ],
            'webhookBase' => url('/webhooks/aggregators'),
            'branches' => Branch::query()->when(
                $request->user('staff')->branch_id !== null,
                fn ($query) => $query->whereKey($request->user('staff')->branch_id),
            )->orderBy('code')->get(['id', 'code', 'name']),
            'items' => MenuItem::query()
                ->where('branch_id', $this->currentBranch($request)->id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function updateChannel(Request $request, AggregatorChannel $channel): RedirectResponse
    {
        $this->authorize('aggregators.manage');

        $validated = $request->validate(PartnerCatalog::rules($channel->key));

        $channel->update($this->platformAttributes($validated, keepSecretWhenBlank: true));

        return back()->with('success', __('aggregators.channel_updated'));
    }

    /**
     * Switch a partner on or off.
     */
    public function toggleChannel(Request $request, AggregatorChannel $channel): RedirectResponse
    {
        $this->authorize('aggregators.manage');

        $channel->update(['is_active' => ! $channel->is_active]);

        activity()->performedOn($channel)->causedBy($request->user('staff'))->withProperties(['is_active' => $channel->is_active])->log('aggregator.toggled');

        return back()->with('success', __($channel->is_active ? 'aggregators.channel_enabled' : 'aggregators.channel_disabled', ['name' => $channel->name]));
    }

    /**
     * Manual aggregator order entry: works for every channel from day
     * one when no platform API is connected.
     */
    public function manualOrder(Request $request, AggregatorOrderService $service): RedirectResponse
    {
        $this->authorize('aggregators.manage');

        $validated = $request->validate([
            'aggregator_channel_id' => ['required', 'integer', 'exists:aggregator_channels,id'],
            'platform_order_id' => ['required', 'string', 'max:100'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_mobile' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $channel = AggregatorChannel::query()->findOrFail((int) $validated['aggregator_channel_id']);

        try {
            $service->receive($channel, $validated);
        } catch (ValidationException $exception) {
            return back()->with('error', collect($exception->errors())->flatten()->first());
        }

        return back()->with('success', __('aggregators.order_created'));
    }

    /**
     * Push an accept or reject decision back through the channel
     * adapter (real adapters only; manual channels log the decision).
     */
    public function decide(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('aggregators.manage');

        abort_unless($request->user('staff')->canAccessBranch($order->branch_id), 404);

        $validated = $request->validate([
            'decision' => ['required', 'in:accept,reject'],
            'reason' => ['nullable', 'string', 'max:60'],
        ]);

        activity()
            ->performedOn($order)
            ->causedBy($request->user('staff'))
            ->withProperties(['decision' => $validated['decision']])
            ->log('aggregator.decision');

        $channel = $order->aggregatorChannel;

        $platform = PlatformAdapters::for($channel);

        if ($platform !== null && $validated['decision'] === 'reject') {
            $pushed = $platform->pushStatus($order, 'reject', $validated['reason'] ?? null);

            if (! $pushed) {
                return back()->with('error', __('aggregators.push_failed', ['error' => (string) $channel->fresh()?->last_error]));
            }

            if ($order->status === OrderStatus::Pending) {
                app(ApproveOrderVoidAction::class)->cancelPending($order);
            }
        }

        return back()->with('success', __('aggregators.decision_pushed'));
    }

    /**
     * Payout reconciliation per channel.
     */
    public function reconcilePayout(Request $request, AggregatorChannel $channel): RedirectResponse
    {
        $this->authorize('aggregators.manage');

        $validated = $request->validate([
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'received_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $expected = (float) Order::query()
            ->where('aggregator_channel_id', $channel->id)
            ->whereBetween('created_at', [$validated['period_from'], $validated['period_to'].' 23:59:59'])
            ->sum('total');

        $netExpected = $expected - (float) Order::query()
            ->where('aggregator_channel_id', $channel->id)
            ->whereBetween('created_at', [$validated['period_from'], $validated['period_to'].' 23:59:59'])
            ->sum('commission_amount');

        $channel->payouts()->create([
            'period_from' => $validated['period_from'],
            'period_to' => $validated['period_to'],
            'expected_amount' => $netExpected,
            'received_amount' => $validated['received_amount'],
            'status' => abs((float) $validated['received_amount'] - $netExpected) < 0.01 ? 'reconciled' : 'pending',
        ]);

        return back()->with('success', __('aggregators.payout_recorded'));
    }

    /**
     * Menu availability sync (queued in real adapters).
     */
    public function syncMenu(Request $request, AggregatorChannel $channel): RedirectResponse
    {
        $this->authorize('aggregators.manage');

        $adapter = PlatformAdapters::for($channel);

        if ($adapter !== null) {
            try {
                $adapter->syncMenu($channel);
            } catch (Throwable $exception) {
                $channel->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 500)])->save();

                return back()->with('error', __('aggregators.push_failed', ['error' => $exception->getMessage()]));
            }

            activity()->performedOn($channel)->withProperties(['items' => $adapter->lastSyncCount])->log('aggregator.menu_synced');

            return back()->with('success', __('aggregators.menu_synced', ['count' => $adapter->lastSyncCount]));
        }

        $branchId = $this->currentBranch($request)->id;

        $count = MenuItem::query()
            ->where('branch_id', $branchId)
            ->where('is_available', true)
            ->count();

        activity()->performedOn($channel)->withProperties(['items' => $count])->log('aggregator.menu_synced');

        return back()->with('success', __('aggregators.menu_synced', ['count' => $count]));
    }

    /**
     * Check the Foodpanda credentials by requesting a fresh token.
     */
    public function testConnection(Request $request, AggregatorChannel $channel): RedirectResponse
    {
        $this->authorize('aggregators.manage');
        abort_unless($channel->isFoodpanda() || $channel->isUberEats(), 404);

        try {
            $channel->isUberEats() ? (new UberEatsClient($channel))->token(fresh: true) : (new FoodpandaClient($channel))->token(fresh: true);
        } catch (Throwable $exception) {
            $channel->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 500)])->save();

            return back()->with('error', __('aggregators.push_failed', ['error' => $exception->getMessage()]));
        }

        $channel->forceFill(['last_error' => null])->save();

        return back()->with('success', __('aggregators.connection_ok', ['environment' => $channel->environment]));
    }

    /**
     * Open or close an outlet on the platform.
     */
    public function outletStatus(Request $request, AggregatorChannel $channel): RedirectResponse
    {
        $this->authorize('aggregators.manage');
        abort_unless($channel->isFoodpanda() || $channel->isUberEats(), 404);

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'status' => ['required', 'in:OPEN,CLOSED_TODAY,CLOSED_UNTIL,CHECKIN'],
            'closed_reason' => ['nullable', 'in:TOO_BUSY_NO_DRIVERS,TOO_BUSY_KITCHEN,UPDATES_IN_MENU,TECHNICAL_PROBLEM,CLOSED,OTHER,BAD_WEATHER,HOLIDAY_SPECIAL_DAY'],
            'closed_until' => ['nullable', 'required_if:status,CLOSED_UNTIL', 'date', 'after:now'],
        ]);

        abort_unless($request->user('staff')->canAccessBranch((int) $validated['branch_id']), 404);

        $vendorId = $channel->vendorIdFor((int) $validated['branch_id']);

        if ($vendorId === null) {
            return back()->with('error', __('aggregators.vendor_map_missing'));
        }

        try {
            if ($channel->isUberEats()) {
                (new UberEatsClient($channel))->setStoreStatus(
                    $vendorId,
                    $validated['status'] === 'OPEN' ? 'ONLINE' : 'PAUSED',
                    $validated['status'] === 'OPEN' ? null : ($validated['closed_reason'] ?? 'OTHER'),
                );
            } else {
                (new FoodpandaClient($channel))->setVendorStatus(
                    $vendorId,
                    $validated['status'],
                    $validated['closed_reason'] ?? 'OTHER',
                    isset($validated['closed_until']) ? CarbonImmutable::parse($validated['closed_until'])->utc()->format('Y-m-d\TH:i:s\Z') : null,
                );
            }
        } catch (Throwable $exception) {
            $channel->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 500)])->save();

            return back()->with('error', __('aggregators.push_failed', ['error' => $exception->getMessage()]));
        }

        activity()->performedOn($channel)->causedBy($request->user('staff'))->withProperties($validated)->log('aggregator.outlet_status');

        return back()->with('success', __('aggregators.outlet_updated'));
    }

    /**
     * Pull the last day of orders and create any that the webhook missed.
     */
    public function importOrders(Request $request, AggregatorChannel $channel): RedirectResponse
    {
        $this->authorize('aggregators.manage');
        $adapter = PlatformAdapters::for($channel);
        abort_unless($adapter !== null, 404);

        try {
            $result = $adapter->importRecent();
        } catch (Throwable $exception) {
            $channel->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 500)])->save();

            return back()->with('error', __('aggregators.push_failed', ['error' => $exception->getMessage()]));
        }

        return back()->with('success', __('aggregators.orders_imported', $result));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function platformAttributes(array $validated, bool $keepSecretWhenBlank = false): array
    {
        if ($keepSecretWhenBlank && blank($validated['client_secret'] ?? null)) {
            unset($validated['client_secret']);
        }

        if (isset($validated['vendor_map'])) {
            $validated['vendor_map'] = array_filter($validated['vendor_map'], fn ($vendor): bool => filled($vendor));
        }

        return $validated;
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
