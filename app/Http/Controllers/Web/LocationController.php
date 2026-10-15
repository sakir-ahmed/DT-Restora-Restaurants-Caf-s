<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Services\SeoBuilder;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function __construct(private readonly SeoBuilder $seo) {}

    public function index(): Response
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->with(['zones' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('code')
            ->get();

        return Inertia::render('web/locations/Index', [
            'branches' => $branches->map(fn (Branch $branch): array => $this->summary($branch))->values(),
            'seo' => $this->seo->page(
                title: __('site.seo_locations_title'),
                description: __('site.seo_locations_description'),
                canonical: route('web.locations.index'),
                jsonLd: $branches->take(10)->map(fn (Branch $branch): array => $this->seo->restaurant($branch))->all(),
                breadcrumbs: [
                    ['name' => __('site.nav_home'), 'url' => url('/')],
                    ['name' => __('site.nav_locations'), 'url' => route('web.locations.index')],
                ],
            ),
        ]);
    }

    public function show(string $slug): Response
    {
        $branch = Branch::findByPublicSlug($slug);

        abort_if($branch === null, 404);

        $branch->load(['zones' => fn ($query) => $query->where('is_active', true)->orderBy('fee')]);

        $popular = MenuItem::query()
            ->available()
            ->where('branch_id', $branch->id)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->with(['category:id,name,name_bn', 'variants'])
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        $canonical = route('web.locations.show', ['slug' => $branch->publicSlug()]);

        return Inertia::render('web/locations/Show', [
            'branch' => $this->summary($branch) + ['latitude' => $branch->latitude, 'longitude' => $branch->longitude],
            'popular' => $popular,
            'seo' => $this->seo->page(
                title: __('site.seo_location_title', ['branch' => $branch->displayName(app()->getLocale())]),
                description: __('site.seo_location_description', [
                    'branch' => $branch->displayName(app()->getLocale()),
                    'address' => trim($branch->address.', '.$branch->city, ', '),
                ]),
                canonical: $canonical,
                jsonLd: [$this->seo->restaurant($branch)],
                breadcrumbs: [
                    ['name' => __('site.nav_home'), 'url' => url('/')],
                    ['name' => __('site.nav_locations'), 'url' => route('web.locations.index')],
                    ['name' => $branch->displayName(app()->getLocale()), 'url' => $canonical],
                ],
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'name_bn' => $branch->name_bn,
            'code' => $branch->code,
            'slug' => $branch->publicSlug(),
            'address' => $branch->address,
            'city' => $branch->city,
            'phone' => $branch->phone,
            'online_ordering_enabled' => $branch->online_ordering_enabled,
            'hours' => $this->seo->hours($branch),
            'zones' => $branch->zones->map(fn ($zone): array => [
                'id' => $zone->id,
                'name' => $zone->name,
                'fee' => (string) $zone->fee,
                'min_order' => (string) $zone->min_order,
                'eta_minutes' => $zone->eta_minutes,
            ])->values(),
        ];
    }
}
