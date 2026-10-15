<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Services\SeoBuilder;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function __construct(private readonly SeoBuilder $seo) {}

    public function show(string $branchCode, MenuItem $item): Response
    {
        $branch = Branch::query()
            ->where('is_active', true)
            ->where('code', strtoupper($branchCode))
            ->firstOrFail();

        abort_unless($item->branch_id === $branch->id && $item->is_available, 404);

        $item->load(['variants', 'addOns', 'category:id,name,name_bn']);

        $related = MenuItem::query()
            ->available()
            ->where('branch_id', $branch->id)
            ->where('menu_category_id', $item->menu_category_id)
            ->whereKeyNot($item->id)
            ->with(['category:id,name,name_bn', 'variants'])
            ->orderBy('sort_order')
            ->limit(4)
            ->get();

        $locale = app()->getLocale();
        $name = $locale === 'bn' && $item->name_bn ? $item->name_bn : $item->name;
        $description = $locale === 'bn' && $item->description_bn ? $item->description_bn : $item->description;
        $code = strtolower($branch->code);
        $canonical = route('web.item', ['branchCode' => $code, 'item' => $item->id]);
        $image = $this->photoUrl($item->photo_path);

        return Inertia::render('web/Item', [
            'branch' => $branch->only(['id', 'name', 'name_bn', 'code']),
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'name_bn' => $item->name_bn,
                'description' => $item->description,
                'description_bn' => $item->description_bn,
                'base_price' => (string) $item->base_price,
                'is_vegetarian' => $item->is_vegetarian,
                'photo_path' => $item->photo_path,
                'category' => $item->category?->name,
                'category_bn' => $item->category?->name_bn,
                'variants' => $item->variants,
                'add_ons' => $item->addOns,
            ],
            'related' => $related,
            'seo' => $this->seo->page(
                title: __('site.seo_item_title', ['item' => $name, 'branch' => $branch->displayName($locale)]),
                description: $description ?: __('site.seo_item_description', ['item' => $name, 'branch' => $branch->displayName($locale)]),
                canonical: $canonical,
                jsonLd: [array_filter([
                    '@type' => 'MenuItem',
                    'name' => $item->name,
                    'description' => $item->description,
                    'image' => $image,
                    'suitableForDiet' => $item->is_vegetarian ? 'https://schema.org/VegetarianDiet' : null,
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => (string) $item->base_price,
                        'priceCurrency' => 'BDT',
                        'availability' => 'https://schema.org/InStock',
                        'url' => $canonical,
                    ],
                ])],
                breadcrumbs: [
                    ['name' => __('site.nav_home'), 'url' => url('/')],
                    ['name' => __('site.nav_menu'), 'url' => route('web.menu', ['branchCode' => $code])],
                    ['name' => $name, 'url' => $canonical],
                ],
                image: $image,
            ),
        ]);
    }

    private function photoUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : url('/storage/'.ltrim($path, '/'));
    }
}
