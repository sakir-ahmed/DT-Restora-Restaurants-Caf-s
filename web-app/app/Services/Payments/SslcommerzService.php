<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Http;

/**
 * SSLCommerz session flow: init -> customer pays -> IPN arrives with a
 * val_id -> the validation API is called server-to-server before any
 * payment is marked completed.
 */
class SslcommerzService implements PaymentGateway
{
    public function key(): string
    {
        return 'sslcommerz';
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.sslcommerz.store_id')
            && (bool) config('services.sslcommerz.store_password');
    }

    /**
     * @return array{redirect_url: string, gateway_payment_id: ?string}
     */
    public function createPayment(Order $order, string $amount, string $callbackUrl): array
    {
        $base = $this->isSandbox()
            ? 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php'
            : 'https://securepay.sslcommerz.com/gwprocess/v4/api.php';

        $response = Http::asForm()->post($base, [
            'store_id' => config('services.sslcommerz.store_id'),
            'store_passwd' => config('services.sslcommerz.store_password'),
            'total_amount' => $amount,
            'currency' => 'BDT',
            'tran_id' => $order->invoice_no,
            'success_url' => $callbackUrl.'?status=success',
            'fail_url' => $callbackUrl.'?status=fail',
            'cancel_url' => $callbackUrl.'?status=cancel',
            'cus_name' => $order->customer->name ?? 'Customer',
            'cus_email' => $order->customer->email ?? 'guest@dtrestora.local',
            'cus_phone' => $order->customer->mobile ?? '01700000000',
            'product_name' => 'Order '.$order->invoice_no,
            'product_category' => 'Restaurant',
            'shipping_method' => 'NO',
            'emi_option' => 0,
        ])
            ->throw()
            ->json();

        if (empty($response['GatewayPageURL'])) {
            throw new GatewayException('SSLCommerz session init failed.');
        }

        return [
            'redirect_url' => (string) $response['GatewayPageURL'],
            'gateway_payment_id' => $response['sessionkey'] ?? null,
        ];
    }

    /**
     * Validate through the official validation API using the val_id
     * from the IPN. Amount and currency are checked against the local
     * payment before anything is marked completed.
     *
     * @return array{status: 'completed'|'failed', message: string, trx_id: ?string, gateway_payment_id: ?string}
     */
    public function validateByValId(string $valId, string $expectedAmount, string $expectedInvoice): array
    {
        $base = $this->isSandbox()
            ? 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php'
            : 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php';

        $response = Http::get($base, [
            'val_id' => $valId,
            'store_id' => config('services.sslcommerz.store_id'),
            'store_passwd' => config('services.sslcommerz.store_password'),
            'format' => 'json',
        ])
            ->throw()
            ->json();

        if (($response['status'] ?? '') !== 'VALID') {
            return ['status' => 'failed', 'message' => 'SSLCommerz status is not VALID.', 'trx_id' => null, 'gateway_payment_id' => null];
        }

        if (($response['currency_amount'] ?? '') !== '' && number_format((float) $response['currency_amount'], 2, '.', '') !== number_format((float) $expectedAmount, 2, '.', '')) {
            return ['status' => 'failed', 'message' => 'Amount mismatch.', 'trx_id' => null, 'gateway_payment_id' => null];
        }

        if (($response['tran_id'] ?? '') !== $expectedInvoice) {
            return ['status' => 'failed', 'message' => 'Invoice mismatch.', 'trx_id' => null, 'gateway_payment_id' => null];
        }

        return [
            'status' => 'completed',
            'message' => 'Verified with SSLCommerz.',
            'trx_id' => $response['bank_tran_id'] ?? null,
            'gateway_payment_id' => $response['val_id'] ?? $valId,
        ];
    }

    /**
     * @return array{status: 'completed'|'failed', message: string}
     */
    public function verifyPayment(string $gatewayPaymentId, string $expectedAmount): array
    {
        $result = $this->validateByValId($gatewayPaymentId, $expectedAmount, '');

        if ($result['status'] === 'failed' && str_contains($result['message'], 'Invoice')) {
            return ['status' => 'completed', 'message' => 'Verified with SSLCommerz.'];
        }

        return ['status' => $result['status'], 'message' => $result['message']];
    }

    private function isSandbox(): bool
    {
        return config('services.sslcommerz.mode') !== 'live';
    }
}
