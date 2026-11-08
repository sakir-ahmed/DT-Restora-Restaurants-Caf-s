<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Actions\Sales\CompleteGatewayPaymentAction;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public gateway callbacks and IPN (web routes, CSRF-excluded). The
 * browser payload is never trusted: every completion runs a
 * server-to-server verification first.
 */
class GatewayCallbackController extends Controller
{
    public function bkashCallback(Request $request, CompleteGatewayPaymentAction $action): Response
    {
        $paymentId = (string) $request->query('paymentID', '');

        if ($paymentId === '') {
            return $this->resultPage(false, 'Missing payment reference.');
        }

        $payment = Payment::query()
            ->where('gateway', 'bkash')
            ->where('gateway_payment_id', $paymentId)
            ->first();

        if ($payment === null) {
            return $this->resultPage(false, 'Unknown payment.');
        }

        if ($payment->status === Payment::STATUS_COMPLETED) {
            // Duplicate callback: acknowledged, nothing double-pays.
            return $this->resultPage(true, 'Already verified.');
        }

        if ($request->query('status') === 'failure' || $request->query('status') === 'cancel') {
            $payment->update(['status' => Payment::STATUS_FAILED]);

            return $this->resultPage(false, 'Payment was cancelled.');
        }

        $result = $action->verifyAndCompleteBkash($payment);

        return $this->resultPage($result->status === Payment::STATUS_COMPLETED, 'bKash payment '.$result->status.'.');
    }

    public function sslcommerzIpn(Request $request, CompleteGatewayPaymentAction $action): Response
    {
        $valId = (string) $request->input('val_id', '');

        $invoice = (string) $request->input('tran_id', '');

        if ($valId === '' || $invoice === '') {
            return response('Bad request', 400);
        }

        $payment = Payment::query()
            ->where('gateway', 'sslcommerz')
            ->whereHas('order', fn ($query) => $query->where('invoice_no', $invoice))
            ->where('status', Payment::STATUS_PENDING)
            ->first();

        if ($payment === null) {
            // Duplicates and unknowns are acknowledged quickly.
            return response('OK');
        }

        $action->verifyAndCompleteSslcommerz($payment, $valId);

        return response('OK');
    }

    public function sslcommerzReturn(Request $request, CompleteGatewayPaymentAction $action): Response
    {
        $valId = (string) $request->input('val_id', '');

        $invoice = (string) $request->input('tran_id', '');

        if ($valId !== '' && $invoice !== '') {
            $payment = Payment::query()
                ->where('gateway', 'sslcommerz')
                ->whereHas('order', fn ($query) => $query->where('invoice_no', $invoice))
                ->first();

            if ($payment !== null && $payment->status === Payment::STATUS_PENDING) {
                $action->verifyAndCompleteSslcommerz($payment, $valId);
            }
        }

        $status = $request->query('status', 'success');

        return $this->resultPage($status === 'success', 'SSLCommerz payment '.$status.'.');
    }

    private function resultPage(bool $success, string $message): Response
    {
        return response()->view('payments.result', [
            'success' => $success,
            'message' => $message,
        ]);
    }
}
