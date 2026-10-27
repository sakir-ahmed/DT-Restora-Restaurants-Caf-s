<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Services\PublicCache;
use App\Services\SeoBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function __construct(private readonly SeoBuilder $seo) {}

    public function __invoke(Request $request, ?string $branchCode = null): Response
    {
        $locale = app()->getLocale();

        /** @var array{branch: array<string, mixed>, categories: list<array<string, mixed>>, seo: array<string, mixed>} $page */
        $page = PublicCache::remember('menu:'.$locale.':'.($branchCode ?? 'default'), fn (): array => $this->pageData($branchCode));

        session(['web_branch_id' => $page['branch']['id']]);

        return Inertia::render('web/Menu', $page);
    }

    /**
     * The part of the menu page that is the same for every visitor.
     *
     * @return array{branch: array<string, mixed>, categories: list<array<string, mixed>>, seo: array<string, mixed>}
     */
    private function pageData(?string $branchCode): array
    {
        $branch = Branch::query()
            ->where('is_active', true)
            ->when($branchCode !== null, fn ($query) => $query->where('code', strtoupper($branchCode)))
            ->when($branchCode === null, fn ($query) => $query->orderBy('id'))
            ->with(['zones' => fn ($query) => $query->where('is_active', true)])
            ->firstOrFail();

        $categories = MenuCategory::query()
            ->where('branch_id', $branch->id)
            ->active()
            ->ordered()
            ->with(['items' => fn ($query) => $query->available()->with(['variants', 'addOns', 'media'])->orderBy('sort_order')])
            ->get();

        return [
            'branch' => $branch->only(['id', 'name', 'name_bn', 'code', 'address', 'phone', 'opening_hours']) + ['slug' => $branch->publicSlug()],
            'categories' => $categories->toArray(),
            'seo' => $this->seoData($branch, $categories, false),
        ];
    }

    /**
     * @param  Collection<int, MenuCategory>  $categories
     * @return array<string, mixed>
     */
    private function seoData(Branch $branch, Collection $categories, bool $isTableOrder): array
    {
        $locale = app()->getLocale();
        $canonical = route('web.menu', ['branchCode' => strtolower($branch->code)]);

        $sections = $categories->map(fn (MenuCategory $category): array => [
            '@type' => 'MenuSection',
            'name' => $category->displayName($locale),
            'hasMenuItem' => $category->items->take(40)->map(fn ($item): array => array_filter([
                '@type' => 'MenuItem',
                'name' => $item->name,
                'description' => $item->description,
                'url' => route('web.item', ['branchCode' => strtolower($branch->code), 'item' => $item->id]),
                'suitableForDiet' => $item->is_vegetarian ? 'https://schema.org/VegetarianDiet' : null,
                'offers' => [
                    '@type' => 'Offer',
                    'price' => (string) $item->base_price,
                    'priceCurrency' => 'BDT',
                ],
            ]))->all(),
        ])->all();

        $seo = $this->seo->page(
            title: __('site.seo_menu_page_title', ['branch' => $branch->displayName($locale)]),
            description: __('site.seo_menu_page_description', ['branch' => $branch->displayName($locale)]),
            canonical: $canonical,
            jsonLd: [[
                '@type' => 'Menu',
                'name' => $branch->name.' Menu',
                'url' => $canonical,
                'inLanguage' => $locale === 'bn' ? 'bn-BD' : 'en-BD',
                'hasMenuSection' => $sections,
            ]],
            breadcrumbs: [
                ['name' => __('site.nav_home'), 'url' => url('/')],
                ['name' => __('site.nav_menu'), 'url' => route('web.menu')],
                ['name' => $branch->displayName($locale), 'url' => $canonical],
            ],
        );

        // Table QR links are per-table duplicates of the same menu.
        $seo['noindex'] = $isTableOrder;

        return $seo;
    }
}
