<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Signed tracking links: the token works without login and is unique
 * per order. (Expiry after delivery arrives with Phase 8.)
 */
class TrackController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $order = Order::query()
            ->where('tracking_token', $token)
            ->with(['items:id,order_id,name,quantity,line_total', 'branch:id,name,phone'])
            ->firstOrFail();

        return Inertia::render('web/Track', [
            'order' => [
                'id' => $order->id,
                'invoice_no' => $order->invoice_no,
                'status' => $order->status->value,
                'order_type' => $order->order_type->value,
                'total' => (string) $order->total,
                'created_at' => $order->created_at->toIso8601String(),
                'scheduled_for' => $order->scheduled_for?->toIso8601String(),
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'line_total' => (string) $item->line_total,
                ]),
                'branch' => $order->branch?->only(['name', 'phone']),
            ],
        ]);
    }
}
