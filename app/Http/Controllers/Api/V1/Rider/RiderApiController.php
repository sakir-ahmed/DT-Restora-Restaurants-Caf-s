<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rider;

use App\Actions\Delivery\UpdateDeliveryStatusAction;
use App\Events\RiderLocationUpdated;
use App\Http\Controllers\Api\Controller;
use App\Models\Delivery;
use App\Models\Rider;
use App\Models\RiderLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class RiderApiController extends Controller
{
    /**
     * Deliveries assigned to the authenticated rider only.
     */
    public function deliveries(Request $request): JsonResponse
    {
        $rider = $this->rider($request);

        $deliveries = Delivery::query()
            ->where('rider_id', $rider->id)
            ->with(['order:id,invoice_no,total,delivery_address,delivery_zone_id', 'order.deliveryZone:id,name'])
            ->latest()
            ->limit(50)
            ->get();

        return $this->success($deliveries->map(fn (Delivery $delivery) => [
            'id' => $delivery->id,
            'status' => $delivery->status,
            'cod_amount' => (string) $delivery->cod_amount,
            'otp_required' => $delivery->otp_code !== null,
            'order' => [
                'id' => $delivery->order->id,
                'invoice_no' => $delivery->order->invoice_no,
                'address' => $delivery->order->delivery_address,
                'zone' => $delivery->order->deliveryZone?->name,
            ],
        ])->all());
    }

    public function updateStatus(Request $request, Delivery $delivery, UpdateDeliveryStatusAction $action): JsonResponse
    {
        $rider = $this->rider($request);

        abort_unless($delivery->rider_id === $rider->id, 404);

        $validated = $request->validate([
            'status' => ['required', 'in:picked_up,on_the_way,delivered,failed'],
            'otp' => ['nullable', 'digits:4'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $action->execute($delivery, $validated['status'], $validated['otp'] ?? null, $validated['reason'] ?? null, $request->user());
        } catch (ValidationException $exception) {
            return $this->error('VALIDATION_FAILED', collect($exception->errors())->flatten()->first() ?? 'Invalid', 422);
        }

        return $this->success(['status' => $delivery->fresh()->status], __('delivery.status_updated'));
    }

    /**
     * GPS sharing: throttled to one write per 5 seconds per rider and
     * broadcast to the tracking channels of all active deliveries.
     */
    public function location(Request $request): JsonResponse
    {
        $rider = $this->rider($request);

        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $key = 'rider-location:'.$rider->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return $this->error('TOO_MANY_REQUESTS', __('api.too_many_attempts'), 429, [
                'retry_after' => RateLimiter::availableIn($key),
            ]);
        }

        RateLimiter::hit($key, 5);

        RiderLocation::query()->create([
            'rider_id' => $rider->id,
            'latitude' => $validated['lat'],
            'longitude' => $validated['lng'],
            'accuracy' => $validated['accuracy'] ?? null,
            'recorded_at' => isset($validated['recorded_at']) ? new \DateTimeImmutable((string) $validated['recorded_at']) : now(),
        ]);

        $activeOrderIds = Delivery::query()
            ->where('rider_id', $rider->id)
            ->whereIn('status', ['picked_up', 'on_the_way'])
            ->pluck('order_id')
            ->all();

        broadcast(new RiderLocationUpdated(
            $rider->id,
            (float) $validated['lat'],
            (float) $validated['lng'],
            $activeOrderIds,
        ));

        return $this->success(['broadcast_to' => count($activeOrderIds)]);
    }

    public function verifyOtp(Request $request, Delivery $delivery, UpdateDeliveryStatusAction $action): JsonResponse
    {
        $rider = $this->rider($request);
        abort_unless($delivery->rider_id === $rider->id, 404);

        $validated = $request->validate(['otp' => ['required', 'digits:4']]);

        $action->execute($delivery, 'delivered', $validated['otp'], null, $request->user());

        return $this->success(['status' => $delivery->fresh()->status]);
    }

    public function codSummary(Request $request): JsonResponse
    {
        $rider = $this->rider($request);

        $due = Delivery::query()
            ->where('rider_id', $rider->id)
            ->where('status', Delivery::STATUS_DELIVERED)
            ->whereNull('cod_received_at')
            ->get(['id', 'cod_amount']);

        return $this->success([
            'total' => number_format((float) $due->sum('cod_amount'), 2, '.', ''),
            'count' => $due->count(),
            'deliveries' => $due->map(fn (Delivery $delivery) => [
                'id' => $delivery->id,
                'cod_amount' => (string) $delivery->cod_amount,
            ]),
        ]);
    }

    private function rider(Request $request): Rider
    {
        $rider = Rider::query()->where('user_id', $request->user()->id)->first();

        abort_if($rider === null, 403);

        return $rider;
    }
}
