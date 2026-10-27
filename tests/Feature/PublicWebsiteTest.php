<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\DeliveryZone;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RestaurantTable;
use App\Services\SeoBuilder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class]);

    $this->branch = Branch::factory()->create([
        'name' => 'Gulshan Kitchen',
        'name_bn' => 'গুলশান কিচেন',
        'code' => 'BR01',
        'address' => 'House 4, Road 11',
        'city' => 'Dhaka',
        'phone' => '01711000001',
        'latitude' => 23.79,
        'longitude' => 90.41,
        'online_ordering_enabled' => true,
        'opening_hours' => [['days' => ['sat', 'sun'], 'opens' => '10:00', 'closes' => '23:00']],
    ]);

    $this->category = MenuCategory::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Rice']);
    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $this->category->id,
        'name' => 'Kacchi Biryani',
        'name_bn' => 'কাচ্চি বিরিয়ানি',
        'description' => 'Slow cooked mutton with aromatic rice',
        'base_price' => '450.00',
    ]);
    $this->sibling = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $this->category->id,
        'name' => 'Chicken Polao',
    ]);

    DeliveryZone::query()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Gulshan 1',
        'fee' => '60.00',
        'min_order' => '200.00',
        'eta_minutes' => 30,
    ]);
});

/**
 * Pull the first JSON-LD graph out of a rendered page.
 *
 * @return array<int, array<string, mixed>>
 */
function jsonLdGraph(string $html): array
{
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

    return json_decode($matches[1] ?? '{}', true)['@graph'] ?? [];
}

it('renders every public marketing page with a component and unique SEO title', function (string $url, string $component): void {
    $response = $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
        ->component($component)
        ->has('seo.title')
        ->has('seo.description')
        ->has('seo.canonical'));

    expect(substr_count($response->getContent(), '<title'))->toBe(1);
})->with([
    'home' => ['/', 'web/Home'],
    'about' => ['/about', 'web/About'],
    'contact' => ['/contact', 'web/Contact'],
    'locations' => ['/locations', 'web/locations/Index'],
    'location' => ['/locations/gulshan-kitchen', 'web/locations/Show'],
    'menu' => ['/menu/br01', 'web/Menu'],
    'privacy' => ['/privacy', 'web/Legal'],
    'terms' => ['/terms', 'web/Legal'],
]);

it('gives each page its own title and canonical URL', function (): void {
    $titles = collect(['/', '/about', '/contact', '/locations', '/privacy', '/terms'])
        ->map(fn (string $url) => $this->get($url)->viewData('page')['props']['seo']['title']);

    expect($titles->unique())->toHaveCount($titles->count());

    $this->get('/about')
        ->assertSee('<link data-inertia="canonical" rel="canonical" href="'.route('web.about').'">', false)
        ->assertSee('<meta data-inertia="og:image" property="og:image" content="'.asset('images/og-default.png').'">', false)
        ->assertSee('name="twitter:card"', false)
        ->assertSee('content="index,follow,max-image-preview:large"', false);
});

it('describes the home page to search engines with organization, restaurants and FAQ schema', function (): void {
    $types = collect(jsonLdGraph($this->get('/')->getContent()))->pluck('@type');

    expect($types)->toContain('WebSite', 'Organization', 'Restaurant', 'FAQPage');
});

it('publishes a branch page with address, geo, opening hours and breadcrumbs', function (): void {
    $graph = collect(jsonLdGraph($this->get('/locations/gulshan-kitchen')->getContent()));
    $restaurant = $graph->firstWhere('@type', 'Restaurant');

    expect($restaurant['address']['streetAddress'])->toBe('House 4, Road 11')
        ->and($restaurant['telephone'])->toBe('01711000001')
        ->and($restaurant['geo']['latitude'])->toBe(23.79)
        ->and($restaurant['openingHoursSpecification'][0]['dayOfWeek'])->toBe(['Saturday', 'Sunday'])
        ->and($restaurant['openingHoursSpecification'][0]['opens'])->toBe('10:00')
        ->and($graph->firstWhere('@type', 'BreadcrumbList')['itemListElement'])->toHaveCount(3);
});

