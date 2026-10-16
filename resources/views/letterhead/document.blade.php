<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Noto Sans Bengali', 'DejaVu Sans', sans-serif; font-size: 12px; color: #111; }
        .head { border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 16px; }
        .brand { display: table; width: 100%; }
        .logo, .identity { display: table-cell; vertical-align: middle; }
        .logo { width: 72px; }
        .logo img { width: 64px; height: 64px; object-fit: contain; }
        h1 { font-size: 18px; margin: 0; }
        .meta { color: #444; margin: 2px 0 0; font-size: 11px; }
        h2 { font-size: 14px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border-bottom: 1px solid #ddd; padding: 4px 6px; text-align: left; }
        td.num { text-align: right; }
        .body-space { min-height: 280px; }
        .sign { margin-top: 48px; width: 220px; border-top: 1px solid #111; padding-top: 4px; font-size: 11px; }
        .foot { margin-top: 24px; color: #666; font-size: 10px; }
        @page { margin: 16mm; }
    </style>
</head>
<body>
    <div class="head">
        <div class="brand">
            @if ($logo)
                <div class="logo"><img src="{{ $logo }}" alt=""></div>
            @endif
            <div class="identity">
                <h1>{{ $branchName }}</h1>
                @if ($address !== '')
                    <p class="meta">{{ $address }}</p>
                @endif
                @if ($phone)
                    <p class="meta">{{ $phone }}</p>
                @endif
            </div>
        </div>
    </div>

    @if ($letter)
        <p class="meta">{{ $letter['date'] }}</p>
        @if ($letter['recipient'])
            <p>{{ __('letterhead.to') }} {{ $letter['recipient'] }}</p>
        @endif
        <h2>{{ $letter['subject'] ?: $letter['title'] }}</h2>
        <div>{!! nl2br(e($letter['body'])) !!}</div>
    @elseif ($slip)
        <h2>{{ __('letterhead.salary_slip') }}</h2>
        <p class="meta">{{ __('letterhead.period') }}: {{ $slip['from'] }} - {{ $slip['to'] }}</p>
        <table>
            <tr><th>{{ __('letterhead.employee') }}</th><td>{{ $slip['employee'] }}</td></tr>
            <tr><th>{{ __('letterhead.designation') }}</th><td>{{ $slip['designation'] }}</td></tr>
            <tr><th>{{ __('letterhead.basis') }}</th><td>{{ __('staff.basis_'.$slip['basis']) }}</td></tr>
            <tr><th>{{ __('letterhead.rate') }}</th><td class="num">{{ $slip['rate'] }}</td></tr>
            <tr><th>{{ __('letterhead.days') }}</th><td class="num">{{ $slip['days'] }}</td></tr>
            <tr><th>{{ __('letterhead.hours') }}</th><td class="num">{{ $slip['hours'] }}</td></tr>
            <tr><th>{{ __('letterhead.gross') }}</th><td class="num">{{ $slip['gross'] }}</td></tr>
            <tr><th>{{ __('letterhead.advances') }}</th><td class="num">{{ $slip['advances'] }}</td></tr>
            <tr><th>{{ __('letterhead.net') }}</th><td class="num">{{ $slip['net'] }}</td></tr>
        </table>
    @endif

    <div class="sign">{{ __('letterhead.signature') }}</div>
    <p class="foot">{{ __('letterhead.printed', ['date' => $printedAt]) }}</p>
</body>
</html>
