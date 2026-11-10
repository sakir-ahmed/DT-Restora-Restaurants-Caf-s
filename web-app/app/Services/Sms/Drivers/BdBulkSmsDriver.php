<?php

declare(strict_types=1);

namespace App\Services\Sms\Drivers;

use App\Services\Sms\SmsDriver;
use App\Services\Sms\SmsResult;
use Illuminate\Support\Facades\Http;

/**
 * BD Bulk SMS (bdbulksms.net). One POST with the recipient, message and API
 * token. The "?json" form of the endpoint answers with one entry per number:
 * status "SENT" on success, "FAILED" otherwise.
 */
class BdBulkSmsDriver implements SmsDriver
{
    public const URL = 'https://api.bdbulksms.net/api.php?json';

    public function send(string $mobile, string $message, array $credentials): SmsResult
    {
        $token = trim((string) ($credentials['token'] ?? ''));

        if ($token === '') {
            return SmsResult::failed('API token is not set.');
        }

        $response = Http::asForm()->timeout(10)->post(self::URL, [
            'to' => $mobile,
            'message' => $message,
            'token' => $token,
        ]);

        if (! $response->successful()) {
            return SmsResult::failed('Gateway answered HTTP '.$response->status().'.');
        }

        $rows = $response->json();

        if (is_array($rows) && $rows !== []) {
            foreach ($rows as $row) {
                if (is_array($row) && strtoupper((string) ($row['status'] ?? '')) !== 'SENT') {
                    return SmsResult::failed((string) ($row['statusmsg'] ?? 'Failed'));
                }
            }

            $first = $rows[0] ?? [];

            return SmsResult::sent((string) (is_array($first) ? ($first['statusmsg'] ?? '') : ''));
        }

        // Plain text answer: one line per number, "Ok: ..." or "Error: ...".
        $body = trim($response->body());

        return str_starts_with($body, 'Ok')
            ? SmsResult::sent($body)
            : SmsResult::failed($body !== '' ? $body : 'Empty answer from the gateway.');
    }
}
