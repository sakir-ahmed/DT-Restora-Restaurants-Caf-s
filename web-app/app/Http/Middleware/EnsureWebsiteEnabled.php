<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the public website when the owner switches it off in Settings.
 *
 * Signed-in staff still see the site (to preview it). Order tracking, payment
 * return pages, table QR ordering, the brand files and the back office stay
 * reachable, so orders already placed and in-restaurant service are not cut off.
 */
class EnsureWebsiteEnabled
{
    /**
     * Route names that stay open while the website is off.
     *
     * @var list<string>
     */
    private const ALWAYS_OPEN = [
        'web.track', 'web.payment.*', 'web.table', 'web.table.*', 'web.qr',
        'web.robots', 'web.favicon', 'web.manifest',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isClosed($request)) {
            return $next($request);
        }

        abort(503, __('errors.503_message'), ['Retry-After' => '3600']);
    }

    private function isClosed(Request $request): bool
    {
        $route = $request->route()?->getName();

        if ($route === null || ! str_starts_with($route, 'web.') || $request->routeIs(...self::ALWAYS_OPEN)) {
            return false;
        }

        if (auth('staff')->check()) {
            return false;
        }

        return Setting::current()->website_enabled === false;
    }
}
