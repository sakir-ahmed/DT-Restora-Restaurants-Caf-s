<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('refuses to rebuild the database unless demo mode is on', function (): void {
    $user = User::factory()->create();

    config(['app.is_demo' => false]);

    $this->artisan('demo:reset')->assertFailed();

    $this->assertModelExists($user);
});

it('shares a demo flag so every page can warn visitors', function (): void {
    config(['app.is_demo' => true]);

    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('isDemo', true));

    config(['app.is_demo' => false]);

    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('isDemo', false));
});

it('schedules the demo rebuild for 4:00 in Dhaka and skips it otherwise', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'demo:reset'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 4 * * *')
        ->and($event->timezone)->toBe('Asia/Dhaka');

    config(['app.is_demo' => false]);
    expect($event->filtersPass(app()))->toBeFalse();

    config(['app.is_demo' => true]);
    expect($event->filtersPass(app()))->toBeTrue();

    Date::setTestNow(Date::parse('2026-10-16 22:00:00', 'UTC'));
    expect($event->isDue(app()))->toBeTrue();

    Date::setTestNow(Date::parse('2026-10-16 21:00:00', 'UTC'));
    expect($event->isDue(app()))->toBeFalse();

    Date::setTestNow();
});
