<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Branch;
use App\Models\DeliveryZone;
use App\Models\Media;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Services\PublicCache;
use App\Services\SeoBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(private readonly SeoBuilder $seo) {}

    public function __invoke(Request $request): Response
    {
        $locale = app()->getLocale();

        return Inertia::render('web/Home', PublicCache::remember('home:'.$locale, fn (): array => $this->props()));
    }

    /**
     * @return array<string, mixed>
     */
    private function props(): array
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->where('online_ordering_enabled', true)
            ->with(['zones' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('code')
            ->get()
            ->each(fn (Branch $branch) => $branch->setAttribute('slug', $branch->publicSlug()));

        $featuredNames = [
            'Al Faham Wajba',
            'Kabsa',
            'Cheesy Jumbo Burger',
            'Shahi Chotpoti (AK Special)',
            'Doi Fuchka (AK Special)',
            'BBQ Chicken Wings (6pcs)',
            'Dajaj Mandi',
            'Green Lemonade',
        ];
        $featuredOrder = array_flip($featuredNames);
        $featured = MenuItem::query()
            ->where('branch_id', $branches->first()?->id)
            ->whereNotNull('photo_path')
            ->available()
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->with(['category:id,name,name_bn', 'variants', 'media'])
            ->get()
            ->sortBy(fn (MenuItem $item): int => $featuredOrder[$item->name] ?? count($featuredNames) + $item->sort_order)
            ->take(count($featuredNames))
            ->values();

        $categories = MenuCategory::query()
            ->active()
            ->whereIn('branch_id', $branches->pluck('id'))
            ->orderBy('name')
            ->get(['name', 'name_bn'])
            ->unique('name')
            ->take(12)
            ->map(fn (MenuCategory $category): array => ['name' => $category->name, 'name_bn' => $category->name_bn])
            ->values();

        return [
            'branches' => $branches->toArray(),
            'featured' => $featured->toArray(),
            'categories' => $categories->all(),
            'gallery' => $this->galleryPreview(),
            'posts' => BlogPost::query()->published()->orderByDesc('published_at')->limit(3)->get()->map(fn (BlogPost $post): array => [
                'id' => $post->id,
                'slug' => $post->slug,
                'title' => $post->text('title', app()->getLocale()),
                'excerpt' => $post->text('excerpt', app()->getLocale()),
                'category' => $post->category,
                'cover' => $post->coverSrc(),
                'cover_alt' => $post->cover_alt ?: $post->text('title', app()->getLocale()),
                'published_at' => $post->published_at?->toIso8601String(),
                'reading_minutes' => $post->readingMinutes(app()->getLocale()),
            ])->values()->all(),
            'stats' => [
                'years' => ($year = Setting::current()->established_year) ? max(1, now()->year - $year) : null,
                'categories' => $categories->count(),
                'branches' => $branches->count(),
                'dishes' => MenuItem::query()->available()->whereIn('branch_id', $branches->pluck('id'))->count(),
                'areas' => DeliveryZone::query()->where('is_active', true)->whereIn('branch_id', $branches->pluck('id'))->count(),
            ],
            'seo' => $this->seoData($branches),
        ];
    }

    /**
     * @param  Collection<int, Branch>  $branches
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

    /**
     * Six photos for the home page strip. Outlet photos are spread evenly
     * through the gallery so rooms, entrance and food all show up; without
     * outlet photos the most recent images are used.
     *
     * @return list<array<string, mixed>>
     */
    private function galleryPreview(): array
    {
        $images = Media::query()
            ->active()
            ->where('kind', Media::KIND_IMAGE)
            ->whereNotNull('path');

        $outlet = (clone $images)
            ->where('mediable_type', (new Branch)->getMorphClass())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        $photos = $outlet->count() >= 6
            ? $outlet->filter(fn (Media $media, int $position): bool => $position % intdiv($outlet->count(), 6) === 0)->take(6)
            : $images->orderByDesc('id')->limit(6)->get();

        return $photos
            ->map(fn (Media $media): array => $media->toDisplay(app()->getLocale()))
            ->values()
            ->all();
    }
}
