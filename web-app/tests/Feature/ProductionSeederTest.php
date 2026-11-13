<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Setting;
use App\Support\Brand;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Cache;

const SEED_QUESTION = 'Which company is this install for?';

/**
 * @return array<string, string>
 */
function seedOptions(bool $withCustomer): array
{
    return array_filter([
        'demo' => 'DT Restora (Demo)',
        'sample-cafe' => $withCustomer ? 'Sample Cafe' : null,
        'skip' => 'Skip, set everything up later in the admin panel',
    ]);
}

function useFixtureCustomer(): void
{
    config(['app.is_demo' => false, 'brand.production_path' => base_path('tests/Fixtures/production')]);
}

it('lists DT Restora (Demo) first, then each company, then skip', function (): void {
    useFixtureCustomer();

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class])
        ->expectsChoice(SEED_QUESTION, 'skip', seedOptions(true))
        ->assertSuccessful();

    expect(Branch::query()->count())->toBe(0);
});

it('seeds the demo data when demo is chosen and resets the brand to the default', function (): void {
    useFixtureCustomer();
    Brand::save(['name' => 'Old Name']);

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class])
        ->expectsChoice(SEED_QUESTION, 'demo', seedOptions(true))
        ->assertSuccessful();

    expect(Branch::query()->pluck('code')->all())->toContain('BR01', 'BR02')
        ->and(Brand::name())->toBe('DT Restora');
});

it('seeds one customer with its brand when that customer is chosen', function (): void {
    useFixtureCustomer();

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class])
        ->expectsChoice(SEED_QUESTION, 'sample-cafe', seedOptions(true))
        ->assertSuccessful();

    expect(Branch::query()->pluck('code')->all())->toBe(['SC01'])
        ->and(Brand::name())->toBe('Sample Cafe')
        ->and(Brand::nameBn())->toBe('স্যাম্পল ক্যাফে')
        ->and(Brand::themeColor())->toBe('#123456')
        ->and(Brand::paletteCss())->toBe('html:root{--color-primary:#123456}');
});

it('answers without a terminal from SEED_PROFILE and skips when it is not set', function (): void {
    useFixtureCustomer();
    config(['brand.seed_profile' => 'sample-cafe']);

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--no-interaction' => true])->assertSuccessful();

    expect(Branch::query()->pluck('code')->all())->toBe(['SC01']);

    Branch::query()->delete();
    config(['brand.seed_profile' => null]);

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--no-interaction' => true])->assertSuccessful();

    expect(Branch::query()->count())->toBe(0);
});

it('offers only DT Restora (Demo) and skip when there is no customer seeder', function (): void {
    config(['app.is_demo' => false]);

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class])
        ->expectsChoice(SEED_QUESTION, 'skip', seedOptions(false))
        ->assertSuccessful();
});

it('never seeds again once a branch exists', function (): void {
    useFixtureCustomer();
    Branch::query()->create(['name' => 'Edited', 'code' => 'ED01', 'city' => 'Dhaka', 'vat_percent' => '0.00', 'service_charge_percent' => '0.00', 'is_active' => true]);

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class])->assertSuccessful();

    expect(Branch::query()->pluck('code')->all())->toBe(['ED01']);
});

it('keeps the brand in the settings table, cached, and refreshes it on every save', function (): void {
    Setting::current();
    Brand::save(['name' => 'First Name']);

    expect(Brand::name())->toBe('First Name')
        ->and(Cache::has('brand.settings'))->toBeTrue()
        ->and(Setting::query()->value('brand'))->toBe(['name' => 'First Name']);

    Brand::save(['name' => 'Second Name']);

    expect(Brand::name())->toBe('Second Name');

    config(['brand.name' => 'From env']);

    expect(Brand::name())->toBe('Second Name');

    Brand::save(null);

    expect(Brand::name())->toBe('From env');
});

it('shows the DT Restora brand in demo mode whatever the settings say', function (): void {
    Brand::save(['name' => 'Some Customer']);
    config(['app.is_demo' => true]);

    expect(Brand::name())->toBe('DT Restora');
});
