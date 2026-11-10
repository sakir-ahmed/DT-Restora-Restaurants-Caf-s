<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\PosSettings;
use App\Support\ReceiptStyle;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HtmlResponse;
use Illuminate\Validation\Rule;

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
                'paper' => PosSettings::for($order->branch)['invoice_paper'],
            ])
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * A sample invoice in the chosen style and paper size, for the preview and
     * test print in Settings. Nothing is saved; the order is built in memory.
     */
    public function preview(Request $request): HtmlResponse
    {
        $this->authorize('settings.manage');

        $data = $request->validate([
            'layout' => ['required', Rule::in(ReceiptStyle::LAYOUTS)],
            'paper' => ['required', Rule::in(ReceiptStyle::PAPERS)],
        ]);

        $user = $request->user('staff');
        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0)) ?? Branch::query()->orderBy('id')->firstOrFail();

        $order = new Order([
            'invoice_no' => 'BR01-20260101-T01-0001',
            'order_type' => OrderType::DineIn,
            'subtotal' => '1050.00',
            'discount_amount' => '50.00',
            'service_charge' => '50.00',
            'extra_charge' => '0.00',
            'vat_amount' => '75.00',
            'rounding' => '0.00',
            'total' => '1125.00',
        ]);
        $order->created_at = now();
        $order->setRelation('branch', $branch);
        $order->setRelation('table', null);
        $order->setRelation('waiter', $user);
        $order->setRelation('payments', collect([new Payment(['method' => 'cash', 'amount' => '1200.00', 'change_due' => '75.00'])]));
        $order->setRelation('items', collect([
            new OrderItem(['name' => 'Chicken Biryani', 'name_bn' => 'চিকেন বিরিয়ানি', 'unit_price' => '350.00', 'quantity' => 2, 'line_total' => '700.00', 'add_ons' => [['name' => 'Extra egg', 'qty' => 1, 'price' => '30.00']]]),
            new OrderItem(['name' => 'Beef Tehari', 'name_bn' => 'বিফ তেহারি', 'unit_price' => '320.00', 'quantity' => 1, 'line_total' => '320.00', 'add_ons' => null]),
        ]));

        return response()
            ->view('receipts.order', [
                'order' => $order,
                'isReprint' => false,
                'isSample' => true,
                'layout' => $data['layout'],
                'paper' => $data['paper'],
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
