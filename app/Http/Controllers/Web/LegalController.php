<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SeoBuilder;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function __construct(private readonly SeoBuilder $seo) {}

    public function privacy(): Response
    {
        return Inertia::render('web/Privacy', ['seo' => $this->legalSeo('privacy', route('web.privacy'))]);
    }

    public function terms(): Response
    {
        return Inertia::render('web/Terms', ['seo' => $this->legalSeo('terms', route('web.terms'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function legalSeo(string $key, string $canonical): array
    {
        return $this->seo->page(
            title: __('site.seo_'.$key.'_title'),
            description: __('site.seo_'.$key.'_description'),
            canonical: $canonical,
            breadcrumbs: [
                ['name' => __('site.nav_home'), 'url' => url('/')],
                ['name' => __('site.seo_'.$key.'_title'), 'url' => $canonical],
            ],
        );
    }
}
