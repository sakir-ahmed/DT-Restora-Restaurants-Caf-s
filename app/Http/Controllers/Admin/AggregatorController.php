<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AggregatorChannel;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\Aggregators\AggregatorOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AggregatorController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('aggregators.manage');

        $channels = AggregatorChannel::query()
            ->withCount(['payouts'])
            ->orderBy('name')
            ->get();

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

    public function storeChannel(Request $request): RedirectResponse
    {
        $this->authorize('aggregators.manage');

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:40', 'unique:aggregator_channels,key'],
            'name' => ['required', 'string', 'max:100'],
            'commission_percent' => ['required', 'numeric', 'between:0,100'],
            'webhook_secret' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        AggregatorChannel::query()->create($validated);

        return back()->with('success', __('aggregators.channel_created'));
    }

    public function updateChannel(Request $request, AggregatorChannel $channel): RedirectResponse
    {
        $this->authorize('aggregators.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'commission_percent' => ['required', 'numeric', 'between:0,100'],
            'webhook_secret' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $channel->update($validated);

        return back()->with('success', __('aggregators.channel_updated'));
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

        $validated = $request->validate(['decision' => ['required', 'in:accept,reject']]);

        activity()
            ->performedOn($order)
            ->causedBy($request->user('staff'))
            ->withProperties(['decision' => $validated['decision']])
            ->log('aggregator.decision');

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

        $branchId = $this->currentBranch($request)->id;

        $count = MenuItem::query()
            ->where('branch_id', $branchId)
            ->where('is_available', true)
            ->count();

        activity()->performedOn($channel)->withProperties(['items' => $count])->log('aggregator.menu_synced');

        return back()->with('success', __('aggregators.menu_synced', ['count' => $count]));
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
