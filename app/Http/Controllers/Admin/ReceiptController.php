<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PosSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HtmlResponse;

/**
 * 80mm thermal receipt as printable HTML. The browser print dialog
 * targets an 80mm paper size through the receipt CSS.
 */
class ReceiptController extends Controller
{
    public function show(Request $request, Order $order): HtmlResponse
    {
        $this->authorize('orders.view');

        abort_unless($request->user('staff')->canAccessBranch($order->branch_id), 404);

        $order->load(['items', 'branch', 'table', 'waiter', 'payments']);

        $isReprint = $request->boolean('reprint');

        if ($isReprint) {
            activity()
                ->performedOn($order)
                ->causedBy($request->user('staff'))
                ->log('order.receipt_reprinted');
        }

        return response()
            ->view('receipts.order', [
                'order' => $order,
                'isReprint' => $isReprint,
                'layout' => PosSettings::for($order->branch)['invoice_layout'],
            ])
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Small token slip for the kitchen: order number, type, table and the
     * items with their add-ons, no prices.
     */
    public function kitchenToken(Request $request, Order $order): HtmlResponse
    {
        $this->authorize('orders.view');

        abort_unless($request->user('staff')->canAccessBranch($order->branch_id), 404);

        $order->load(['items', 'branch', 'table']);

        return response()
            ->view('receipts.kitchen', ['order' => $order])
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
