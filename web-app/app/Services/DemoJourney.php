<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Demo\RecordDemoEventsAction;
use App\Enums\DemoEventType;
use App\Models\DemoSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Finds the visitor's demo session from the long-lived demo cookie.
 * The cookie, not the Laravel session, identifies the visitor so the
 * journey survives the nightly database rebuild.
 */
class DemoJourney
{
    public const COOKIE = 'demo_token';

    public const COOKIE_MINUTES = 60 * 24 * 30;

    private const CACHE_SECONDS = 300;

    public function __construct(private readonly RecordDemoEventsAction $record) {}

    public static function enabled(): bool
    {
        return config('app.is_demo') === true;
    }

    public function current(Request $request): ?DemoSession
    {
        if (! self::enabled()) {
            return null;
        }

        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || strlen($token) !== 40) {
            return null;
        }

        /** @var array<string, mixed>|null $attributes */
        $attributes = Cache::remember(
            'demo:session:'.$token,
            self::CACHE_SECONDS,
            fn (): ?array => DemoSession::query()->where('token', $token)->first()?->getAttributes(),
        );

        return $attributes === null ? null : (new DemoSession)->newFromBuilder($attributes);
    }

    /**
     * Server side milestone, used where the browser cannot be trusted to report (admin demo login).
     *
     * @param  array<string, mixed>  $meta
     */
    public function recordFor(Request $request, DemoEventType $type, ?string $name = null, array $meta = []): void
    {
        $session = $this->current($request);

        if ($session === null) {
            return;
        }

        $this->record->execute($session, [[
            'type' => $type->value,
            'name' => $name,
            'path' => '/'.$request->path(),
            'meta' => $meta,
            'age_ms' => 0,
        ]]);
    }

    public function forget(string $token): void
    {
        Cache::forget('demo:session:'.$token);
    }

    /**
     * Small, non-sensitive description of the session for the browser.
     *
     * @return array{mobile: string, stage: string, started_at: string}|null
     */
    public function shared(Request $request): ?array
    {
        $session = $this->current($request);

        return $session === null ? null : $this->describe($session);
    }

    /**
     * @return array{mobile: string, stage: string, started_at: string}
     */
    public function describe(DemoSession $session): array
    {
        return [
            'mobile' => substr($session->mobile, 0, 5).'***'.substr($session->mobile, -3),
            'stage' => $session->stage,
            'started_at' => $session->started_at->toIso8601String(),
        ];
    }
}
