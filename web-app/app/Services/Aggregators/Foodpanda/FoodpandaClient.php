<?php

declare(strict_types=1);

namespace App\Services\Aggregators\Foodpanda;

use App\Models\AggregatorChannel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the Foodpanda Partner API v2 (Delivery Hero).
 * OAuth 2.0 client credentials: the bearer token is cached until it
 * expires (the token endpoint is limited to 50 calls a minute).
 *
 * @see https://developer.foodpanda.com/api-specifications
 */
class FoodpandaClient
{
    public const PRODUCTION_URL = 'https://foodpanda.partner.deliveryhero.io';

    public const SANDBOX_URL = 'https://sandbox.partner.deliveryhero.io';

    public function __construct(private readonly AggregatorChannel $channel) {}

    public function baseUrl(): string
    {
        return $this->channel->environment === 'production' ? self::PRODUCTION_URL : self::SANDBOX_URL;
    }

    /**
     * Fetch (or reuse) the bearer token.
     */
    public function token(bool $fresh = false): string
    {
        if (! $this->channel->hasApiCredentials()) {
            throw new RuntimeException(__('aggregators.credentials_missing'));
        }

        $cacheKey = 'foodpanda.token.'.$this->channel->id.'.'.$this->channel->environment;

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post($this->baseUrl().'/v2/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $this->channel->client_id,
                'client_secret' => $this->channel->client_secret,
            ]);

        if ($response->failed() || ! is_string($response->json('access_token'))) {
            throw new RuntimeException(__('aggregators.token_failed', ['status' => $response->status()]));
        }

        $token = (string) $response->json('access_token');
        $ttl = max(60, (int) ($response->json('expires_in') ?? 3600) - 120);

        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateOrder(string $orderId, array $payload): array
    {
        return $this->send('put', "/chains/{$this->chain()}/orders/{$orderId}", $payload);
    }

    /** @return array<string, mixed> */
    public function getOrder(string $orderId): array
    {
        return $this->send('get', "/chains/{$this->chain()}/orders/{$orderId}");
    }

    /**
     * Order history for one vendor, newest first. The platform only keeps 60 days.
     *
     * @return array<string, mixed>
     */
    public function listOrders(string $vendorId, \DateTimeInterface $from, \DateTimeInterface $to, int $page = 1, int $pageSize = 100): array
    {
        return $this->send('get', "/chains/{$this->chain()}/vendors/{$vendorId}/orders", [
            'start_time' => $from->format('Y-m-d\TH:i:s'),
            'end_time' => $to->format('Y-m-d\TH:i:s'),
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * Batch price, active flag and quantity update (asynchronous on the platform).
     *
     * @param  array<int, array{sku: string, price?: float|int, active?: bool, quantity?: int}>  $products
     * @return array<string, mixed>
     */
    public function updateProducts(string $vendorId, array $products): array
    {
        return $this->send('put', "/chains/{$this->chain()}/vendors/{$vendorId}/catalog", ['products' => $products]);
    }

    /** @return array<string, mixed> */
    public function getProducts(string $vendorId, int $page = 1, int $pageSize = 100): array
    {
        return $this->send('get', "/chains/{$this->chain()}/vendors/{$vendorId}/catalog", ['page' => $page, 'page_size' => $pageSize]);
    }

    /** @return array<string, mixed> */
    public function categories(string $vendorId): array
    {
        return $this->send('get', "/chains/{$this->chain()}/vendors/{$vendorId}/categories");
    }

    /** @return array<string, mixed> */
    public function vendorStatus(string $vendorId): array
    {
        return $this->send('get', "/chains/{$this->chain()}/vendors/{$vendorId}/status");
    }

    /**
     * Open, close for today, close until a time, or acknowledge a check-in.
     *
     * @return array<string, mixed>
     */
    public function setVendorStatus(string $vendorId, string $status, ?string $reason = null, ?string $closedUntil = null): array
    {
        return $this->send('put', "/chains/{$this->chain()}/vendors/{$vendorId}/status", array_filter([
            'status' => $status,
            'closed_reason' => $status === 'OPEN' ? null : $reason,
            'closed_until' => $status === 'CLOSED_UNTIL' ? $closedUntil : null,
        ], fn ($value): bool => $value !== null));
    }

    private function chain(): string
    {
        return (string) $this->channel->chain_id;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $data = []): array
    {
        $response = $this->authorised()->send(strtoupper($method), $this->baseUrl().'/v2'.$path, $method === 'get' ? ['query' => $data] : ['json' => $data]);

        if ($response->status() === 401) {
            // The cached token may have been revoked: fetch a new one and retry once.
            $this->token(fresh: true);
            $response = $this->authorised()->send(strtoupper($method), $this->baseUrl().'/v2'.$path, $method === 'get' ? ['query' => $data] : ['json' => $data]);
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
