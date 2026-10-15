<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    /**
     * Only production is crawlable; staging and local installs stay out of search results.
     */
    public function __invoke(): Response
    {
        if (! app()->isProduction()) {
            return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain']);
        }

        $private = ['/admin', '/rider', '/api', '/checkout', '/account', '/customer', '/track', '/pay', '/login', '/webhooks', '/payments', '/q/'];

        $lines = ['User-agent: *', 'Allow: /', ...array_map(fn (string $path): string => 'Disallow: '.$path, $private), '', 'Sitemap: '.route('web.sitemap')];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
    }
}
