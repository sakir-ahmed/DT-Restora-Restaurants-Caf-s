<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class OtpService
{
    private const TTL_MINUTES = 5;

    private const MAX_VERIFY_ATTEMPTS = 5;

    public function __construct(private readonly SmsService $sms) {}

    public function isRateLimited(string $mobile, string $ip): bool
    {
        // Demo mode is for testing, so repeated sends must never lock the tester out.
        if (SmsService::isDemo()) {
            return false;
        }

        return RateLimiter::tooManyAttempts('otp:send:'.sha1(normalize_bd_mobile($mobile).'|'.$ip), 5);
    }

    /**
     * Generate, store (hashed) and send an OTP. Rate limited by IP + mobile
     * at 5 requests per hour.
     */
    public function send(string $mobile, string $ip): bool
    {
        $mobile = normalize_bd_mobile($mobile);
        $key = 'otp:send:'.sha1($mobile.'|'.$ip);

        if (! SmsService::isDemo()) {
            if (RateLimiter::tooManyAttempts($key, 5)) {
                return false;
            }

            RateLimiter::hit($key, 3600);
        }

        $code = (string) random_int(100000, 999999);

        Cache::put($this->cacheKey($mobile), [
            'hash' => Hash::make($code),
            'attempts' => 0,
        ], now()->addMinutes(self::TTL_MINUTES));

        // Local installs without an SMS gateway read the code from the log so sign-in and checkout stay testable.
        if (app()->environment('local') && ! SmsService::isDemo() && ! config('services.bdbulksms.token')) {
            Log::info('OTP for local testing (SMS gateway not configured).', ['mobile' => $mobile, 'code' => $code]);

            return true;
        }

        return $this->sms->send($mobile, __('auth.otp_sms', ['code' => $code]));
    }

    /**
     * Verify an OTP. Max 5 wrong attempts per code; the code is deleted
     * after a successful verification.
     */
    public function verify(string $mobile, string $code): bool
    {
        $mobile = normalize_bd_mobile($mobile);
        $key = $this->cacheKey($mobile);

        /** @var array{hash: string, attempts: int}|null $payload */
        $payload = Cache::get($key);

        if ($payload === null) {
            return false;
        }

        if ((int) $payload['attempts'] >= self::MAX_VERIFY_ATTEMPTS) {
            Cache::forget($key);

            return false;
        }

        if (! Hash::check($code, $payload['hash'])) {
            $payload['attempts'] = (int) $payload['attempts'] + 1;
            Cache::put($key, $payload, now()->addMinutes(self::TTL_MINUTES));

            return false;
        }

        Cache::forget($key);

        return true;
    }

    private function cacheKey(string $mobile): string
    {
        return 'otp:'.$mobile;
    }
}
