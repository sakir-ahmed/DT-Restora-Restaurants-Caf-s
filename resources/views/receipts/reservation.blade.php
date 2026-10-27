<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reservation->booking_no }}</title>
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
        $deposit = (float) $reservation->deposit_amount;
    @endphp

    <div class="center bold large">{{ $reservation->branch->name }}</div>
    @if ($reservation->branch->address)
        <div class="center">{{ $reservation->branch->address }}</div>
    @endif
    @if ($reservation->branch->phone)
        <div class="center">{{ __('receipt.phone') }}: {{ $reservation->branch->phone }}</div>
    @endif

    <div class="hr"></div>

    <div class="center bold large">{{ __('reservations.slip_title') }}</div>
    <div class="center bold huge">{{ $reservation->booking_no }}</div>
    <div class="center small">{{ __('reservations.status_'.$reservation->status) }}</div>

    <div class="hr"></div>

    <table>
        <tr>
            <td>{{ __('reservations.guest_name') }}</td>
            <td class="right bold">{{ $reservation->guest_name }}</td>
        </tr>
        @if ($reservation->guest_mobile)
            <tr>
                <td>{{ __('reservations.guest_mobile') }}</td>
                <td class="right">{{ $reservation->guest_mobile }}</td>
            </tr>
        @endif
        <tr>
            <td>{{ __('reservations.party_size') }}</td>
            <td class="right">{{ $reservation->party_size }}</td>
        </tr>
        <tr>
            <td>{{ $reservation->tables->count() > 1 ? __('reservations.tables') : __('reservations.table') }}</td>
            <td class="right bold">{{ $reservation->tables->pluck('name')->implode(', ') }}</td>
        </tr>
    </table>

    <div class="boxed center">
        <div class="small">{{ __('reservations.slip_when') }}</div>
        {{-- Shown in the clock of the device that prints it; the text is the server's time until the script runs. --}}
        <div class="bold large"><time class="when" datetime="{{ $reservation->starts_at->toIso8601String() }}" data-part="date">{{ $reservation->starts_at->translatedFormat('D j M Y') }}</time></div>
        <div class="bold large">
            <time class="when" datetime="{{ $reservation->starts_at->toIso8601String() }}" data-part="time">{{ $reservation->starts_at->translatedFormat('g:i A') }}</time>
            -
            <time class="when" datetime="{{ $reservation->ends_at->toIso8601String() }}" data-part="time">{{ $reservation->ends_at->translatedFormat('g:i A') }}</time>
        </div>
    </div>

    @if ($reservation->note)
        <div>{{ __('reservations.note') }}: {{ $reservation->note }}</div>
    @endif

    @if (! empty($reservation->preorder))
        <div class="hr"></div>
        <div class="bold">{{ __('reservations.slip_preorder') }}</div>
        <table>
            @foreach ($reservation->preorder as $line)
                <tr>
                    <td class="bold">{{ $line['quantity'] }} x {{ $locale === 'bn' && ! empty($line['name_bn']) ? $line['name_bn'] : $line['name'] }}@if (! empty($line['variant_name'])) ({{ $locale === 'bn' && ! empty($line['variant_name_bn']) ? $line['variant_name_bn'] : $line['variant_name'] }})@endif</td>
                    <td class="right">{{ $money($line['line_total']) }}</td>
                </tr>
                @foreach ($line['add_ons'] ?? [] as $addOn)
                    <tr>
                        <td class="small" colspan="2">&nbsp;&nbsp;+ {{ $addOn['qty'] }} x {{ $addOn['name'] }}</td>
                    </tr>
                @endforeach
            @endforeach
            <tr>
                <td class="bold">{{ __('reservations.slip_preorder_total') }}</td>
                <td class="right bold">{{ $money($reservation->preorder_total) }}</td>
            </tr>
        </table>
        <div class="small">{{ __('reservations.slip_preorder_hint') }}</div>
    @endif

    @if ($deposit > 0)
        <div class="hr"></div>
        <div class="bold">{{ __('reservations.slip_deposit') }}</div>
        <table>
            <tr>
                <td>{{ __('reservations.slip_deposit_received') }}</td>
                <td class="right bold">Tk {{ $money($deposit) }}</td>
            </tr>
            <tr>
                <td>{{ __('reservations.deposit_method') }}</td>
                <td class="right">{{ $depositMethod?->displayName($locale) ?? $reservation->deposit_method }}</td>
            </tr>
            @if ($reservation->deposit_reference)
                <tr>
                    <td>{{ __('reservations.deposit_reference') }}</td>
                    <td class="right">{{ $reservation->deposit_reference }}</td>
                </tr>
            @endif
            @if ((float) $reservation->deposit_applied > 0)
                <tr>
                    <td>{{ __('reservations.slip_deposit_applied') }}</td>
                    <td class="right">Tk {{ $money($reservation->deposit_applied) }}</td>
                </tr>
            @endif
            <tr>
                <td>{{ __('reservations.deposit_status') }}</td>
                <td class="right bold">{{ __('reservations.deposit_'.$reservation->deposit_status) }}</td>
            </tr>
        </table>
    @endif

    <div class="hr"></div>

    <div class="small">{{ __('reservations.slip_terms') }}</div>

    @if ($reservation->branch->receipt_footer)
        <div class="hr"></div>
        <div class="center">{{ $reservation->branch->receipt_footer }}</div>
    @endif
    <div class="center">{{ __('reservations.slip_thanks') }}</div>

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
    </script>
</body>
</html>
