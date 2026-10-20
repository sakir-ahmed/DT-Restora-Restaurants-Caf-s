<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\File;

/**
 * Optimized demo photographs shipped with the app (food and dining rooms).
 * Seeding copies them into the public disk so the site can serve them.
 */
final class DemoPhotos
{
    private const FALLBACK = 'platter';

    /** @var array<string, string> */
    private static array $published = [];

    public static function source(string $key): ?string
    {
        $path = self::assetPath($key);

        if (is_file($path)) {
            return $path;
        }

        $fallback = self::assetPath(self::FALLBACK);

        return is_file($fallback) ? $fallback : null;
    }

    /**
     * Copy a photo onto the public disk and return its storage-relative path.
     */
    public static function publish(string $key): ?string
    {
        if (array_key_exists($key, self::$published)) {
            return self::$published[$key];
        }

        $source = self::source($key);

        if ($source === null) {
            return self::$published[$key] = null;
        }

        $filename = $key.'.'.pathinfo($source, PATHINFO_EXTENSION);
        $relative = 'demo/'.$filename;
        $destination = storage_path('app/public/'.$relative);

        File::ensureDirectoryExists(dirname($destination));

        if (! is_file($destination) || filemtime($source) > filemtime($destination)) {
            File::copy($source, $destination);
        }

        return self::$published[$key] = $relative;
    }

    private static function assetPath(string $key): string
    {
        $png = database_path('seeders/assets/photos/'.$key.'.png');

        return is_file($png) ? $png : database_path('seeders/assets/photos/'.$key.'.webp');
    }
}
