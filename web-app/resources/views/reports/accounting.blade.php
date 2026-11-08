<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Noto Sans Bengali', 'DejaVu Sans', sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 14px 0 4px; }
        .meta { color: #555; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #ddd; padding: 3px 6px; }
        th { background: #f3f4f6; text-align: left; font-size: 10px; }
        td.num, th.num { text-align: right; }
        .total { font-weight: 700; border-top: 2px solid #111; }
        @page { margin: 12mm; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="meta">{{ $from }} - {{ $to }}</p>

    @if ($statement === 'trial-balance')
        <table>
            <thead>
                <tr><th>{{ __('accounting.accounts') }}</th><th class="num">{{ __('accounting.debit') }}</th><th class="num">{{ __('accounting.credit') }}</th></tr>
            </thead>
            <tbody>
                @foreach ($data['rows'] as $row)
                    <tr><td>{{ $row['code'] }} - {{ $row['name'] }}</td><td class="num">{{ $row['debit'] }}</td><td class="num">{{ $row['credit'] }}</td></tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total"><td>{{ __('common.total') }}</td><td class="num">{{ $data['debits'] }}</td><td class="num">{{ $data['credits'] }}</td></tr>
            </tfoot>
        </table>
    @elseif ($statement === 'profit-loss')
        <table>
            <tr><td>{{ __('accounting.revenue') }}</td><td class="num">{{ $data['revenue'] }}</td></tr>
            <tr><td>{{ __('accounting.cogs') }}</td><td class="num">{{ $data['cogs'] }}</td></tr>
            <tr class="total"><td>{{ __('accounting.gross_profit') }}</td><td class="num">{{ $data['gross_profit'] }}</td></tr>
            @foreach ($data['expenses'] as $expense)
                <tr><td>{{ $expense['name'] }}</td><td class="num">{{ $expense['amount'] }}</td></tr>
            @endforeach
            <tr class="total"><td>{{ __('accounting.net_profit') }}</td><td class="num">{{ $data['net_profit'] }}</td></tr>
        </table>
    @elseif ($statement === 'balance-sheet')
        <h2>{{ __('accounting.assets') }}</h2>
        <table>
            @foreach ($data['assets'] as $row)
                <tr><td>{{ $row['name'] }}</td><td class="num">{{ $row['amount'] }}</td></tr>
            @endforeach
            <tr class="total"><td>{{ __('accounting.total_assets') }}</td><td class="num">{{ $data['total_assets'] }}</td></tr>
        </table>
        <h2>{{ __('accounting.liabilities') }}</h2>
        <table>
            @foreach ($data['liabilities'] as $row)
                <tr><td>{{ $row['name'] }}</td><td class="num">{{ $row['amount'] }}</td></tr>
            @endforeach
        </table>
        <h2>{{ __('accounting.equity') }}</h2>
        <table>
            @foreach ($data['equity'] as $row)
                <tr><td>{{ $row['name'] }}</td><td class="num">{{ $row['amount'] }}</td></tr>
            @endforeach
            <tr class="total"><td>{{ __('accounting.total_liabilities_equity') }}</td><td class="num">{{ $data['total_liabilities_equity'] }}</td></tr>
        </table>
    @else
        <table>
            <tr><td>{{ __('accounting.total_vat') }}</td><td class="num">{{ $data['total_vat'] }}</td></tr>
        </table>
    @endif
</body>
</html>
