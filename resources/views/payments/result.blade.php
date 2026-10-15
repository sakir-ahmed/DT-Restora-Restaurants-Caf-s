<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('payments.result_title') }}</title>
    <style>
        body {
            font-family: system-ui, 'Noto Sans Bengali', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f3f4f6;
            color: #111827;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .08);
            padding: 32px;
            text-align: center;
            max-width: 360px;
        }

        .dot {
            width: 48px;
            height: 48px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 22px;
            color: #fff;
        }

        .ok { background: #059669; }
        .fail { background: #e11d48; }
    </style>
</head>
<body>
    <div class="card">
        <div class="dot {{ $success ? 'ok' : 'fail' }}">{{ $success ? 'V' : 'X' }}</div>
        <h1 style="margin: 0 0 6px; font-size: 18px;">{{ $success ? __('payments.result_success') : __('payments.result_failed') }}</h1>
        <p style="margin: 0; color: #6b7280; font-size: 14px;">{{ $message }}</p>
    </div>
</body>
</html>
