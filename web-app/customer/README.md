# Customer folder

This folder holds everything that belongs to one customer. Everything here except
this file is git-ignored, so the repository stays the plain DT Restora product and
each server keeps its own customer. It is used only when `IS_DEMO=false`.

Run `php artisan brand:status` to see what a server still takes from the DT Restora
default.

## What goes where

| What | Where | If missing |
| --- | --- | --- |
| Name, Bangla name, colours | `.env` (`BRAND_*`, see `.env.example`) | DT Restora name and Datatech blue |
| Logo set | `public/images/customer/brand/` (git-ignored) | DT Restora logo |
| Social preview image | `public/images/customer/og-default.png` (1200x630) | DT Restora image |
| Website wording | `customer/lang/en/*.php`, `customer/lang/bn/*.php` | Neutral DT Restora wording |
| Restaurant, menu, gallery | `customer/seed/CustomerDataSeeder.php` | Seeding warns and offers a first branch or skip |
| Customer-only tests | `customer/tests/` (run with `php artisan test customer/tests`) | none |
| Classes to load | `customer/autoload.php` | none |

## Logo set

Use the same file names as `public/images/default/brand`:

- `logo-192.png`, `logo-512.png`, `logo-maskable-512.png` (app icons, also the mark)
- `favicon.ico` (and optionally `favicon.png`, `favicon.svg`)
- optional: `logo-mark.svg` (preferred mark), `logo-wordmark.png`, `apple-touch-icon.png`

Any file you leave out falls back to the DT Restora one.

## Wording

A file in `customer/lang/{locale}/{group}.php` returns only the lines that differ from
`lang/{locale}/{group}.php`. Use `\App\Support\Brand::name()` instead of typing the
name when a line needs it.

## Seed data

`customer/seed/CustomerDataSeeder.php` must define `Database\Seeders\CustomerDataSeeder`.
`php artisan migrate:fresh --seed` runs it on an empty database. It never runs when
`IS_DEMO=true`; the demo seeders run instead. Load any extra classes from
`customer/autoload.php`.