it('publishes the menu as structured data with prices', function (): void {
    $graph = collect(jsonLdGraph($this->get('/menu/br01')->getContent()));
    $menu = $graph->firstWhere('@type', 'Menu');
    $items = collect($menu['hasMenuSection'])->flatMap(fn (array $section) => $section['hasMenuItem']);

    expect($items->pluck('name'))->toContain('Kacchi Biryani')
        ->and($items->firstWhere('name', 'Kacchi Biryani')['offers']['price'])->toBe('450.00')
        ->and($items->firstWhere('name', 'Kacchi Biryani')['offers']['priceCurrency'])->toBe('BDT');
});

it('renders a dish page with schema, related dishes and the dish description', function (): void {
    $response = $this->get('/menu/br01/items/'.$this->item->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('web/Item')
            ->where('item.name', 'Kacchi Biryani')
            ->has('related', 1)
            ->where('related.0.name', 'Chicken Polao')
            ->where('seo.description', 'Slow cooked mutton with aromatic rice'));

    $dish = collect(jsonLdGraph($response->getContent()))->firstWhere('@type', 'MenuItem');

    expect($dish['offers']['availability'])->toBe('https://schema.org/InStock');
});

it('keeps private and transactional pages out of search results', function (): void {
    $this->get('/checkout')->assertOk()->assertSee('content="noindex,nofollow"', false);

    $table = RestaurantTable::query()->create(['branch_id' => $this->branch->id, 'name' => 'T5']);

    $this->get(route('web.table', ['token' => $table->qr_token]))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('content="noindex,nofollow"', false);
    $this->get('/menu/br01')->assertOk()->assertSee('content="index,follow,max-image-preview:large"', false);
});

it('lists public pages, branches and available dishes in the sitemap', function (): void {
    $hidden = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $this->category->id,
        'is_available' => false,
    ]);
    Branch::factory()->inactive()->create(['name' => 'Closed Cafe', 'code' => 'BR09']);

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)->toContain('<loc>'.route('web.about').'</loc>')
        ->toContain('<loc>'.route('web.locations.show', ['slug' => 'gulshan-kitchen']).'</loc>')
        ->toContain('<loc>'.route('web.menu', ['branchCode' => 'br01']).'</loc>')
        ->toContain('/menu/br01/items/'.$this->item->id.'</loc><lastmod>')
        ->not->toContain('/items/'.$hidden->id.'<')
        ->not->toContain('closed-cafe')
        ->not->toContain('/checkout');
});

it('blocks crawlers outside production and exposes the sitemap in production', function (): void {
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');

    app()['env'] = 'production';

    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Disallow: /admin')
        ->assertSee('Disallow: /checkout')
        ->assertSee('Sitemap: '.route('web.sitemap'));
});

it('resolves a branch page by slug or legacy code and 404s otherwise', function (): void {
    $this->get('/locations/gulshan-kitchen')->assertOk();
    $this->get('/locations/br01')->assertOk();
    $this->get('/locations/unknown-branch')->assertNotFound();

    Branch::factory()->inactive()->create(['name' => 'Closed Cafe', 'code' => 'BR09']);
    $this->get('/locations/closed-cafe')->assertNotFound();
});

it('serves the website in Bangla with Bangla SEO and locale tags', function (): void {
    $this->withSession(['locale' => 'bn'])->get('/about')
        ->assertOk()
        ->assertSee('আমাদের সম্পর্কে', false)
        ->assertSee('property="og:locale" content="bn_BD"', false);

    $this->withSession(['locale' => 'bn'])->get('/menu/br01/items/'.$this->item->id)
        ->assertInertia(fn ($page) => $page->where('seo.title', 'গুলশান কিচেন-এ কাচ্চি বিরিয়ানি'));
});

it('shares only the translation groups the website needs', function (): void {
    $translations = $this->get('/')->viewData('page')['props']['translations'];

    expect($translations)->toHaveKey('site.hero_title')
        ->toHaveKey('web.checkout_title')
        ->not->toHaveKey('accounting.title');
});

it('normalises stored opening hours', function (): void {
    $seo = app(SeoBuilder::class);

    $branch = new Branch(['opening_hours' => [
        ['days' => 'daily', 'opens' => '09:00', 'closes' => '22:00'],
        ['days' => ['Monday', 'fri'], 'opens' => '12:00', 'closes' => '23:30'],
        ['days' => ['sat'], 'opens' => '10:00'],
        'invalid',
    ]]);

    expect($seo->hours($branch))->toHaveCount(2)
        ->and($seo->hours($branch)[0]['days'])->toBe(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])
        ->and($seo->hours($branch)[1]['days'])->toBe(['mon', 'fri'])
        ->and($seo->hours(new Branch))->toBe([]);
});
