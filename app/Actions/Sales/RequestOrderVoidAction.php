<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Models\Order;
use App\Models\User;
use App\Models\VoidRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestOrderVoidAction
{
    public function execute(Order $order, User $requester, string $reasonCode, ?string $note = null): VoidRequest
    {
        return DB::transaction(function () use ($order, $requester, $reasonCode, $note): VoidRequest {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->isVoid()) {
                throw ValidationException::withMessages([
                    'order' => __('orders.order_void'),
                ]);
            }

            if ($order->voidRequest()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages([
                    'order' => __('orders.void_already_pending'),
                ]);
            }

            if (! in_array($reasonCode, VoidRequest::REASONS, true)) {
                throw ValidationException::withMessages([
                    'reason_code' => __('inventory.reason_required'),
                ]);
            }

            $request = VoidRequest::query()->create([
                'order_id' => $order->id,
                'requested_by' => $requester->id,
                'reason_code' => $reasonCode,
                'note' => $note,
                'status' => 'pending',
            ]);

            activity()
                ->performedOn($order)
                ->causedBy($requester)
                ->log('order.void_requested');

            return $request;
        });
    }
}
