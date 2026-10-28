<?php

declare(strict_types=1);

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
