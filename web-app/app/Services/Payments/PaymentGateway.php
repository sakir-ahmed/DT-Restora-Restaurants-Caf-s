<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;

/**
 * Contract shared by every payment gateway adapter (bKash, Nagad,
 * SSLCommerz). The POS, website and API never talk to a gateway
 * directly; they go through this interface.
 */
interface PaymentGateway
{
    /**
     * Unique gateway key matching config/services.php.
     */
    public function key(): string;

    /**
     * True when credentials are configured and the adapter can make
     * live calls. Nagad falls back to manual verification otherwise.
     */
    public function isConfigured(): bool;

    /**
     * Start a payment for the given amount and return the URL the
     * customer must open to pay.
     *
     * @return array{redirect_url: string, gateway_payment_id: ?string}
     */
    public function createPayment(Order $order, string $amount, string $callbackUrl): array;

    /**
     * Server-to-server verification callback handlers call this after
     * the gateway reports a payment. Must be idempotent: duplicate
     * callbacks must not double-pay, and a mismatched amount must be
     * rejected.
     *
     * @return array{status: 'completed'|'failed', message: string}
     */
    public function verifyPayment(string $gatewayPaymentId, string $expectedAmount): array;
}
