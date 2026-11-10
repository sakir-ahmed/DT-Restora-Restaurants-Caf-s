<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;

/**
 * Sends a push only when FCM credentials are configured. Without a
 * server key the registration is stored and nothing is sent.
 */
class PushNotificationService
{
    public function isConfigured(): bool
    {
        return filled(config('services.fcm.server_key'));
    }

    public function notifyCustomer(Customer $customer, string $title, string $body): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $tokens = DeviceToken::query()
            ->where('owner_type', $customer->getMorphClass())
            ->where('owner_id', $customer->id)
            ->pluck('token');

        foreach ($tokens as $token) {
            Http::withToken((string) config('services.fcm.server_key'))
                ->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                ]);
        }
    }
}
