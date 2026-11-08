<?php

declare(strict_types=1);

namespace App\Actions\Delivery;

use App\Events\DeliveryStatusChanged;
use App\Jobs\SendSmsJob;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateDeliveryStatusAction
{
    /**
     * Rider-driven status transitions with optional delivery OTP as
     * proof of handover.
     */
    public function execute(Delivery $delivery, string $nextStatus, ?string $otp = null, ?string $failedReason = null, ?User $rider = null): Delivery
    {
        return DB::transaction(function () use ($delivery, $nextStatus, $otp, $failedReason, $rider): Delivery {
            $delivery = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            $allowed = [
                Delivery::STATUS_ASSIGNED => [Delivery::STATUS_PICKED_UP, Delivery::STATUS_FAILED],
                Delivery::STATUS_PICKED_UP => [Delivery::STATUS_ON_THE_WAY, Delivery::STATUS_FAILED],
                Delivery::STATUS_ON_THE_WAY => [Delivery::STATUS_DELIVERED, Delivery::STATUS_FAILED],
            ];

            if (! in_array($nextStatus, $allowed[$delivery->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => __('delivery.invalid_transition'),
                ]);
            }

            if ($rider !== null && $delivery->rider?->user_id !== $rider->id) {
                throw ValidationException::withMessages([
                    'delivery' => __('delivery.not_your_delivery'),
                ]);
            }

            if ($nextStatus === Delivery::STATUS_DELIVERED && $delivery->otp_code !== null) {
                if ($otp === null || $otp !== $delivery->otp_code) {
                    throw ValidationException::withMessages([
                        'otp' => __('delivery.otp_mismatch'),
                    ]);
                }
            }

            $delivery->update([
                'status' => $nextStatus,
                'picked_up_at' => $nextStatus === Delivery::STATUS_PICKED_UP ? now() : $delivery->picked_up_at,
                'delivered_at' => $nextStatus === Delivery::STATUS_DELIVERED ? now() : $delivery->delivered_at,
                'failed_reason' => $nextStatus === Delivery::STATUS_FAILED ? ($failedReason ?? __('delivery.default_fail_reason')) : $delivery->failed_reason,
            ]);

            $order = $delivery->order;

            broadcast(new DeliveryStatusChanged($order->branch_id, $order->customer_id, [
                'delivery_id' => $delivery->id,
                'order_id' => $order->id,
                'status' => $nextStatus,
            ]));

            if (in_array($nextStatus, [Delivery::STATUS_PICKED_UP, Delivery::STATUS_DELIVERED, Delivery::STATUS_FAILED], true)
                && $order->customer?->mobile !== null) {
                SendSmsJob::deliver(
                    $order->customer->mobile,
                    __('delivery.sms_status.'.$nextStatus, ['invoice' => $order->invoice_no]),
                );
            }

            activity()
                ->performedOn($delivery)
                ->causedBy($rider)
                ->log('delivery.status_'.$nextStatus);

            return $delivery;
        });
    }

    /**
     * Manager records the cash the rider hands over.
     */
    public function reconcileCod(Delivery $delivery, User $manager): Delivery
    {
        $delivery = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();

        if ($delivery->status !== Delivery::STATUS_DELIVERED) {
            throw ValidationException::withMessages([
                'delivery' => __('delivery.not_delivered_yet'),
            ]);
        }

        if ($delivery->cod_received_at !== null) {
            throw ValidationException::withMessages([
                'delivery' => __('delivery.cod_already_reconciled'),
            ]);
        }

        $delivery->update(['cod_received_at' => now()]);

        activity()
            ->performedOn($delivery)
            ->causedBy($manager)
            ->log('delivery.cod_reconciled');

        return $delivery;
    }
}
