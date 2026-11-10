<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SmsLog;
use App\Services\Sms\SmsFeatures;
use App\Services\Sms\SmsProviders;
use App\Services\Sms\SmsResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    /**
     * Send one SMS for a feature (see SmsFeatures) and record it in the SMS
     * history. A switched-off feature is not sent but is still recorded as
     * skipped, so the owner can see what would have gone out.
     */
    public function send(string $mobile, string $message, string $feature = 'general', ?int $userId = null): bool
    {
        if (! SmsFeatures::enabled($feature)) {
            $this->record($feature, $mobile, $message, SmsLog::STATUS_SKIPPED, null, __('sms.skipped_reason'), $userId);

            return false;
        }

        if (self::isDemo()) {
            app(DemoSmsInbox::class)->push($mobile, $message);

            Log::info('Demo SMS shown on screen, not sent.', ['mobile' => $mobile]);
            $this->record($feature, $mobile, $message, SmsLog::STATUS_DEMO, null, __('sms.demo_reason'), $userId);

            return true;
        }

        $result = $this->deliver($mobile, $message);

        $result->sent
            ? Log::info('SMS sent.', ['mobile' => $mobile])
            : Log::error('SMS failed.', ['mobile' => $mobile, 'reason' => $result->message]);

        $this->record($feature, $mobile, $message, $result->sent ? SmsLog::STATUS_SENT : SmsLog::STATUS_FAILED, SmsProviders::current(), $result->message, $userId);

        return $result->sent;
    }

    private function record(string $feature, string $mobile, string $message, string $status, ?string $provider, ?string $response, ?int $userId): void
    {
        try {
            SmsLog::query()->create([
                'feature' => $feature,
                'mobile' => mb_substr($mobile, 0, 30),
                // A sign-in code in the history would let anyone with Settings access sign in as the customer.
                'message' => $feature === 'otp' ? __('sms.otp_hidden') : $message,
                'provider' => $provider,
                'status' => $status,
                'response' => $response !== null ? mb_substr($response, 0, 500) : null,
                'user_id' => $userId,
            ]);
        } catch (Throwable $exception) {
            // The history must never stop a message from being sent.
            Log::warning('SMS history entry could not be saved.', ['error' => $exception->getMessage()]);
        }
    }

    /**
     * Send through the provider chosen in Settings and report what the
     * gateway said. Also used by the "send test SMS" button.
     */
    public function deliver(string $mobile, string $message): SmsResult
    {
        $provider = SmsProviders::current();

        if (! SmsProviders::isConfigured($provider)) {
            Log::warning('SMS skipped: provider credentials are not configured.', ['provider' => $provider, 'mobile' => $mobile]);

            return SmsResult::failed('Provider credentials are not set.');
        }

        try {
            return SmsProviders::driver($provider)->send($mobile, $message, SmsProviders::credentials($provider));
        } catch (ConnectionException $exception) {
            return SmsResult::failed('Could not reach the gateway.');
        }
    }
}
