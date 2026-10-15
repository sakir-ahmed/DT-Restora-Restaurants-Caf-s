<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Cloudflare Turnstile verification for guest checkout. When no secret
 * is configured the check is skipped (local and test environments).
 */
class TurnstileService
{
    public function verify(string $token): bool
    {
        $secret = config('services.turnstile.secret_key');

        if (! is_string($secret) || $secret === '') {
            return true;
        }

        if ($token === '') {
            return false;
        }

        $response = Http::asForm()
            ->timeout(8)
            ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => request()->ip(),
            ]);

        return (bool) ($response->json('success') ?? false);
    }
}
