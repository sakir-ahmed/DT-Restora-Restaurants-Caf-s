<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * bKash Tokenized Checkout: create -> customer pays -> callback runs
 * execute payment, then query payment. Only the query result marks a
 * payment completed.
 */
class BkashService implements PaymentGateway
{
    public function key(): string
    {
        return 'bkash';
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.bkash.app_key')
            && (bool) config('services.bkash.app_secret')
            && (bool) config('services.bkash.username')
            && (bool) config('services.bkash.password');
    }

    /**
     * @return array{redirect_url: string, gateway_payment_id: ?string}
     */
    public function createPayment(Order $order, string $amount, string $callbackUrl): array
    {
        $response = Http::withToken($this->grantToken())
            ->withHeaders(['X-APP-Key' => (string) config('services.bkash.app_key')])
            ->post($this->url('/tokenized/checkout/create'), [
                'mode' => '0011',
                'payerReference' => $order->invoice_no,
                'callbackURL' => $callbackUrl,
                'amount' => $amount,
                'currency' => 'BDT',
                'intent' => 'sale',
                'merchantInvoiceNumber' => $order->invoice_no,
            ])
            ->throw()
            ->json();

        if (($response['statusCode'] ?? '') !== '0000') {
            throw new GatewayException('bKash create failed: '.($response['statusMessage'] ?? 'unknown'));
        }

        return [
            'redirect_url' => (string) $response['bkashURL'],
            'gateway_payment_id' => $response['paymentID'] ?? null,
        ];
    }

    /**
     * Execute (if needed) and query the payment server-to-server, then
     * compare the amount. Idempotent by bKash paymentID.
     *
     * @return array{status: 'completed'|'failed', message: string, trx_id: ?string}
     */
    public function executeAndVerify(string $paymentId, string $expectedAmount): array
    {
        $execute = Http::withToken($this->grantToken())
            ->withHeaders(['X-APP-Key' => (string) config('services.bkash.app_key')])
            ->post($this->url('/tokenized/checkout/execute'), ['paymentID' => $paymentId])
            ->json();

        // 0001 = already completed earlier; safe to continue to query.
        if (! in_array($execute['statusCode'] ?? '', ['0000', '0001', '2011'], true)) {
            return ['status' => 'failed', 'message' => $execute['statusMessage'] ?? 'execute failed', 'trx_id' => null];
        }

        $verified = $this->verifyPayment($paymentId, $expectedAmount);
        $verified['trx_id'] = $this->trxIdFor($paymentId);

        return $verified;
    }

    /**
     * @return array{status: 'completed'|'failed', message: string}
     */
    public function verifyPayment(string $gatewayPaymentId, string $expectedAmount): array
    {
        $query = Http::withToken($this->grantToken())
            ->withHeaders(['X-APP-Key' => (string) config('services.bkash.app_key')])
            ->post($this->url('/tokenized/checkout/payment/status'), ['paymentID' => $gatewayPaymentId])
            ->throw()
            ->json();

        if (($query['transactionStatus'] ?? '') !== 'Completed') {
            return ['status' => 'failed', 'message' => 'Transaction not completed at bKash.'];
        }

        if (number_format((float) ($query['amount'] ?? 0), 2, '.', '') !== number_format((float) $expectedAmount, 2, '.', '')) {
            return ['status' => 'failed', 'message' => 'Amount mismatch.'];
        }

        return ['status' => 'completed', 'message' => 'Verified with bKash.'];
    }

    public function trxIdFor(string $paymentId): ?string
    {
        $query = Http::withToken($this->grantToken())
            ->withHeaders(['X-APP-Key' => (string) config('services.bkash.app_key')])
            ->post($this->url('/tokenized/checkout/payment/status'), ['paymentID' => $paymentId])
            ->json();

        return $query['trxID'] ?? null;
    }

    private function grantToken(): string
    {
        return Cache::remember('bkash:grant-token', now()->addMinutes(50), function (): string {
            $response = Http::withHeaders([
                'username' => (string) config('services.bkash.username'),
                'password' => (string) config('services.bkash.password'),
            ])
                ->post($this->url('/tokenized/checkout/token/grant'), [
                    'app_key' => config('services.bkash.app_key'),
                    'app_secret' => config('services.bkash.app_secret'),
                ])
                ->throw()
                ->json();

            if (empty($response['id_token'])) {
                throw new GatewayException('bKash token grant failed.');
            }

            return (string) $response['id_token'];
        });
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.bkash.base_url'), '/').$path;
    }
}
