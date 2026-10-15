<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;

/**
 * Nagad deep-link flow needs merchant cryptographic keys that most
 * installations only receive later. Until credentials exist the
 * adapter runs in manual-verification mode: the cashier records the
 * customer's TrxID and a manager reviews it.
 */
class NagadService implements PaymentGateway
{
    public function key(): string
    {
        return 'nagad';
    }

    public function isConfigured(): bool
    {
        return config('services.nagad.mode') === 'api'
            && (bool) config('services.nagad.merchant_id')
            && (bool) config('services.nagad.public_key')
            && (bool) config('services.nagad.private_key');
    }

    public function isManualMode(): bool
    {
        return ! $this->isConfigured();
    }

    /**
     * @return array{redirect_url: string, gateway_payment_id: ?string}
     */
    public function createPayment(Order $order, string $amount, string $callbackUrl): array
    {
        if ($this->isManualMode()) {
            throw new GatewayNotConfiguredException('Nagad runs in manual verification mode.');
        }

        // The full Nagad API flow is enabled once merchant credentials
        // and documentation are provided by the platform.
        throw new GatewayException('Nagad API adapter pending credentials.');
    }

    /**
     * @return array{status: 'completed'|'failed', message: string}
     */
    public function verifyPayment(string $gatewayPaymentId, string $expectedAmount): array
    {
        return ['status' => 'failed', 'message' => 'Nagad manual payments are verified by a manager.'];
    }
}
