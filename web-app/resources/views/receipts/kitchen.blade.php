<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $order->invoice_no }}</title>
    <style>
        @page {
            size: 58mm auto;
            margin: 0;
        }

        body {
            font-family: 'Noto Sans Bengali', 'Courier New', monospace;
            width: 52mm;
            margin: 0 auto;
            padding: 3mm 0;
            color: #000;
            font-size: 13px;
            line-height: 1.35;
        }

        .center { text-align: center; }
        .bold { font-weight: 700; }
        .large { font-size: 16px; }
        .hr { border-top: 2px dashed #000; margin: 5px 0; }
        .item { font-weight: 700; font-size: 14px; }
        .addon { padding-left: 10px; font-size: 12px; }

        @media screen {
            body {
                background: #f3f4f6;
                padding: 16px 0;
                box-shadow: 0 0 8px rgba(0, 0, 0, .25);
            }
        }

        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="center bold large">{{ __('receipt.kitchen_token') }}</div>
    <div class="center bold">{{ $order->invoice_no }}</div>
    <div class="center">{{ \Illuminate\Support\Carbon::parse($order->created_at)->format('d M Y, h:i A') }}</div>

    <div class="hr"></div>

    <div class="bold">{{ __('receipt.order_type.' . $order->order_type->value) }}
        @if ($order->table) - {{ __('receipt.table') }} {{ $order->table->name }} @endif
    </div>

    <div class="hr"></div>

    @foreach ($order->items as $item)
        <div class="item">{{ $item->quantity }} x {{ app()->getLocale() === 'bn' && $item->name_bn ? $item->name_bn : $item->name }}</div>
        @foreach ($item->add_ons ?? [] as $addOn)
            <div class="addon">+ {{ $addOn['qty'] ?? 1 }} x {{ $addOn['name'] }}</div>
        @endforeach
    @endforeach

    @if ($order->note)
        <div class="hr"></div>
        <div>{{ $order->note }}</div>
    @endif

    <div class="hr"></div>
</body>
</html>
