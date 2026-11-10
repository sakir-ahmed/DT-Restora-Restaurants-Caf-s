<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Holds SMS messages that were not sent because SMS_DEMO is on, until the
 * next page response shows them as toasts. Messages created during a web
 * request wait in that visitor's session; messages from queues, the scheduler
 * or stateless API calls wait in a small shared list.
 */
class DemoSmsInbox
{
    private const SESSION_KEY = 'demo_sms';

    private const SHARED_KEY = 'demo_sms:shared';

    private const SHARED_LIMIT = 20;

    /**
     * @return array{id: string, mobile: string, message: string, at: string}
     */
    public function push(string $mobile, string $message): array
    {
        $entry = [
            'id' => (string) Str::uuid(),
            'mobile' => $mobile,
            'message' => $message,
            'at' => now()->toIso8601String(),
        ];

        $request = app()->bound('request') ? app('request') : null;

        if ($request instanceof Request && $request->hasSession()) {
            $request->session()->push(self::SESSION_KEY, $entry);

            return $entry;
        }

        $shared = Cache::get(self::SHARED_KEY, []);
        $shared[] = $entry;

        Cache::put(self::SHARED_KEY, array_slice($shared, -self::SHARED_LIMIT), now()->addHour());

        return $entry;
    }

    /**
     * Take every waiting message for this visitor and clear them.
     *
     * @return list<array{id: string, mobile: string, message: string, at: string}>
     */
    public function drain(Request $request): array
    {
        if (! SmsService::isDemo()) {
            return [];
        }

        $own = $request->hasSession() ? $request->session()->pull(self::SESSION_KEY, []) : [];
        $shared = Cache::pull(self::SHARED_KEY, []);

        return array_values([...$own, ...$shared]);
    }
}
