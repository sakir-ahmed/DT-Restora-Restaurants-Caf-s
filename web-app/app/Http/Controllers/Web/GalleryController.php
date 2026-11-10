<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Media;
use App\Models\MenuItem;
use App\Services\SeoBuilder;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    private const LIMIT = 200;

    public function __construct(private readonly SeoBuilder $seo) {}

    public function __invoke(): Response
    {
        $locale = app()->getLocale();

        $branches = Branch::query()->where('is_active', true)->get()->keyBy('id');
        $items = MenuItem::query()->available()->whereIn('branch_id', $branches->keys())->get()->keyBy('id');

        $entries = Media::query()
            ->active()
            ->ordered()
            ->limit(self::LIMIT)
            ->get()
            ->map(function (Media $media) use ($branches, $items, $locale): ?array {
                $display = $media->toDisplay($locale);

                if ($media->mediable_type === 'branch') {
                    $branch = $branches->get($media->mediable_id);

                    return $branch ? $display + [
                        'group' => 'outlet',
                        'label' => $branch->displayName($locale),
                        'href' => route('web.locations.show', ['slug' => $branch->publicSlug()]),
                    ] : null;
                }

                if ($media->mediable_type === 'menu_item') {
                    $item = $items->get($media->mediable_id);
                    $branch = $item ? $branches->get($item->branch_id) : null;

                    return $item && $branch ? $display + [
                        'group' => 'dish',
                        'label' => $locale === 'bn' && $item->name_bn ? $item->name_bn : $item->name,
                        'href' => route('web.item', ['branchCode' => strtolower($branch->code), 'item' => $item->id]),
                    ] : null;
                }

                return $display + ['group' => 'general', 'label' => null, 'href' => null];
            })
            ->filter()
            ->sortBy(fn (array $entry): int => ['general' => 0, 'outlet' => 1, 'dish' => 2][$entry['group']])
            ->values();

        $images = $entries->where('kind', 'image');
        $videos = $entries->where('kind', 'video')->filter(fn (array $entry): bool => $entry['thumbnail'] !== null && $entry['title'] !== null);

        return Inertia::render('web/Gallery', [
            'entries' => $entries,
            'seo' => $this->seo->page(
                title: __('site.seo_gallery_title'),
                description: __('site.seo_gallery_description'),
                canonical: route('web.gallery'),
                jsonLd: [
                    [
                        '@type' => 'ImageGallery',
                        'name' => __('site.seo_gallery_title'),
                        'url' => route('web.gallery'),
                        'image' => $images->take(24)->map(fn (array $entry): array => array_filter([
                            '@type' => 'ImageObject',
                            'contentUrl' => $entry['absolute_src'],
                            'name' => $entry['title'] ?: $entry['label'],
                        ]))->values()->all(),
                    ],
                    ...$videos->take(12)->map(fn (array $entry): array => [
                        '@type' => 'VideoObject',
                        'name' => $entry['title'],
                        'thumbnailUrl' => $entry['thumbnail'],
                        'embedUrl' => $entry['embed_url'],
                        'uploadDate' => now()->toDateString(),
                    ])->all(),
                ],
                breadcrumbs: [
                    ['name' => __('site.nav_home'), 'url' => url('/')],
                    ['name' => __('site.nav_gallery'), 'url' => route('web.gallery')],
                ],
                image: $images->first()['absolute_src'] ?? null,
            ),
        ]);
    }
}
