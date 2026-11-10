<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\OrderChannel;
use App\Jobs\SendSmsJob;
use App\Models\Order;
use App\Models\SmsLog;
use App\Services\Sms\SmsFeatures;
use App\Services\Sms\SmsTemplates;
use App\Services\SmsService;

/**
 * Texts the invoice to a customer when a counter or table order is paid (needs the customer's mobile on the order).
 * Website orders already get an order confirmation, and a sale put on due
 * is covered by the due reminder, so neither sends this one.
 */
class OrderInvoiceSmsObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status') || ! $order->isPaid() || $order->is_due) {
            return;
        }

        if (! in_array($order->channel, [OrderChannel::Pos, OrderChannel::Qr], true)) {
            return;
        }

        // Checked here, not only in the sender, so a switched-off feature does not fill the history on every sale.
        if (! SmsFeatures::enabled('invoice')) {
            return;
        }

        $mobile = normalize_bd_mobile((string) $order->customer?->mobile);

        $message = SmsTemplates::render('invoice', [
            'branch' => $order->branch?->name ?? config('app.name'),
            'invoice' => $order->invoice_no,
            'total' => number_format((float) $order->total, 2, '.', ''),
        ], $order->customer?->locale);

        if ($mobile !== '') {
            SendSmsJob::deliver($mobile, $message, 'invoice');

            return;
        }

        // A walk-in sale has nowhere to send to. The demo still shows the preview so the feature can be seen;
        // a real install records that nothing was sent, so the history explains why.
        if (SmsService::isDemo()) {
            SendSmsJob::deliver(__('sms.walk_in_label'), $message, 'invoice');

            return;
        }

        SmsLog::query()->create([
            'feature' => 'invoice',
            'mobile' => '',
            'message' => $message,
            'status' => SmsLog::STATUS_SKIPPED,
            'response' => __('sms.no_mobile_reason'),
        ]);
    }
}
