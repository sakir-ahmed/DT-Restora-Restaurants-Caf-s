# Production companies

One seeder per customer. `php artisan migrate:fresh --seed` (or `db:seed`) on an empty
production database (`IS_DEMO=false`) asks **which company this install is for**:

- DT Restora (Demo): the sample data of `IS_DEMO=true`
- one entry per company folder here (Arman's Kitchen, Hills Table, ...)
- Skip, and set up later in the admin panel

Without a terminal, set `SEED_PROFILE` to `demo`, a company slug such as `armans-kitchen`,
or `skip`. It only asks while there is no branch, so admin-panel edits are never overwritten.
To re-sync one company on purpose:
`php artisan db:seed --class="Database\Seeders\Production\ArmansKitchen\CompanySeeder" --force`

## Adding a customer

One folder per company, named after it in StudlyCase, holding a seeder with the same name plus `Seeder`:

```
production/HillsTable/HillsTableSeeder.php
```

1. Create the folder `HillsTable/` and `HillsTableSeeder.php` in it (namespace
   `Database\Seeders\Production\HillsTable`, extends `Database\Seeders\Support\CompanySeeder`).
   The company name and the `SEED_PROFILE` slug come from the class name: `HillsTable`
   becomes "Hills Table" and `hills-table`.
2. Put everything else it needs in the same folder (see below).
3. Run `composer dump-autoload` (the folder is a classmap).

```php
namespace Database\Seeders\Production\HillsTable;

class HillsTableSeeder extends CompanySeeder
{
    public function run(): void
    {
        $this->saveBrand(['name' => 'Hills Table', 'name_bn' => '...', 'light' => ['primary' => '#1f180e']]);
        $this->installFiles();
        // branch, menu, gallery, delivery areas, settings ...
    }
}
```

## What a company folder holds

Inside `production/{Name}/`:

| Path | Used for |
| --- | --- |
| `{Name}Seeder.php` | Brand, branch, menu, gallery and everything else for day one |
| `brand/` | Logo set: `logo-192.png`, `logo-512.png`, `logo-maskable-512.png`, `favicon.ico`; optional `logo-mark.svg`, `logo-wordmark.png`, `apple-touch-icon.png`, `favicon.png`, `favicon.svg`. A missing file falls back to the DT Restora one. |
| `og-default.png` | Social preview image (1200x630) |
| `lang/{en,bn}/*.php` | Website wording: only the lines that differ from `lang/{locale}`. Use `\App\Support\Brand::name()` instead of typing the name. |
| `assets/` | Source photos and any other files the seeder copies |

`installFiles()` copies `brand`, `og-default.png` and `lang` to the git-ignored runtime
folders `public/images/customer` and `customer/lang`; the brand itself (name, Bangla name,
`mark_shape`, `theme_color`, `background_color`, and `light` / `dark` maps of `background`,
`surface`, `foreground`, `muted`, `border`, `primary`, `primary-foreground`, `accent`,
`accent-foreground`) goes to the settings table and is cached.

`php artisan brand:status` shows what an install still takes from the DT Restora default.
