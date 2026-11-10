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
            ->with(['category:id,name,name_bn', 'variants', 'media'])
            ->orderBy('sort_order')
            ->limit(4)
            ->get();

        $locale = app()->getLocale();
        $name = $locale === 'bn' && $item->name_bn ? $item->name_bn : $item->name;
        $description = $locale === 'bn' && $item->description_bn ? $item->description_bn : $item->description;
        $code = strtolower($branch->code);
        $canonical = route('web.item', ['branchCode' => $code, 'item' => $item->id]);
        $gallery = $this->gallery($item, $name, $locale);
        $image = collect($gallery)->firstWhere('kind', 'image')['absolute_src'] ?? null;

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
            'gallery' => $gallery,
            'related' => $related,
            'seo' => $this->seo->page(
                title: __('site.seo_item_title', ['item' => $name, 'branch' => $branch->displayName($locale)]),
                description: $description ?: __('site.seo_item_description', ['item' => $name, 'branch' => $branch->displayName($locale)]),
                canonical: $canonical,
                jsonLd: [array_filter([
                    '@type' => 'MenuItem',
                    'name' => $item->name,
                    'description' => $item->description,
                    'image' => collect($gallery)->where('kind', 'image')->pluck('absolute_src')->values()->all() ?: null,
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

    /**
     * Photos and videos for the dish page, starting with the legacy photo
     * path when the dish has one.
     *
     * @return list<array<string, mixed>>
     */
    private function gallery(MenuItem $item, string $name, string $locale): array
    {
        $gallery = [];

        if ($item->photo_path && $item->media->doesntContain(fn ($media): bool => $media->path === $item->photo_path)) {
            $gallery[] = [
                'id' => 0, 'kind' => 'image', 'src' => $this->photoPath($item->photo_path), 'absolute_src' => $this->absolute($this->photoPath($item->photo_path)), 'title' => $name,
                'provider' => null, 'embed_url' => null, 'thumbnail' => null, 'video_url' => null,
            ];
        }

        foreach ($item->media->where('is_active', true) as $media) {
            $gallery[] = $media->toDisplay($locale);
        }

        return $gallery;
    }

    private function photoPath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : '/storage/'.ltrim($path, '/');
    }

    private function absolute(?string $path): ?string
    {
        return $path === null ? null : (str_starts_with($path, 'http') ? $path : url($path));
    }
}
