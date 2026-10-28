@php
    // The SSR response already carries the page title; print our own only when SSR is unavailable.
    $ssrResponse = app(\Inertia\Ssr\SsrState::class)->setPage($page)->dispatch();
    $seo = $page['props']['seo'] ?? null;
    $brand = config('app.name', "Arman's Kitchen");
    $title = $seo ? (collect([$brand, __('site.brand_name')])->contains(fn ($name) => str_contains($seo['title'], $name)) ? $seo['title'] : $seo['title'].' - '.__('site.brand_name')) : $brand;
    $robots = $seo && ! ($seo['noindex'] ?? false) ? 'index,follow,max-image-preview:large' : 'noindex,nofollow';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-brand="{{ \App\Support\Brand::mode() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ \App\Support\Brand::themeColor() }}">
    <meta name="application-name" content="{{ $brand }}">
    <meta name="format-detection" content="telephone=yes">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta data-inertia="robots" name="robots" content="{{ $robots }}">

    @unless ($ssrResponse)
        <title>{{ $title }}</title>
    @endunless

    @if ($seo)
        <link rel="alternate" type="application/rss+xml" title="{{ $brand }}" href="{{ route('web.blog.feed') }}">
        <meta data-inertia="description" name="description" content="{{ $seo['description'] }}">
        <link data-inertia="canonical" rel="canonical" href="{{ $seo['canonical'] }}">
        <meta data-inertia="og:site_name" property="og:site_name" content="{{ $brand }}">
        <meta data-inertia="og:type" property="og:type" content="{{ $seo['type'] }}">
        <meta data-inertia="og:locale" property="og:locale" content="{{ $seo['locale'] }}">
        <meta data-inertia="og:title" property="og:title" content="{{ $title }}">
        <meta data-inertia="og:description" property="og:description" content="{{ $seo['description'] }}">
        <meta data-inertia="og:url" property="og:url" content="{{ $seo['canonical'] }}">
        <meta data-inertia="og:image" property="og:image" content="{{ $seo['image'] }}">
        <meta data-inertia="og:image:alt" property="og:image:alt" content="{{ $seo['image_alt'] ?? $brand }}">
        <meta data-inertia="twitter:card" name="twitter:card" content="summary_large_image">
        <meta data-inertia="twitter:title" name="twitter:title" content="{{ $title }}">
        <meta data-inertia="twitter:description" name="twitter:description" content="{{ $seo['description'] }}">
        <meta data-inertia="twitter:image" name="twitter:image" content="{{ $seo['image'] }}">
        <meta data-inertia="twitter:image:alt" name="twitter:image:alt" content="{{ $seo['image_alt'] ?? $brand }}">
        @if (! empty($seo['jsonLd']))
            <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => $seo['jsonLd']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        @endif
    @endif

    @foreach (\App\Support\Brand::iconLinks() as $icon)
        <link @foreach ($icon as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach>
    @endforeach
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script>
        (function () {
            try {
                var theme = localStorage.getItem('theme');

                if (theme === 'dark') {
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
