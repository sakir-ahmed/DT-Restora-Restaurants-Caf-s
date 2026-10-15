<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DeliveryZone;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\SeoBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(private readonly SeoBuilder $seo) {}

    public function __invoke(Request $request): Response
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->where('online_ordering_enabled', true)
            ->with(['zones' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('code')
            ->get()
            ->each(fn (Branch $branch) => $branch->setAttribute('slug', $branch->publicSlug()));

        $featured = MenuItem::query()
            ->available()
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->with(['category:id,name,name_bn', 'variants'])
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $categories = MenuCategory::query()
            ->active()
            ->whereIn('branch_id', $branches->pluck('id'))
            ->orderBy('name')
            ->get(['name', 'name_bn'])
            ->unique('name')
            ->take(12)
            ->map(fn (MenuCategory $category): array => ['name' => $category->name, 'name_bn' => $category->name_bn])
            ->values();

        return Inertia::render('web/Home', [
            'branches' => $branches,
            'featured' => $featured,
            'categories' => $categories,
            'stats' => [
                'branches' => $branches->count(),
                'dishes' => MenuItem::query()->available()->whereIn('branch_id', $branches->pluck('id'))->count(),
                'areas' => DeliveryZone::query()->where('is_active', true)->whereIn('branch_id', $branches->pluck('id'))->count(),
            ],
            'seo' => $this->seoData($branches),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Branch>  $branches
     * @return array<string, mixed>
     */
    private function seoData($branches): array
    {
        $graph = [
            $this->seo->website(),
            $this->seo->organization(),
            ...$branches->take(10)->map(fn (Branch $branch): array => $this->seo->restaurant($branch))->all(),
            $this->seo->faqPage(),
        ];

        return $this->seo->page(
            title: __('site.seo_home_title'),
            description: __('site.seo_home_description'),
            canonical: url('/'),
            jsonLd: $graph,
        );
    }
}
