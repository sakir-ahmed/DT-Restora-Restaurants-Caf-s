<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->where('online_ordering_enabled', true)
            ->with(['zones' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('code')
            ->get();

        $featured = MenuItem::query()
            ->available()
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->with(['category:id,name', 'variants'])
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return Inertia::render('web/Home', [
            'branches' => $branches,
            'featured' => $featured,
        ])->withViewData(['seo' => $this->seo()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function seo(): array
    {
        $name = (string) config('app.name');

        return [
            'title' => $name.' - '.__('web.seo_home_title'),
            'description' => __('web.seo_home_description'),
            'canonical' => url('/'),
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'Restaurant',
                'name' => $name,
                'servesCuisine' => 'Bangladeshi',
                'priceRange' => 'Tk',
                'url' => url('/'),
            ],
        ];
    }
}
