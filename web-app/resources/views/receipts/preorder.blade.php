<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $preorder->order_no }}</title>
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
        .huge { font-size: 17px; letter-spacing: 1px; }

        .hr {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 1px 0;
            vertical-align: top;
        }

        td.right { text-align: right; }
        .small { font-size: 10px; }
        .boxed { border: 1px solid #000; padding: 4px; margin: 4px 0; }

        body.layout-compact { font-size: 10px; line-height: 1.3; }
        body.layout-compact .hr { margin: 3px 0; }
        body.layout-bold { font-size: 12px; }
        body.layout-bold .hr { border-top: 2px solid #000; }

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
<body class="layout-{{ $layout ?? 'classic' }}">
    @php
        $locale = app()->getLocale();
        $money = fn ($value) => \Illuminate\Support\Number::format((float) $value, 2);
    @endphp

    <div class="center bold large">{{ $preorder->branch->name }}</div>
    @if ($preorder->branch->address)
        <div class="center">{{ $preorder->branch->address }}</div>
    @endif
    @if ($preorder->branch->phone)
        <div class="center">{{ __('receipt.phone') }}: {{ $preorder->branch->phone }}</div>
    @endif

    <div class="hr"></div>

    <div class="center bold large">{{ __('preorders.slip_title') }}</div>
    <div class="center bold huge">{{ $preorder->order_no }}</div>
    <div class="center small">{{ __('preorders.status.'.$preorder->status) }}</div>

    <div class="hr"></div>

    <table>
        <tr>
            <td>{{ __('preorders.slip_guest') }}</td>
            <td class="right bold">{{ $preorder->customer_name ?: __('preorders.walk_in') }}</td>
        </tr>
        @if ($preorder->customer_mobile)
            <tr>
                <td>{{ __('preorders.mobile') }}</td>
                <td class="right">{{ $preorder->customer_mobile }}</td>
            </tr>
        @endif
        @if ($preorder->party_size)
            <tr>
                <td>{{ __('preorders.party_size') }}</td>
                <td class="right">{{ $preorder->party_size }}</td>
            </tr>
        @endif
        @if ($preorder->table)
            <tr>
                <td>{{ __('preorders.table') }}</td>
                <td class="right bold">{{ $preorder->table->name }}</td>
            </tr>
        @endif
    </table>

    @if ($preorder->expected_at)
        <div class="boxed center">
            <div class="small">{{ __('preorders.slip_for') }}</div>
            {{-- Shown in the clock of the device that prints it; the text is the server's time until the script runs. --}}
            <div class="bold large"><time class="when" datetime="{{ $preorder->expected_at->toIso8601String() }}" data-part="date">{{ $preorder->expected_at->translatedFormat('D j M Y') }}</time></div>
            <div class="bold large"><time class="when" datetime="{{ $preorder->expected_at->toIso8601String() }}" data-part="time">{{ $preorder->expected_at->translatedFormat('g:i A') }}</time></div>
        </div>
    @endif

    @if ($preorder->note)
        <div>{{ __('preorders.note') }}: {{ $preorder->note }}</div>
    @endif

    <div class="hr"></div>
    <table>
        @foreach ($preorder->items as $line)
            <tr>
                <td class="bold">{{ $line['quantity'] }} x {{ $locale === 'bn' && ! empty($line['name_bn']) ? $line['name_bn'] : $line['name'] }}@if (! empty($line['variant_name'])) ({{ $locale === 'bn' && ! empty($line['variant_name_bn']) ? $line['variant_name_bn'] : $line['variant_name'] }})@endif</td>
                <td class="right">{{ $money($line['line_total']) }}</td>
            </tr>
            @foreach ($line['add_ons'] ?? [] as $addOn)
                <tr>
                    <td class="small" colspan="2">&nbsp;&nbsp;+ {{ $addOn['qty'] }} x {{ $locale === 'bn' && ! empty($addOn['name_bn']) ? $addOn['name_bn'] : $addOn['name'] }}</td>
                </tr>
            @endforeach
        @endforeach
        <tr>
            <td class="bold">{{ __('preorders.slip_total') }}</td>
            <td class="right bold">{{ $money($preorder->total) }}</td>
        </tr>
    </table>

    <div class="hr"></div>
    <div class="small">{{ __('preorders.slip_note') }}</div>

    @if ($preorder->branch->receipt_footer)
        <div class="hr"></div>
        <div class="center">{{ $preorder->branch->receipt_footer }}</div>
    @endif
    <div class="center">{{ __('preorders.slip_thanks') }}</div>

    <div class="center no-print" style="margin-top: 12px;">
        <button onclick="window.print()" style="padding: 6px 16px; font-size: 12px;">{{ __('receipt.print') }}</button>
    </div>

    <script>
        document.querySelectorAll('time.when').forEach(function (element) {
            var date = new Date(element.getAttribute('datetime'));

            if (isNaN(date.getTime())) {
                return;
            }

            element.textContent = element.dataset.part === 'date'
                ? date.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
                : date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        });

        @if ($autoPrint ?? false)
            window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
        @endif
    </script>
</body>
</html>
