<?php

declare(strict_types=1);

namespace App\Actions\Delivery;

use App\Events\DeliveryAssigned;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Rider;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignRiderAction
{
    public function execute(Order $order, Rider $rider, ?User $manager = null, ?bool $withOtp = null): Delivery
    {
        $withOtp ??= (bool) Setting::current()->delivery_otp_enabled;

        return DB::transaction(function () use ($order, $rider, $manager, $withOtp): Delivery {
            $delivery = Delivery::query()->where('order_id', $order->id)->lockForUpdate()->first();

            if ($delivery !== null && $delivery->status !== Delivery::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'delivery' => __('delivery.already_assigned'),
                ]);
            }

            $isCod = $order->payments()->where('status', 'completed')->doesntExist()
                && $order->channel->value !== 'pos';

            $data = [
                'order_id' => $order->id,
                'rider_id' => $rider->id,
                'status' => Delivery::STATUS_ASSIGNED,
                'cod_amount' => $isCod ? $order->total : 0,
                'assigned_at' => now(),
            ];

            if ($withOtp && $isCod) {
                $data['otp_code'] = (string) random_int(1000, 9999);
            }

            if ($delivery === null) {
                $delivery = Delivery::query()->create($data);
            } else {
                $delivery->update($data);
            }

            broadcast(new DeliveryAssigned($order->branch_id, $rider->user_id, [
                'delivery_id' => $delivery->id,
                'order_id' => $order->id,
                'invoice_no' => $order->invoice_no,
            ]));

            activity()
                ->performedOn($delivery)
                ->causedBy($manager)
                ->log('delivery.assigned');

            return $delivery;
        });
    }
}
