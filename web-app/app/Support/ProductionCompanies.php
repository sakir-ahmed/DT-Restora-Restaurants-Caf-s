<?php

declare(strict_types=1);

namespace App\Support;

use Database\Seeders\Production\DtRestora\CompanySeeder as DtRestoraSeeder;
use Database\Seeders\Support\BaseCompanySeeder;

/**
 * The companies this codebase can set up. DT Restora (the demo, slug "demo")
 * always comes first. Every other folder database/seeders/production/{Name}/
 * holds a CompanySeeder.php that defines
 * Database\Seeders\Production\{Name}\CompanySeeder extending BaseCompanySeeder.
 * A new customer is one new folder; nothing here changes.
 */
final class ProductionCompanies
{
    /**
     * @return array<string, array{label: string, class: class-string<BaseCompanySeeder>}> keyed by slug
     */
    public static function all(): array
    {
        return ['demo' => ['label' => DtRestoraSeeder::label(), 'class' => DtRestoraSeeder::class], ...self::customers()];
    }

    /**
     * The customer companies, without the DT Restora demo.
     *
     * @return array<string, array{label: string, class: class-string<BaseCompanySeeder>}> keyed by slug
     */
    public static function customers(): array
    {
        $found = [];

        foreach (glob(self::path('*/CompanySeeder.php')) ?: [] as $file) {
            $name = basename(dirname($file));

            if ($name === 'DtRestora') {
                continue;
            }

            require_once $file;

            $class = 'Database\\Seeders\\Production\\'.$name.'\\CompanySeeder';

            if (! class_exists($class) || ! is_subclass_of($class, BaseCompanySeeder::class)) {
                continue;
            }

            $found[$class::slug()] = ['label' => $class::label(), 'class' => $class];
        }

        ksort($found);

        return $found;
    }

    public static function path(string $relative = ''): string
    {
        $root = config('brand.production_path') ?: database_path('seeders/production');

        return rtrim((string) $root, '/').($relative === '' ? '' : '/'.$relative);
    }
}
