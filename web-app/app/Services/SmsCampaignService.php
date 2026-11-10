<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Models\Customer;
use App\Services\Sms\SmsFeatures;
use App\Services\Sms\SmsTemplates;
use Illuminate\Support\Facades\Cache;

/**
 * Birthday and win-back SMS. Each customer is contacted at most once
 * per campaign window, stored in cache so a rerun does not resend.
 */
class SmsCampaignService
{
    public function sendBirthdayOffers(): int
    {
        if (! SmsFeatures::enabled('birthday')) {
            return 0;
        }

        $sent = 0;

        Customer::query()
            ->where('is_active', true)
            ->whereNotNull('mobile')
            ->whereNotNull('birthday')
            ->whereMonth('birthday', now()->month)
            ->whereDay('birthday', now()->day)
            ->orderBy('id')
            ->each(function (Customer $customer) use (&$sent): void {
                $key = 'sms.birthday.'.$customer->id.'.'.now()->year;

                if (Cache::has($key)) {
                    return;
                }

                SendSmsJob::deliver($customer->mobile, SmsTemplates::render('birthday', ['name' => $customer->name], $customer->locale), 'birthday');
                Cache::put($key, true, now()->addYear());
                $sent++;
            });

        return $sent;
    }

    public function sendWinBack(int $inactiveDays = 30): int
    {
        if (! SmsFeatures::enabled('win_back')) {
            return 0;
        }

        $sent = 0;
        $cutoff = now()->subDays($inactiveDays);

        Customer::query()
            ->where('is_active', true)
            ->whereNotNull('mobile')
            ->whereHas('orders')
            ->whereDoesntHave('orders', fn ($query) => $query->where('created_at', '>=', $cutoff))
            ->orderBy('id')
            ->each(function (Customer $customer) use (&$sent, $inactiveDays): void {
                $key = 'sms.winback.'.$customer->id;

                if (Cache::has($key)) {
                    return;
                }

                SendSmsJob::deliver($customer->mobile, SmsTemplates::render('win_back', ['name' => $customer->name], $customer->locale), 'win_back');
                Cache::put($key, true, now()->addDays($inactiveDays));
                $sent++;
            });

        return $sent;
    }
}
