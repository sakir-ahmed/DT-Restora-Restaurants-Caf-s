<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $themeColor }}">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ __('pwa.offline_title', [], 'en') }} - {{ $brandName }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: {{ $backgroundColor }};
            color: {{ $textColor }};
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans Bengali", sans-serif;
            line-height: 1.5;
        }
        main { max-width: 26rem; text-align: center; }
        img { width: 72px; height: 72px; object-fit: contain; margin-bottom: 16px; }
        h1 { margin: 0 0 4px; font-size: 1.25rem; }
        p { margin: 0 0 16px; font-size: 0.9rem; opacity: 0.8; }
        section + section { margin-top: 20px; padding-top: 20px; border-top: 1px solid rgb(128 128 128 / 0.3); }
        .actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
        a, button {
            min-height: 40px;
            padding: 0 16px;
            border: 1px solid rgb(128 128 128 / 0.5);
            border-radius: 8px;
            background: transparent;
            color: inherit;
            font: inherit;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
        }
        button[data-retry] { background: {{ $accentColor }}; border-color: {{ $accentColor }}; color: {{ $accentTextColor }}; }
        a:focus-visible, button:focus-visible { outline: 2px solid {{ $accentColor }}; outline-offset: 2px; }
    </style>
</head>
<body>
    <main role="main">
        <img src="{{ $logo }}" alt="{{ $brandName }}" width="72" height="72" onerror="this.remove()">

        @foreach ($languages as $language)
            <section lang="{{ $language }}">
                <h1>{{ __('pwa.offline_title', [], $language) }}</h1>
                <p>{{ __('pwa.offline_message', [], $language) }}</p>
                <div class="actions">
                    <button type="button" data-retry>{{ __('pwa.retry', [], $language) }}</button>
                    <a href="/admin/pos">{{ __('pwa.open_pos', [], $language) }}</a>
                </div>
            </section>
        @endforeach
    </main>

    <script>
        document.querySelectorAll('[data-retry]').forEach(function (button) {
            button.addEventListener('click', function () { location.reload(); });
        });
        window.addEventListener('online', function () { location.reload(); });
    </script>
</body>
</html>
