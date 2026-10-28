<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Brand;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Brand files that browsers and crawlers request at a fixed address.
 */
class BrandController extends Controller
{
    public function favicon(): BinaryFileResponse
    {
        return response()->file(Brand::path('favicon.ico'), ['Content-Type' => 'image/x-icon', 'Cache-Control' => 'public, max-age=86400']);
    }

    public function manifest(): JsonResponse
    {
        return response()->json(Brand::manifest(), 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
