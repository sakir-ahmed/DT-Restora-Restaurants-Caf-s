<?php

declare(strict_types=1);

namespace App\Services\Sms;

final class SmsResult
{
    public function __construct(
        public readonly bool $sent,
        public readonly string $message = '',
    ) {}

    public static function sent(string $message = ''): self
    {
        return new self(true, $message);
    }

    public static function failed(string $message): self
    {
        return new self(false, $message);
    }
}
