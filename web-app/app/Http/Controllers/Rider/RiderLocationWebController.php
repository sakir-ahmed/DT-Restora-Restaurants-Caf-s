<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rider;

use App\Events\RiderLocationUpdated;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Rider;
use App\Models\RiderLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * GPS writes from the rider web view, sharing the same throttling and
 * broadcasting rules as the mobile API endpoint.
 */
class RiderLocationWebController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $rider = Rider::query()->where('user_id', $request->user('staff')->id)->firstOrFail();

        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $key = 'rider-location:'.$rider->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json(['success' => false, 'throttled' => true], 429);
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

        return response()->json(['success' => true]);
    }
}
