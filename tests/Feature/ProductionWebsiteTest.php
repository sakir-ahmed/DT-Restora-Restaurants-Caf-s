<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\ArmansKitchenDataSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class, ArmansKitchenDataSeeder::class]);
});

/**
 * @return array<int, array<string, mixed>>
 */
function armansJsonLd(string $html): array
{
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

    return json_decode($matches[1] ?? '{}', true)['@graph'] ?? [];
}

it('features real dishes with photos on the home page', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('web/Home')
            ->has('featured', 8)
            ->where('featured.0.name', 'Al Faham Wajba')
            ->where('featured.1.name', 'Kabsa')
            ->where('stats.years', now()->year - 2013)
            ->where('stats.categories', 9)
            ->has('gallery', 6));
});

it('shows an outlet photo and years of service on the about page', function (): void {
    $this->get('/about')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('web/About')
            ->where('photo.title_en', 'Dining lounge')
            ->where('stats.years', now()->year - 2013));
});

it('does not repeat the brand in page titles', function (): void {
    $home = $this->get('/')->getContent();
    $contact = $this->get('/contact')->getContent();

    expect($home)->toContain('<title>'.e("Arman's Kitchen, Mohammadpur: Burgers, Street Food and Mandi").'</title>')
        ->and($contact)->toContain('<title>'.e("Contact and Opening Hours - Arman's Kitchen").'</title>');

    $this->withSession(['locale' => 'bn'])->get('/')->assertSee('আরমানস কিচেন, মোহাম্মদপুর', false);
});

it('describes the restaurant with its logo, founding year, cuisines and photos', function (): void {
    $graph = collect(armansJsonLd($this->get('/')->getContent()));
    $organization = $graph->firstWhere('@type', 'Organization');
    $restaurant = $graph->firstWhere('@type', 'Restaurant');

    expect($organization['logo'])->toEndWith('/images/brand/emblem-512.png')
        ->and($organization['foundingDate'])->toBe('2013')
        ->and($restaurant['servesCuisine'])->toContain('Arabian', 'Street Food')
        ->and($restaurant['image'])->toHaveCount(4)
        ->and($restaurant['telephone'])->toBe('01777656561');
});

it('answers the restaurant questions in the FAQ schema', function (): void {
    $faq = collect(armansJsonLd($this->get('/')->getContent()))->firstWhere('@type', 'FAQPage');
    $questions = collect($faq['mainEntity'])->pluck('name');

    expect($questions)->toContain('What are your opening hours?', 'What is Qasr Al Mandi and how do I pre-order?');
});

it('keeps the English and Bangla site copy in step', function (): void {
    $english = require lang_path('en/site.php');
    $bangla = require lang_path('bn/site.php');

    expect(array_keys($bangla))->toBe(array_keys($english))
        ->and(array_keys($bangla['faq']))->toBe(array_keys($english['faq']));
});

it('shows the Bangla address of the outlet in Bangla mode', function (): void {
    $this->withSession(['locale' => 'bn'])->get('/locations')
        ->assertInertia(fn ($page) => $page
            ->component('web/locations/Index')
            ->where('branches.0.address_bn', 'বাসা ১৩৩, রোড ০৬, মোহাম্মদিয়া হাউজিং সোসাইটি, মোহাম্মদপুর')
            ->where('branches.0.city_bn', 'ঢাকা'));
});

it('lets the owner save social links, shares them with the footer and rejects bad links', function (): void {
    $owner = User::factory()->role('owner')->create();
    $settings = [
        'discount_limit_percent' => '10',
        'wastage_spike_multiplier' => '1.5',
        'pos_pin_minutes' => 5,
        'delivery_otp_enabled' => true,
    ];

    $this->get('/')->assertInertia(fn ($page) => $page->where('site.social.social_facebook', null));

    $this->actingAs($owner, 'staff')
        ->put(route('admin.settings.update'), [...$settings, 'social_facebook' => 'https://facebook.com/armanskitchen', 'social_whatsapp' => 'javascript:alert(1)'])
        ->assertSessionHasErrors('social_whatsapp');

    $this->actingAs($owner, 'staff')
        ->put(route('admin.settings.update'), [...$settings, 'social_facebook' => 'https://facebook.com/armanskitchen'])
        ->assertSessionHasNoErrors();

    auth('staff')->logout();

    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('site.social.social_facebook', 'https://facebook.com/armanskitchen')
        ->where('site.social.social_instagram', null));
});

it('saves the Bangla address from the branch form', function (): void {
    $owner = User::factory()->role('owner')->create();
    $branch = Branch::query()->where('code', 'AK01')->firstOrFail();

    $this->actingAs($owner, 'staff')
        ->put(route('admin.branches.update', $branch), [
            'name' => $branch->name,
            'code' => $branch->code,
            'vat_percent' => '0',
            'service_charge_percent' => '0',
            'address_bn' => 'নতুন ঠিকানা',
            'city_bn' => 'ঢাকা',
        ])
        ->assertSessionHasNoErrors();

    expect($branch->fresh()->address_bn)->toBe('নতুন ঠিকানা');
});
