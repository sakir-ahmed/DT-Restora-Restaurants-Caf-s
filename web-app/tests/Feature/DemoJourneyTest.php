<?php

declare(strict_types=1);

use App\Enums\DemoEventType;
use App\Models\DemoEvent;
use App\Models\DemoSession;
use App\Models\User;
use App\Services\DemoJourneyArchive;
use App\Services\PublicCache;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

function demoSession(array $attributes = []): DemoSession
{
    return DemoSession::query()->create([
        'token' => str_repeat('a', 40),
        'mobile' => '8801712345678',
        'started_at' => now(),
        ...$attributes,
    ]);
}

it('hides the demo session routes unless demo mode is on', function (): void {
    config(['app.is_demo' => false]);

    $this->postJson('/demo/session', ['mobile' => '01712345678', 'consent' => true])->assertNotFound();
    $this->postJson('/demo/events', ['events' => [['type' => 'page_view']]])->assertNotFound();
});

it('starts a session from a mobile number and remembers it in a cookie', function (): void {
    config(['app.is_demo' => true]);

    $response = $this->postJson('/demo/session', ['mobile' => '01712-345678', 'consent' => true, 'utm_source' => 'facebook'])
        ->assertOk()
        ->assertJsonPath('session.stage', 'visited')
        ->assertCookie('demo_token');

    $session = DemoSession::query()->firstOrFail();

    expect($session->mobile)->toBe('8801712345678')
        ->and($session->utm_source)->toBe('facebook')
        ->and($session->token)->toHaveLength(40)
        ->and($session->events()->pluck('type')->all())->toBe(['session_start']);

    $token = $session->token;

    $this->withCookie('demo_token', $token)
        ->get('/')
        ->assertInertia(fn ($page) => $page->where('demoSession.mobile', '88017***678'));
});

it('rejects an invalid mobile number or a missing consent', function (): void {
    config(['app.is_demo' => true]);

    $this->postJson('/demo/session', ['mobile' => '12345', 'consent' => true])->assertJsonValidationErrors('mobile');
    $this->postJson('/demo/session', ['mobile' => '01712345678'])->assertJsonValidationErrors('consent');

    expect(DemoSession::query()->count())->toBe(0);
});

it('stores batched events and only moves the funnel forward', function (): void {
    config(['app.is_demo' => true]);
    $session = demoSession();

    $this->withCredentials()
        ->withCookie('demo_token', $session->token)
        ->postJson('/demo/events', ['events' => [
            ['type' => 'page_view', 'path' => '/about?x=1', 'age_ms' => 2000],
            ['type' => 'add_to_cart', 'name' => 'Chicken Biryani', 'meta' => ['price' => 350]],
            ['type' => 'menu_view', 'path' => '/menu'],
        ]])
        ->assertStatus(202)
        ->assertJsonPath('recorded', 3);

    $session->refresh();

    expect($session->stage)->toBe('added_to_cart')
        ->and($session->events_count)->toBe(3)
        ->and($session->page_views)->toBe(2)
        ->and(DemoEvent::query()->where('type', 'page_view')->value('path'))->toBe('/about');
});

it('ignores events from visitors without a session and rejects unknown event types', function (): void {
    config(['app.is_demo' => true]);

    $this->postJson('/demo/events', ['events' => [['type' => 'page_view']]])
        ->assertStatus(202)
        ->assertJsonPath('recorded', 0);

    $session = demoSession();

    $this->withCredentials()
        ->withCookie('demo_token', $session->token)
        ->postJson('/demo/events', ['events' => [['type' => 'drop_database']]])
        ->assertJsonValidationErrors('events.0.type');

    expect(DemoEvent::query()->count())->toBe(0);
});

it('records the admin demo login against the visitor session', function (): void {
    config(['app.is_demo' => true]);
    $this->seed(RoleSeeder::class);

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('owner');
    $session = demoSession();

    $this->withCookie('demo_token', $session->token)
        ->post('/login/demo', ['user_id' => $user->id])
        ->assertRedirect();

    expect($session->refresh()->stage)->toBe('tried_admin')
        ->and($session->events()->where('type', DemoEventType::AdminLogin->value)->value('name'))->toBe('owner');
});

it('keeps mobile numbers and journeys through the nightly rebuild', function (): void {
    $session = demoSession();
    $session->events()->create(['type' => 'page_view', 'path' => '/', 'occurred_at' => now()]);
    $session->events()->create(['type' => 'add_to_cart', 'name' => 'Tea', 'meta' => ['price' => 40], 'occurred_at' => now()]);

    $archive = app(DemoJourneyArchive::class);
    $path = $archive->snapshot();

    expect($path)->not->toBeNull();

    DB::table('demo_events')->delete();
    DB::table('demo_sessions')->delete();

    expect($archive->restore($path))->toBe(3);

    $restored = DemoSession::query()->firstOrFail();

    expect($restored->id)->toBe($session->id)
        ->and($restored->mobile)->toBe('8801712345678')
        ->and($restored->events()->pluck('type')->all())->toBe(['page_view', 'add_to_cart'])
        ->and(is_file($path))->toBeFalse();
});

it('serves public pages from cache until something they show changes', function (): void {
    $calls = 0;
    $build = function () use (&$calls): int {
        return ++$calls;
    };

    expect(PublicCache::remember('probe', $build))->toBe(1)
        ->and(PublicCache::remember('probe', $build))->toBe(1);

    PublicCache::bump();

    expect(PublicCache::remember('probe', $build))->toBe(2);
});

it('does not cache for signed-in staff', function (): void {
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->create(), 'staff');

    $calls = 0;
    $build = function () use (&$calls): int {
        return ++$calls;
    };

    PublicCache::remember('probe', $build);
    PublicCache::remember('probe', $build);

    expect($calls)->toBe(2);
});
