<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\PublicCache;

/**
 * Any change to a model that feeds the public website invalidates the cached pages.
 */
trait FlushesPublicCache
{
    public static function bootFlushesPublicCache(): void
    {
        static::saved(static fn () => PublicCache::bump());
        static::deleted(static fn () => PublicCache::bump());
    }
}
