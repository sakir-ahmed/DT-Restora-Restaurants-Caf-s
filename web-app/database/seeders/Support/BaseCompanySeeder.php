<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Support\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Base class of every company's first-setup seeder. A company is one folder,
 * database/seeders/production/{Name}/, holding CompanySeeder.php (namespace
 * Database\Seeders\Production\{Name}) and the company's own files. The same names
 * are used in every company folder. ProductionCompanies lists it as a company.
 *
 * run() should seed everything the company needs on day one: call saveBrand()
 * and installFiles() first, then the branch, menu, gallery and so on. See
 * database/seeders/production/README.md.
 */
abstract class BaseCompanySeeder extends Seeder
{
    /**
     * The company name shown in the seeding menu.
     */
    public static function label(): string
    {
        return Str::headline(static::folder());
    }

    /**
     * The company folder name, taken from the namespace: Database\Seeders\Production\ArmansKitchen gives ArmansKitchen.
     */
    protected static function folder(): string
    {
        return Str::afterLast(Str::beforeLast(static::class, '\\'), '\\');
    }

    /**
     * The short name used by SEED_PROFILE, for example armans-kitchen.
     */
    public static function slug(): string
    {
        return Str::kebab(static::folder());
    }

    /**
     * The company's own folder, the one that holds its seeder file.
     */
    protected function companyPath(string $relative = ''): string
    {
        $folder = dirname((string) (new ReflectionClass(static::class))->getFileName());

        return $relative === '' ? $folder : $folder.'/'.$relative;
    }

    /**
     * Store the company's brand (name, name_bn, mark_shape, theme_color,
     * background_color, light and dark colour maps) in the settings table.
     *
     * @param  array<string, mixed>|null  $brand
     */
    protected function saveBrand(?array $brand): void
    {
        Brand::save($brand);
    }

    /**
     * Put the company's logo set, social image and website wording where the app
     * reads them: {Name}/brand and {Name}/og-default.png go to public/images/customer,
     * {Name}/lang goes to customer/lang. Both targets are git-ignored runtime copies.
     */
    protected function installFiles(): void
    {
        $brand = $this->companyPath('brand');
        $og = $this->companyPath('og-default.png');
        $lang = $this->companyPath('lang');

        File::deleteDirectory(Brand::customerImagePath());
        File::deleteDirectory(Brand::customerPath('lang'));

        if (is_dir($brand)) {
            File::copyDirectory($brand, Brand::customerImagePath('brand'));
        }

        if (is_file($og)) {
            File::ensureDirectoryExists(Brand::customerImagePath());
            File::copy($og, Brand::customerImagePath('og-default.png'));
        }

        if (is_dir($lang)) {
            File::copyDirectory($lang, Brand::customerPath('lang'));
        }
    }
}
