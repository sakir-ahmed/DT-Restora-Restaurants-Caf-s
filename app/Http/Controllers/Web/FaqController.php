<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SeoBuilder;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    public function __construct(private readonly SeoBuilder $seo) {}

    public function __invoke(): Response
    {
        return Inertia::render('web/Faq', [
            'seo' => $this->seo->page(
                title: __('site.seo_faq_title'),
                description: __('site.seo_faq_description'),
                canonical: route('web.faq'),
                jsonLd: [$this->seo->faqPage(SeoBuilder::FAQ_COUNT)],
                breadcrumbs: [
                    ['name' => __('site.nav_home'), 'url' => url('/')],
                    ['name' => __('site.nav_faq'), 'url' => route('web.faq')],
                ],
            ),
        ]);
    }
}
