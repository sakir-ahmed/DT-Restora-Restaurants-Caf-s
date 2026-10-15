<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\SeoBuilder;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function __construct(private readonly SeoBuilder $seo) {}

    public function __invoke(): Response
    {
        $branches = Branch::query()->where('is_active', true)->orderBy('code')->get();

        return Inertia::render('web/Contact', [
            'branches' => $branches->map(fn (Branch $branch): array => [
                'id' => $branch->id,
                'name' => $branch->name,
                'name_bn' => $branch->name_bn,
                'code' => $branch->code,
                'slug' => $branch->publicSlug(),
                'address' => $branch->address,
                'city' => $branch->city,
                'phone' => $branch->phone,
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
                'hours' => $this->seo->hours($branch),
            ])->values(),
            'seo' => $this->seo->page(
                title: __('site.seo_contact_title'),
                description: __('site.seo_contact_description'),
                canonical: route('web.contact'),
                jsonLd: [
                    [
                        '@type' => 'ContactPage',
                        'name' => __('site.seo_contact_title'),
                        'url' => route('web.contact'),
                    ],
                    ...$branches->take(10)->map(fn (Branch $branch): array => $this->seo->restaurant($branch))->all(),
                ],
                breadcrumbs: [
                    ['name' => __('site.nav_home'), 'url' => url('/')],
                    ['name' => __('site.nav_contact'), 'url' => route('web.contact')],
                ],
            ),
        ]);
    }
}
