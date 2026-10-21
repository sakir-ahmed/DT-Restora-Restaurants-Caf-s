<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AggregatorChannel;
use App\Services\Aggregators\AggregatorOrderService;
use App\Services\Aggregators\Foodpanda\FoodpandaAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * Public webhooks, one per channel: POST /webhooks/aggregators/{key}.
 * Signature verified (or secret-compared) before anything happens;
 * duplicates acknowledged; malformed payloads rejected with 400.
 */
class AggregatorWebhookController extends Controller
{
    public function __invoke(Request $request, string $key, AggregatorOrderService $service): Response
    {
        $channel = AggregatorChannel::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->first();

        if ($channel === null) {
            return response('Unknown channel', 400);
        }

        if (! $this->signatureValid($request, $channel)) {
            return response('Invalid signature', 400);
        }

        if ($channel->isFoodpanda() && $request->filled('order_id')) {
            return $this->foodpanda($request, $channel);
        }

        $payload = $request->input('order');

        if (! is_array($payload)) {
            return response('Malformed payload', 400);
        }

        $normalized = [
            'platform_order_id' => (string) ($payload['platform_order_id'] ?? $payload['id'] ?? ''),
            'branch_id' => (int) ($payload['branch_id'] ?? 0),
            'customer_name' => $payload['customer_name'] ?? null,
            'customer_mobile' => $payload['customer_mobile'] ?? null,
            'address' => $payload['address'] ?? null,
            'note' => $payload['note'] ?? null,
            'items' => collect((array) ($payload['items'] ?? []))
                ->map(fn ($item) => [
                    'menu_item_id' => (int) ($item['menu_item_id'] ?? 0),
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                ])
                ->all(),
        ];

        if ($normalized['platform_order_id'] === '' || $normalized['branch_id'] === 0 || $normalized['items'] === []) {
            return response('Malformed payload', 400);
        }

        $service->receive($channel, $normalized);

        return response('OK');
    }

    private function signatureValid(Request $request, AggregatorChannel $channel): bool
    {
        $secret = $channel->webhook_secret;

        // Channels without a configured secret are accepted; the real
        // platform adapters set one at onboarding.
        if ($secret === null || $secret === '') {
            return true;
        }

        // Foodpanda sends the static token set in the Vendor Portal; accept it
        // in the usual header places next to our own signature header.
        $candidates = [
            (string) $request->header('X-Webhook-Signature', ''),
            (string) $request->header('X-Webhook-Token', ''),
            (string) $request->header('X-Api-Key', ''),
            trim((string) preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization', ''))),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && hash_equals($secret, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Foodpanda order events. Always acknowledged with 200 once the call is
     * authentic: the platform retries 4xx and 5xx, so problems we cannot fix
     * by retrying are logged on the channel instead.
     */
    private function foodpanda(Request $request, AggregatorChannel $channel): Response
    {
        try {
            app(FoodpandaAdapter::class, ['channel' => $channel])->receiveOrder($request->all());
        } catch (Throwable $exception) {
            $channel->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 500)])->save();
            report($exception);
        }

        return response('OK');
    }
}
