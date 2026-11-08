<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Demo mode shows messages on screen instead of sending them. It exposes
     * verification codes to the browser, so it is never honoured in production.
     */
    public static function isDemo(): bool
    {
        return config('services.sms.demo') === true && ! app()->isProduction();
    }

    public function send(string $mobile, string $message): bool
    {
        if (self::isDemo()) {
            app(DemoSmsInbox::class)->push($mobile, $message);

            Log::info('Demo SMS shown on screen, not sent.', ['mobile' => $mobile]);

            return true;
        }

        if (! config('services.bdbulksms.token')) {
            Log::warning('SMS skipped: BdBulkSMS token not configured.', ['mobile' => $mobile]);

            return false;
        }

        $response = Http::asForm()
            ->timeout(10)
            ->post((string) config('services.bdbulksms.url'), [
                'to' => $mobile,
                'message' => $message,
                'token' => config('services.bdbulksms.token'),
            ]);

        // Confirm the exact success format in the BdBulkSMS API docs.
        $sent = $response->successful() && str_starts_with($response->body(), 'Ok');

        $sent
            ? Log::info('SMS sent.', ['mobile' => $mobile])
            : Log::error('SMS failed.', ['mobile' => $mobile, 'status' => $response->status()]);

        return $sent;
    }
}
