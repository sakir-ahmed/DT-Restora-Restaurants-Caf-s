<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Services\SeoBuilder;
use Inertia\Inertia;
use Inertia\Response;

class AboutController extends Controller
{
    public function __construct(private readonly SeoBuilder $seo) {}

    public function __invoke(): Response
    {
        $branchIds = Branch::query()->where('is_active', true)->pluck('id');

        return Inertia::render('web/About', [
            'stats' => [
                'branches' => $branchIds->count(),
                'dishes' => MenuItem::query()->available()->whereIn('branch_id', $branchIds)->count(),
            ],
            'seo' => $this->seo->page(
                title: __('site.seo_about_title'),
                description: __('site.seo_about_description'),
                canonical: route('web.about'),
                jsonLd: [[
                    '@type' => 'AboutPage',
                    'name' => __('site.seo_about_title'),
                    'url' => route('web.about'),
                    'about' => ['@id' => url('/').'#organization'],
                ]],
                breadcrumbs: [
                    ['name' => __('site.nav_home'), 'url' => url('/')],
                    ['name' => __('site.nav_about'), 'url' => route('web.about')],
                ],
            ),
        ]);
    }
}
