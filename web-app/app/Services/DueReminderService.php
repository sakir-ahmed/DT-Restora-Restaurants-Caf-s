<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Jobs\SendSmsJob;
use App\Models\Order;
use App\Services\Sms\SmsFeatures;
use App\Services\Sms\SmsTemplates;
use Illuminate\Support\Facades\Cache;

/**
 * Reminds customers who still owe money on a due sale. One reminder per sale
 * every REPEAT_DAYS days, never on the day of the sale.
 */
class DueReminderService
{
    public const REPEAT_DAYS = 7;

    public function send(): int
    {
        if (! SmsFeatures::enabled('due_reminder')) {
            return 0;
        }

        $sent = 0;

        Order::query()
            ->where('is_due', true)
            ->where('status', '!=', OrderStatus::Void->value)
            ->where('paid_at', '<', now()->startOfDay())
            ->whereHas('customer', fn ($query) => $query->whereNotNull('mobile'))
            ->with(['customer:id,name,mobile', 'branch:id,name'])
            ->orderBy('id')
            ->each(function (Order $order) use (&$sent): void {
                $key = 'sms.due.'.$order->id;
                $balance = $order->balanceDue();
                $mobile = normalize_bd_mobile((string) $order->customer?->mobile);

                if ($balance <= 0 || $mobile === '' || Cache::has($key)) {
                    return;
                }

                SendSmsJob::deliver($mobile, SmsTemplates::render('due_reminder', [
                    'name' => $order->customer->name,
                    'balance' => number_format($balance, 2, '.', ''),
                    'invoice' => $order->invoice_no,
                    'branch' => $order->branch?->name ?? config('app.name'),
                ], $order->customer->locale), 'due_reminder');

                Cache::put($key, true, now()->addDays(self::REPEAT_DAYS));
                $sent++;
            });

        return $sent;
    }
}
