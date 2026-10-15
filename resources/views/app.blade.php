@php
    $seo = $page['props']['seo'] ?? null;
    $brand = config('app.name', 'DT Restora');
    $title = $seo ? $seo['title'].' - '.$brand : $brand;
    $robots = $seo && ! ($seo['noindex'] ?? false) ? 'index,follow,max-image-preview:large' : 'noindex,nofollow';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1f1612">
    <meta data-inertia="robots" name="robots" content="{{ $robots }}">

    <title>{{ $title }}</title>

    @if ($seo)
        <meta data-inertia="description" name="description" content="{{ $seo['description'] }}">
        <link data-inertia="canonical" rel="canonical" href="{{ $seo['canonical'] }}">
        <meta data-inertia="og:site_name" property="og:site_name" content="{{ $brand }}">
        <meta data-inertia="og:type" property="og:type" content="{{ $seo['type'] }}">
        <meta data-inertia="og:locale" property="og:locale" content="{{ $seo['locale'] }}">
        <meta data-inertia="og:title" property="og:title" content="{{ $title }}">
        <meta data-inertia="og:description" property="og:description" content="{{ $seo['description'] }}">
        <meta data-inertia="og:url" property="og:url" content="{{ $seo['canonical'] }}">
        <meta data-inertia="og:image" property="og:image" content="{{ $seo['image'] }}">
        <meta data-inertia="twitter:card" name="twitter:card" content="summary_large_image">
        <meta data-inertia="twitter:title" name="twitter:title" content="{{ $title }}">
        <meta data-inertia="twitter:description" name="twitter:description" content="{{ $seo['description'] }}">
        <meta data-inertia="twitter:image" name="twitter:image" content="{{ $seo['image'] }}">
        @if (! empty($seo['jsonLd']))
            <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => $seo['jsonLd']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        @endif
    @endif

    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
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
