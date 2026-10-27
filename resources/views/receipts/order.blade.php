<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $order->invoice_no }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }

        body {
            font-family: 'Noto Sans Bengali', 'Courier New', monospace;
            width: 72mm;
            margin: 0 auto;
            padding: 4mm 0;
            color: #000;
            font-size: 11px;
            line-height: 1.45;
        }

        .center { text-align: center; }
        .bold { font-weight: 700; }
        .large { font-size: 13px; }

        .hr {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
        }

        table.items td {
            padding: 1px 0;
            vertical-align: top;
        }

        td.right, th.right { text-align: right; }

        .addon-line {
            padding-left: 8px;
            font-size: 10px;
        }

        .totals td {
            padding: 1px 0;
        }

        @media screen {
            body {
                background: #f3f4f6;
                padding: 16px 0;
                width: 72mm;
                box-shadow: 0 0 8px rgba(0, 0, 0, .25);
            }
        }

        /* Compact: tighter type and spacing for narrow rolls */
        body.layout-compact { font-size: 10px; line-height: 1.3; }
        body.layout-compact .large { font-size: 11px; }
        body.layout-compact .hr { margin: 3px 0; }
        body.layout-compact .addon-line { font-size: 9px; }

        /* Bold: large centered total and solid separators */
        body.layout-bold { font-size: 12px; }
        body.layout-bold .hr { border-top: 2px solid #000; }
        body.layout-bold .grand td { font-size: 16px; padding: 4px 0; border-top: 2px solid #000; border-bottom: 2px solid #000; }

        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body class="layout-{{ $layout ?? 'classic' }}">
    <div class="center bold large">{{ $order->branch->name }}</div>
    @if ($order->branch->name_bn)
        <div class="center">{{ $order->branch->name_bn }}</div>
    @endif
    @if ($order->branch->address)
        <div class="center">{{ $order->branch->address }}</div>
    @endif
    @if ($order->branch->phone)
        <div class="center">{{ __('receipt.phone') }}: {{ $order->branch->phone }}</div>
    @endif

    <div class="hr"></div>

    <div class="bold">{{ $order->invoice_no }}</div>
    <div>{{ \Illuminate\Support\Carbon::parse($order->created_at)->format('d M Y, h:i A') }}</div>
    <div>{{ __('receipt.order_type.' . $order->order_type->value) }}
        @if ($order->table) - {{ __('receipt.table') }} {{ $order->table->name }} @endif
    </div>
    @if ($order->waiter)
        <div>{{ __('receipt.served_by') }}: {{ $order->waiter->name }}</div>
    @endif

    <div class="hr"></div>

    <table class="items">
        @foreach ($order->items as $item)
            <tr>
                <td class="bold">{{ $item->quantity }} x {{ app()->getLocale() === 'bn' && $item->name_bn ? $item->name_bn : $item->name }}</td>
                <td class="right">{{ \Illuminate\Support\Number::format((float) $item->line_total, 2) }}</td>
            </tr>
            @if ($item->add_ons)
                @foreach ($item->add_ons as $addOn)
                    <tr>
                        <td class="addon-line">+ {{ $addOn['qty'] ?? 1 }} x {{ $addOn['name'] }}</td>
                        <td class="right addon-line">{{ \Illuminate\Support\Number::format(((float) $addOn['price']) * ($addOn['qty'] ?? 1), 2) }}</td>
                    </tr>
                @endforeach
            @endif
        @endforeach
    </table>

    <div class="hr"></div>

    <table class="totals">
        <tr>
            <td>{{ __('receipt.subtotal') }}</td>
            <td class="right">{{ \Illuminate\Support\Number::format((float) $order->subtotal, 2) }}</td>
        </tr>
        @if ((float) $order->discount_amount > 0)
            <tr>
                <td>{{ __('receipt.discount') }}</td>
                <td class="right">-{{ \Illuminate\Support\Number::format((float) $order->discount_amount, 2) }}</td>
            </tr>
        @endif
        @if ((float) $order->service_charge > 0)
            <tr>
                <td>{{ __('receipt.service_charge') }}</td>
                <td class="right">{{ \Illuminate\Support\Number::format((float) $order->service_charge, 2) }}</td>
            </tr>
        @endif
        @if ((float) $order->extra_charge > 0)
            <tr>
                <td>{{ __('receipt.other_charge') }}</td>
                <td class="right">{{ \Illuminate\Support\Number::format((float) $order->extra_charge, 2) }}</td>
            </tr>
        @endif
        @if ((float) $order->vat_amount > 0)
            <tr>
                <td>{{ __('receipt.vat') }} ({{ \Illuminate\Support\Number::format((float) $order->branch->vat_percent, 2) }}%)</td>
                <td class="right">{{ \Illuminate\Support\Number::format((float) $order->vat_amount, 2) }}</td>
            </tr>
        @endif
        @if ((float) $order->rounding != 0)
            <tr>
                <td>{{ __('receipt.rounding') }}</td>
                <td class="right">{{ \Illuminate\Support\Number::format((float) $order->rounding, 2) }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td class="bold large">{{ __('receipt.grand_total') }}</td>
            <td class="right bold large">Tk {{ \Illuminate\Support\Number::format((float) $order->total, 2) }}</td>
        </tr>
        @foreach ($order->payments as $payment)
            <tr>
                <td>{{ \App\Models\PaymentMethod::findByCode((string) $payment->method)?->displayName(app()->getLocale()) ?? $payment->method }}</td>
                <td class="right">{{ \Illuminate\Support\Number::format((float) $payment->amount, 2) }}</td>
            </tr>
            @if ($payment->change_due !== null && (float) $payment->change_due > 0)
                <tr>
                    <td>{{ __('receipt.change') }}</td>
                    <td class="right">{{ \Illuminate\Support\Number::format((float) $payment->change_due, 2) }}</td>
                </tr>
            @endif
        @endforeach
    </table>

    <div class="hr"></div>

    @if ($order->branch->receipt_footer)
        <div class="center">{{ $order->branch->receipt_footer }}</div>
    @endif
    <div class="center">{{ __('receipt.thank_you') }}</div>

    @if ($isReprint)
        <div class="center bold">{{ __('receipt.reprint') }}</div>
    @endif

    <div class="center no-print" style="margin-top: 12px;">
        <button onclick="window.print()" style="padding: 6px 16px; font-size: 12px;">{{ __('receipt.print') }}</button>
    </div>
</body>
</html>
