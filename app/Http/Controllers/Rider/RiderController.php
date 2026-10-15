<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rider;

use App\Actions\Delivery\UpdateDeliveryStatusAction;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Rider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rider web view (mobile-first PWA page). A rider only ever sees
 * their own assigned deliveries.
 */
class RiderController extends Controller
{
    public function index(Request $request): Response
    {
        $rider = $this->rider($request);

        $deliveries = Delivery::query()
            ->where('rider_id', $rider->id)
            ->with(['order:id,invoice_no,total,delivery_address,customer_id,order_type', 'order.customer:id,name,mobile'])
            ->latest()
            ->limit(50)
            ->get();

        $codDue = (float) Delivery::query()
            ->where('rider_id', $rider->id)
            ->where('status', Delivery::STATUS_DELIVERED)
            ->whereNull('cod_received_at')
            ->sum('cod_amount');

        return Inertia::render('rider/Index', [
            'deliveries' => $deliveries,
            'rider' => ['name' => $rider->user->name, 'on_duty' => $rider->is_on_duty],
            'codDue' => number_format($codDue, 2, '.', ''),
        ]);
    }

    public function updateStatus(Request $request, Delivery $delivery, UpdateDeliveryStatusAction $action): RedirectResponse
    {
        $rider = $this->rider($request);

        abort_unless($delivery->rider_id === $rider->id, 404);

        $validated = $request->validate([
            'status' => ['required', 'in:picked_up,on_the_way,delivered,failed'],
            'otp' => ['nullable', 'digits:4'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $action->execute(
                $delivery,
                $validated['status'],
                $validated['otp'] ?? null,
                $validated['reason'] ?? null,
                $request->user('staff'),
            );
        } catch (ValidationException) {
            return back()->with('error', __('delivery.otp_mismatch'));
        }

        return back()->with('success', __('delivery.status_updated'));
    }

    public function toggleDuty(Request $request): RedirectResponse
    {
        $rider = $this->rider($request);

        $rider->update(['is_on_duty' => ! $rider->is_on_duty]);

        return back()->with('success', $rider->fresh()->is_on_duty ? __('delivery.on_duty') : __('delivery.off_duty'));
    }

    private function rider(Request $request): Rider
    {
        return Rider::query()
            ->where('user_id', $request->user('staff')->id)
            ->firstOrFail();
    }
}
