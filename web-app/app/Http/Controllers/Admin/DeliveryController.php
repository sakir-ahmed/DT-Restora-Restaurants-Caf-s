<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Delivery\AssignRiderAction;
use App\Actions\Delivery\UpdateDeliveryStatusAction;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Rider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('delivery.dispatch');

        $branch = $this->currentBranch($request);

        $deliveries = Delivery::query()
            ->whereHas('order', fn ($query) => $query->where('branch_id', $branch->id))
            ->with(['order:id,invoice_no,total,status,order_type,customer_id,delivery_address,created_at', 'order.customer:id,name,mobile', 'rider.user:id,name'])
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->limit(100)
            ->get();

        // Delivery orders that still need a rider.
        $unassigned = Order::query()
            ->where('branch_id', $branch->id)
            ->where('order_type', 'delivery')
            ->whereIn('status', ['pending', 'preparing', 'ready', 'served'])
            ->whereDoesntHave('delivery')
            ->with(['customer:id,name,mobile'])
            ->latest()
            ->limit(50)
            ->get();

        $riders = Rider::query()
            ->where('is_active', true)
            ->with('user:id,name')
            ->withCount(['deliveries as active_deliveries' => fn ($query) => $query->whereIn('status', ['assigned', 'picked_up', 'on_the_way'])])
            ->get();

        return Inertia::render('admin/delivery/Index', [
            'deliveries' => $deliveries,
            'unassigned' => $unassigned,
            'riders' => $riders,
            'statusFilter' => $request->string('status')->toString(),
        ]);
    }

    public function assign(Request $request, Order $order, AssignRiderAction $action): RedirectResponse
    {
        $this->authorize('delivery.dispatch');

        abort_unless($request->user('staff')->canAccessBranch($order->branch_id), 404);

        $validated = $request->validate([
            'rider_id' => ['required', 'integer', 'exists:riders,id'],
        ]);

        $rider = Rider::query()->where('is_active', true)->findOrFail((int) $validated['rider_id']);

        $action->execute($order, $rider, $request->user('staff'));

        return back()->with('success', __('delivery.assigned'));
    }

    public function reconcileCod(Request $request, Delivery $delivery, UpdateDeliveryStatusAction $action): RedirectResponse
    {
        $this->authorize('delivery.dispatch');

        abort_unless($request->user('staff')->canAccessBranch($delivery->order->branch_id), 404);

        $action->reconcileCod($delivery, $request->user('staff'));

        return back()->with('success', __('delivery.cod_reconciled'));
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
