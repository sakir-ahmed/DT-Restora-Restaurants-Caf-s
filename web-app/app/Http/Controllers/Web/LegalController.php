<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SeoBuilder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Policy pages. Their text lives in lang/{locale}/legal.php as numbered
 * sections, so the page only needs to know how many sections to print.
 */
class LegalController extends Controller
{
    /** @var array<string, array{route: string, sections: int}> */
    private const PAGES = [
        'privacy' => ['route' => 'web.privacy', 'sections' => 9],
        'terms' => ['route' => 'web.terms', 'sections' => 10],
        'refund' => ['route' => 'web.refund', 'sections' => 6],
        'delivery' => ['route' => 'web.delivery', 'sections' => 6],
        'cookies' => ['route' => 'web.cookies', 'sections' => 5],
    ];

    public function __construct(private readonly SeoBuilder $seo) {}

    public function privacy(): Response
    {
        return $this->page('privacy');
    }

    public function terms(): Response
    {
        return $this->page('terms');
    }

    public function refund(): Response
    {
        return $this->page('refund');
    }

    public function delivery(): Response
    {
        return $this->page('delivery');
    }

    public function cookies(): Response
    {
        return $this->page('cookies');
    }

    private function page(string $key): Response
    {
        $config = self::PAGES[$key];
        $canonical = route($config['route']);
        $title = __('legal.'.$key.'.title');

        return Inertia::render('web/Legal', [
            'page' => $key,
            'sections' => $config['sections'],
            'seo' => $this->seo->page(
                title: $title,
                description: __('legal.'.$key.'.intro', ['app' => config('app.name')]),
                canonical: $canonical,
                breadcrumbs: [
                    ['name' => __('site.nav_home'), 'url' => url('/')],
                    ['name' => $title, 'url' => $canonical],
                ],
            ),
        ]);
    }
}
