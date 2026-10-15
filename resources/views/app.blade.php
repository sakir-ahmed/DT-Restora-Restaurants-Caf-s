<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $seo['title'] ?? config('app.name', 'DT Restora') }}</title>

    @if ($seo ?? null)
        <meta name="description" content="{{ $seo['description'] ?? '' }}">
        <link rel="canonical" href="{{ $seo['canonical'] ?? url()->current() }}">
        <meta property="og:title" content="{{ $seo['title'] ?? '' }}">
        <meta property="og:description" content="{{ $seo['description'] ?? '' }}">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ $seo['canonical'] ?? url()->current() }}">
        <script type="application/ld+json">{{ json_encode($seo['jsonLd'] ?? []) }}</script>
    @endif

    <link rel="preconnect" href="{{ url('/') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script>
        (function () {
            try {
                var theme = localStorage.getItem('theme');

                if (theme === 'dark' || (! theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {
                /* ignore */
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
