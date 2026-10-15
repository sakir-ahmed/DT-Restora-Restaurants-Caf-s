<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Noto Sans Bengali', 'DejaVu Sans', sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .meta { color: #555; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #ddd; padding: 4px 6px; }
        th { background: #f3f4f6; text-align: left; font-size: 10px; text-transform: none; }
        td.num, th.num { text-align: right; }
        @page { margin: 12mm; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="meta">
        {{ $branchName }} |
        {{ $from }} - {{ $to }} |
        {{ __('reports.generated_at') }}: {{ $generatedAt }}
    </p>

    <table>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th class="{{ in_array($column['type'] ?? '', ['money', 'number']) ? 'num' : '' }}">{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($columns as $column)
                        <td class="{{ in_array($column['type'] ?? '', ['money', 'number']) ? 'num' : '' }}">{{ $row[$column['key']] ?? '' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}">{{ __('common.empty_default') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
