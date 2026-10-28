<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\Setting;
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
            'photo' => $this->photo(),
            'stats' => [
                'years' => ($year = Setting::current()->established_year) ? max(1, now()->year - $year) : null,
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

    /**
     * The outlet photo shown beside the story: the dining lounge when it is in
     * the gallery, otherwise the first outlet photo.
     *
     * @return array<string, mixed>|null
     */
    private function photo(): ?array
    {
        $photos = Media::query()
            ->active()
            ->where('kind', Media::KIND_IMAGE)
            ->whereNotNull('path')
            ->where('mediable_type', (new Branch)->getMorphClass())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $photo = $photos->first(fn (Media $media): bool => str_contains((string) $media->title, 'Dining lounge')) ?? $photos->first();

        return $photo?->toDisplay(app()->getLocale());
    }
}
