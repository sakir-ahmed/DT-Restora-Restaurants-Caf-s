<?php

declare(strict_types=1);

namespace App\Services\Aggregators\UberEats;

use App\Models\AggregatorChannel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the Uber Eats Marketplace API. OAuth 2.0 client
 * credentials; the bearer token lasts 30 days and is cached, because the
 * token endpoint is limited to 100 calls an hour.
 *
 * @see https://developer.uber.com/docs/eats/introduction
 */
class UberEatsClient
{
    public const SCOPES = 'eats.store eats.order eats.store.orders.read eats.store.status.write';

    public function __construct(private readonly AggregatorChannel $channel) {}

    /**
     * The token domain must match the API domain, or Uber rejects the call.
     */
    public function authUrl(): string
    {
        return $this->channel->environment === 'production' ? 'https://auth.uber.com' : 'https://sandbox-login.uber.com';
    }

    public function baseUrl(): string
    {
        return $this->channel->environment === 'production' ? 'https://api.uber.com' : 'https://test-api.uber.com';
    }

    public function token(bool $fresh = false): string
    {
        if (! $this->channel->hasApiCredentials()) {
            throw new RuntimeException(__('aggregators.credentials_missing'));
        }

        $cacheKey = 'ubereats.token.'.$this->channel->id.'.'.$this->channel->environment;

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()->acceptJson()->timeout(15)->post($this->authUrl().'/oauth/v2/token', [
            'client_id' => $this->channel->client_id,
            'client_secret' => $this->channel->client_secret,
            'grant_type' => 'client_credentials',
            'scope' => self::SCOPES,
        ]);

        if ($response->failed() || ! is_string($response->json('access_token'))) {
            throw new RuntimeException(__('aggregators.token_failed', ['status' => $response->status()]));
        }

        $token = (string) $response->json('access_token');
        Cache::put($cacheKey, $token, max(60, (int) ($response->json('expires_in') ?? 86400) - 3600));

        return $token;
    }

    /** @return array<string, mixed> */
    public function order(string $orderId): array
    {
        return $this->send('get', "/v2/eats/order/{$orderId}");
    }

    /** @return array<string, mixed> */
    public function createdOrders(string $storeId): array
    {
        return $this->send('get', "/v1/eats/stores/{$storeId}/created-orders");
    }

    /** @return array<string, mixed> */
    public function accept(string $orderId, ?string $reference = null): array
    {
        return $this->send('post', "/v1/eats/orders/{$orderId}/accept_pos_order", array_filter([
            'reason' => 'Accepted by DT Restora',
            'external_reference_id' => $reference,
        ]));
    }

    /** @return array<string, mixed> */
    public function deny(string $orderId, string $code, string $explanation): array
    {
        return $this->send('post', "/v1/eats/orders/{$orderId}/deny_pos_order", [
            'reason' => ['code' => $code, 'explanation' => $explanation],
        ]);
    }

    /** @return array<string, mixed> */
    public function cancel(string $orderId, string $reason, ?string $details = null): array
    {
        return $this->send('post', "/v1/eats/orders/{$orderId}/cancel", array_filter([
            'reason' => $reason,
            'details' => $details,
            'cancelling_party' => 'MERCHANT',
        ]));
    }

    /**
     * ONLINE takes orders; PAUSED stops them.
     *
     * @return array<string, mixed>
     */
    public function setStoreStatus(string $storeId, string $status, ?string $reason = null): array
    {
        return $this->send('post', "/v1/eats/store/{$storeId}/status", array_filter([
            'status' => $status,
            'reason' => $reason,
        ]));
    }

    /**
     * Price and availability of one menu item.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateItem(string $storeId, string $itemId, array $payload): array
    {
        return $this->send('post', "/v2/eats/stores/{$storeId}/menus/items/{$itemId}", $payload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $data = []): array
    {
        $options = $method === 'get' ? ['query' => $data] : ['json' => $data];
        $response = $this->authorised()->send(strtoupper($method), $this->baseUrl().$path, $options);

        if ($response->status() === 401) {
            $this->token(fresh: true);
            $response = $this->authorised()->send(strtoupper($method), $this->baseUrl().$path, $options);
        }

        if ($response->failed()) {
            throw new RequestException($response);
        }

        return (array) $response->json();
    }

    private function authorised(): PendingRequest
    {
        return Http::withToken($this->token())->acceptJson()->timeout(20);
    }
}
