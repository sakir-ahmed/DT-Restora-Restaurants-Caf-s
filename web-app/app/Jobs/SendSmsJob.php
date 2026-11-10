<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * SMS always goes through this queued job so a slow gateway never
 * blocks a customer request.
 */
class SendSmsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $mobile,
        public readonly string $message,
        public readonly string $feature = 'general',
        public readonly ?int $userId = null,
    ) {}

    /**
     * Queue the SMS, or run it immediately in demo mode so the message can
     * appear as a toast in the same request.
     */
    public static function deliver(string $mobile, string $message, string $feature = 'general', ?int $userId = null): void
    {
        if (SmsService::isDemo()) {
            static::dispatchSync($mobile, $message, $feature, $userId);

            return;
        }

        static::dispatch($mobile, $message, $feature, $userId);
    }

    public function handle(SmsService $sms): void
    {
        $sms->send($this->mobile, $this->message, $this->feature, $this->userId);
    }
}
