<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use Illuminate\Support\Str;

/**
 * Builds the `seo` prop shared by every public page: meta tags, canonical URL,
 * Open Graph data and schema.org JSON-LD. The root Blade view renders it for
 * crawlers and the SeoHead component keeps it current on client navigation.
 */
class SeoBuilder
{
    public const FAQ_COUNT = 6;

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
            'logo' => asset('images/og-default.png'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function restaurant(Branch $branch): array
    {
        $node = [
            '@type' => 'Restaurant',
            '@id' => $this->branchUrl($branch).'#restaurant',
            'name' => $branch->name,
            'url' => $this->branchUrl($branch),
            'servesCuisine' => 'Bangladeshi',
            'priceRange' => 'BDT',
            'hasMenu' => route('web.menu', ['branchCode' => strtolower($branch->code)]),
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $branch->address,
                'addressLocality' => $branch->city,
                'addressCountry' => 'BD',
            ]),
            'potentialAction' => [
                '@type' => 'OrderAction',
                'target' => route('web.menu', ['branchCode' => strtolower($branch->code)]),
                'deliveryMethod' => ['http://purl.org/goodrelations/v1#DeliveryModeOwnFleet', 'http://purl.org/goodrelations/v1#DeliveryModePickUp'],
            ],
        ];

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
    public function faqPage(): array
    {
        $questions = [];

        for ($number = 1; $number <= self::FAQ_COUNT; $number++) {
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

    private function branchUrl(Branch $branch): string
    {
        return route('web.locations.show', ['slug' => $branch->publicSlug()]);
    }
}
