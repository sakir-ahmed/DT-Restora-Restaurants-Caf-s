<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\Media;
use Illuminate\Support\Str;

/**
 * Builds the `seo` prop shared by every public page: meta tags, canonical URL,
 * Open Graph data and schema.org JSON-LD. The root Blade view renders it for
 * crawlers and the SeoHead component keeps it current on client navigation.
 */
class SeoBuilder
{
    /** Questions on the home page, and in total on the FAQ page. */
    public const FAQ_HOME_COUNT = 6;

    public const FAQ_COUNT = 10;

    private const DAYS = [
        'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday',
        'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday',
    ];

    /**
     * @param  list<array<string, mixed>>  $jsonLd
     * @param  list<array{name: string, url: string}>  $breadcrumbs
     * @return array<string, mixed>
     */
    public function page(
        string $title,
        string $description,
        string $canonical,
        array $jsonLd = [],
        array $breadcrumbs = [],
        ?string $image = null,
    ): array {
        if (count($breadcrumbs) > 1) {
            $jsonLd[] = $this->breadcrumbList($breadcrumbs);
        }

        return [
            'title' => $title,
            'description' => Str::limit(trim(strip_tags($description)), 160, ''),
            'canonical' => $canonical,
            'image' => $image ?? asset('images/og-default.png'),
            'image_alt' => (string) config('app.name'),
            'type' => 'website',
            'locale' => app()->getLocale() === 'bn' ? 'bn_BD' : 'en_US',
            'noindex' => false,
            'breadcrumbs' => $breadcrumbs,
            'jsonLd' => $jsonLd,
        ];
    }

    /**
     * @param  list<array{name: string, url: string}>  $breadcrumbs
     * @return array<string, mixed>
     */
    public function breadcrumbList(array $breadcrumbs): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($breadcrumbs)->values()->map(fn (array $crumb, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'url' => url('/'),
            'name' => (string) config('app.name'),
            'inLanguage' => app()->getLocale() === 'bn' ? 'bn-BD' : 'en-BD',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function organization(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => (string) config('app.name'),
            'url' => url('/'),
            'logo' => asset('images/brand/emblem-512.png'),
            'image' => asset('images/og-default.png'),
            'description' => __('site.seo_home_description'),
            'foundingDate' => (string) config('app.established_year'),
        ];
    }

    /**
     * @param  list<string>  $images
     * @return array<string, mixed>
     */
    public function restaurant(Branch $branch, array $images = []): array
    {
        $node = [
            '@type' => 'Restaurant',
            '@id' => $this->branchUrl($branch).'#restaurant',
            'name' => $branch->name,
            'url' => $this->branchUrl($branch),
            'servesCuisine' => config('app.cuisines'),
            'priceRange' => 'BDT 70 - BDT 4000',
            'parentOrganization' => ['@id' => url('/').'#organization'],
            'hasMenu' => route('web.menu', ['branchCode' => strtolower($branch->code)]),
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => app()->getLocale() === 'bn' && $branch->address_bn ? $branch->address_bn : $branch->address,
                'addressLocality' => $branch->city,
                'addressCountry' => 'BD',
            ]),
            'potentialAction' => [
                '@type' => 'OrderAction',
                'target' => route('web.menu', ['branchCode' => strtolower($branch->code)]),
                'deliveryMethod' => ['http://purl.org/goodrelations/v1#DeliveryModeOwnFleet', 'http://purl.org/goodrelations/v1#DeliveryModePickUp'],
            ],
        ];

        $images = $images !== [] ? $images : $this->outletImages($branch);

        if ($images !== []) {
            $node['image'] = $images;
        }

        if ($branch->phone) {
            $node['telephone'] = $branch->phone;
        }

        if ($branch->latitude !== null && $branch->longitude !== null) {
            $node['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $branch->latitude,
                'longitude' => (float) $branch->longitude,
            ];
        }

        $specification = $this->openingHoursSpecification($branch);

        if ($specification !== []) {
            $node['openingHoursSpecification'] = $specification;
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    public function faqPage(int $count = self::FAQ_HOME_COUNT): array
    {
        $questions = [];

        for ($number = 1; $number <= $count; $number++) {
            $questions[] = [
                '@type' => 'Question',
                'name' => __('site.faq.q'.$number),
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => __('site.faq.a'.$number)],
            ];
        }

        return ['@type' => 'FAQPage', 'mainEntity' => $questions];
    }

    /**
     * Normalised opening hours for display. Each stored entry looks like
     * {"days": ["mon", "tue"] or "daily", "opens": "10:00", "closes": "23:00"}.
     *
     * @return list<array{days: list<string>, opens: string, closes: string}>
     */
    public function hours(Branch $branch): array
    {
        $entries = is_array($branch->opening_hours) ? $branch->opening_hours : [];
        $hours = [];

        foreach ($entries as $entry) {
            if (! is_array($entry) || empty($entry['opens']) || empty($entry['closes'])) {
                continue;
            }

            $days = $entry['days'] ?? 'daily';
            $days = is_array($days) ? $days : [$days];
            $keys = collect($days)
                ->flatMap(fn ($day): array => strtolower((string) $day) === 'daily' ? array_keys(self::DAYS) : [substr(strtolower((string) $day), 0, 3)])
                ->filter(fn (string $key): bool => isset(self::DAYS[$key]))
                ->unique()
                ->values()
                ->all();

            if ($keys !== []) {
                $hours[] = ['days' => $keys, 'opens' => (string) $entry['opens'], 'closes' => (string) $entry['closes']];
            }
        }

        return $hours;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function openingHoursSpecification(Branch $branch): array
    {
        return collect($this->hours($branch))->map(fn (array $entry): array => [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => array_map(fn (string $key): string => self::DAYS[$key], $entry['days']),
            'opens' => $entry['opens'],
            'closes' => $entry['closes'],
        ])->all();
    }

    /**
     * Up to four outlet photos for the Restaurant node, used when the caller has none.
     *
     * @return list<string>
     */
    private function outletImages(Branch $branch): array
    {
        return $branch->media()
            ->where('is_active', true)
            ->where('kind', Media::KIND_IMAGE)
            ->whereNotNull('path')
            ->limit(4)
            ->get()
            ->map(fn (Media $media): ?string => $media->absoluteSrc())
            ->filter()
            ->values()
            ->all();
    }

    private function branchUrl(Branch $branch): string
    {
        return route('web.locations.show', ['slug' => $branch->publicSlug()]);
    }
}
