<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Brand;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The two files the installed app needs at a fixed address: the service worker
 * and the page shown when a screen cannot be opened without internet.
 */
class PwaController extends Controller
{
    /**
     * Serve the built service worker from the site root, so its scope covers the
     * whole app, with headers that make browsers look for a new version every time.
     * A long-lived copy (as for the fingerprinted files in /build) would pin staff
     * to an old release.
     */
    public function serviceWorker(): BinaryFileResponse
    {
        $path = public_path('build/sw.js');

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    /**
     * Fallback screen for a page that was never opened on this device. It is stored
     * by the service worker when the app is installed, so it has no session, no
     * scripts and no external files, and shows both languages.
     */
    public function offline(): Response
    {
        $background = Brand::backgroundColor();
        $isDark = $this->isDark($background);

        return response()
            ->view('offline', [
                'brandName' => __('site.brand_name', [], 'en'),
                // The emblem is already stored by the service worker once the app has been opened.
                'logo' => Brand::shared()['emblem'],
                'themeColor' => Brand::themeColor(),
                'backgroundColor' => $background,
                'textColor' => $isDark ? '#f4efe6' : '#1b2430',
                'accentColor' => $isDark ? '#d6a84f' : Brand::themeColor(),
                'accentTextColor' => $isDark ? '#17120b' : '#ffffff',
                'languages' => ['en', 'bn'],
            ])
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Whether white-ish text is the readable choice on this #rrggbb background.
     */
    private function isDark(string $hex): bool
    {
        [$red, $green, $blue] = sscanf($hex, '#%02x%02x%02x') + [0, 0, 0];

        return (0.299 * $red + 0.587 * $green + 0.114 * $blue) < 140;
    }
}
