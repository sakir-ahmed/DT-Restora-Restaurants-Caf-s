<?php

declare(strict_types=1);

namespace App\Services\Sms;

interface SmsDriver
{
    /**
     * Send one message. Credentials are the saved values for this provider,
     * keyed by the field keys the provider declares in SmsProviders.
     *
     * @param  array<string, string>  $credentials
     */
    public function send(string $mobile, string $message, array $credentials): SmsResult;
}
