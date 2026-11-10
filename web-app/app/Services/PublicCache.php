<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived cache for read-heavy public pages (home, menu, site header).
 *
 * Keys carry a version number, so one bump invalidates every entry on any
 * cache store, including the database store that has no tags. Signed-in
 * staff skip the cache because branch scoping changes what they may see.
 */
class PublicCache
{
    private const VERSION_KEY = 'public-cache:version';

    public const TTL_SECONDS = 600;

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function remember(string $key, Closure $callback, int $seconds = self::TTL_SECONDS): mixed
    {
        if (auth('staff')->check()) {
            return $callback();
        }

        return Cache::remember('public-cache:v'.self::version().':'.$key, $seconds, $callback);
    }

    public static function bump(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    private static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
