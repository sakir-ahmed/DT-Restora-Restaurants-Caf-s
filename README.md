# DT Restora

**DT Restora** — restaurant owner control system, a product of **Datatech BD Ltd** (datatechbd.com) — _Software that runs business._ An Owner Control System for restaurants and cafes in Bangladesh.

DT Restora goes beyond a standard POS. It gives owners clear financial visibility across branches (profit, recipe costing, wastage, inventory, full accounting) and runs the customer side too: online ordering, loyalty, delivery tracking and delivery-app order intake. The interface is fully bilingual (English and Bangla).

> This project is built phase by phase from the specification in `docs/`. Start with `docs/roadmap.md`.

---

## Features

- **Multi-branch management:** branch switching, role-based scoping, stock transfers, branch-wise P&L
- **Owner dashboard:** gross vs net sales, food cost, wastage, salary, expenses, estimated net profit, alerts
- **POS and payments:** touch-friendly POS, cash, bKash, Nagad, SSLCommerz, split payments, KDS, 80mm receipts
- **Recipes and inventory:** recipe builder, automatic stock deduction, costing engine, purchases, wastage logger, stock counts
- **Customer website:** SEO-friendly online ordering, QR table ordering, order tracking
- **Loyalty and coupons:** points ledger, tiers, coupons, SMS campaigns
- **Delivery:** dispatch board, rider web view with live location, customer tracking
- **Aggregator integrations:** adapter framework for delivery platforms, commission tracking
- **Accounting:** double-entry ledger, vouchers, payables, trial balance, P&L, balance sheet, VAT summary
- **Full offline mode:** installable PWA with local queue and automatic sync
- **Mobile-ready API:** documented, versioned `/api/v1` for future customer, rider and staff apps (the mobile apps are a separate project)

Full scope and business rules: `docs/prd.md`.

---

## Tech Stack

| Area                  | Technology                                             |
| --------------------- | ------------------------------------------------------ |
| Backend               | Laravel 13.x, PHP 8.3+                                 |
| Database              | MySQL 8                                                |
| Cache, session, queue | Redis                                                  |
| Frontend              | Inertia.js v2, Vue 3, Tailwind CSS v4, shadcn-vue      |
| Icons and charts      | Font Awesome Free, Chart.js                            |
| Real-time             | Laravel Reverb                                         |
| Auth and roles        | Laravel Sanctum, Socialite (Google), Spatie Permission |
| Audit and backup      | Spatie Activitylog, Spatie Backup                      |
| SMS                   | BdBulkSMS                                              |
| Testing and quality   | Pest, Laravel Pint, Larastan                           |
| Containers            | Docker and Docker Compose                              |

---

## Documentation

All project documentation lives in `docs/`. AI coding agents read `CLAUDE.md` first.

| File                   | Purpose                                                           |
| ---------------------- | ----------------------------------------------------------------- |
| `docs/prd.md`          | Product requirements: roles, modules, business rules, scope       |
| `docs/architecture.md` | Tech stack, folder structure, coding rules, Definition of Done    |
| `docs/api.md`          | API conventions, endpoints, payment gateway flows                 |
| `docs/design.md`       | Design system, components, icons, sizing, tables, dialogs, charts |
| `docs/roadmap.md`      | Build phases with acceptance criteria                             |
| `docs/ui-preview.html` | Static visual mockup of the intended UI (open in a browser)       |

---

## Getting Started

### Requirements

- Docker and Docker Compose
- Git
- Node.js 20+ and npm (only if you run the frontend outside Docker)

### Setup

```sh
git clone <repository-url> dt-restora
cd dt-restora

cp .env.example .env
# Edit .env: database, Redis, mail, BdBulkSMS, bKash, SSLCommerz, Google OAuth

docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app npm install
docker compose exec app npm run dev
```

The service names above (`app`) follow the Docker Compose file created in Phase 0 of the roadmap. Adjust them if yours differ.

Open the app at `http://localhost`. The admin panel is at `/admin`. The root administrator account is created by the seeder from the values in `.env`:

```text
ADMIN_NAME=
ADMIN_EMAIL=
ADMIN_PASSWORD=
```

Never commit real credentials. Change the default password before deploying.

### Background processes

These run as separate containers in the Docker setup:

| Process                                      | Command                     |
| -------------------------------------------- | --------------------------- |
| Queue worker                                 | `php artisan queue:work`    |
| Scheduler (backups, loyalty expiry, reports) | `php artisan schedule:work` |
| Real-time server (Reverb)                    | `php artisan reverb:start`  |

---

## Environment Variables

Key groups in `.env.example` (see the file for the full list):

| Group    | Examples                                                              |
| -------- | --------------------------------------------------------------------- |
| App      | `APP_NAME`, `APP_URL`, `APP_LOCALE`, `APP_TIMEZONE=Asia/Dhaka`        |
| Database | `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`                |
| Redis    | `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis` |
| Reverb   | `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST` |
| SMS      | `BDBULKSMS_URL`, `BDBULKSMS_TOKEN`                                    |
| Payments | `BKASH_*`, `NAGAD_*`, `SSLCOMMERZ_*`                                  |
| Login    | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`                            |
| Backup   | `AWS_*` (if backing up to S3)                                         |

---

## Development Workflow

```sh
# Run tests
php artisan test            # or: ./vendor/bin/pest

# Code style (auto-fix)
./vendor/bin/pint

# Static analysis
./vendor/bin/phpstan

# Fresh database with demo data
php artisan migrate:fresh --seed
```

Rules to follow:

- Build one roadmap phase at a time; a phase is finished only when its acceptance criteria and the Definition of Done in `docs/architecture.md` are met.
- Every feature has Pest tests, including authorization and branch isolation.
- No emojis anywhere (UI, translations, SMS, receipts, code, commits). Icons come from Font Awesome through `AppIcon`.
- Every text key exists in both `lang/en` and `lang/bn`.

---

## Project Structure

```text
app/
  Actions/          single-purpose business operations
  Services/         integrations (SMS, payments, aggregators) and orchestration
  Enums/            typed enums
  Http/
    Controllers/    Admin (SPA), Api/V1, Web (public SSR)
    Requests/       validation
    Resources/      API resources
  Models/           Eloquent models
resources/js/       Vue 3 pages and components (ui-kit for shared components)
lang/en, lang/bn    translations
docs/               project specification
tests/              Pest tests
```

---

## Roles

`super-admin`, `owner`, `branch-manager`, `cashier`, `waiter`, `kitchen-manager`, `accountant`, `rider`. Customers use a separate login and cannot access `/admin`.

---

## Deployment Notes

- One installation per client (single-tenant), with multiple branches inside it.
- Serve over HTTPS only. Set `APP_ENV=production` and `APP_DEBUG=false`.
- Schedule `backup:run` daily and test a restore before going live.
- Run the queue worker, scheduler and Reverb under a process supervisor.
- Confirm VAT rate, invoice numbering and receipt format with the client's accountant.

---

## Support

**DT Restora** is a product of **Datatech BD Ltd** — datatechbd.com — _Software that runs business._

- Email: info@datatechbd.com
- Phone: +880 1575-888775
- WhatsApp: +880 1575-888775
- Address: Flat 4-A, Dhumketu Shikder Tower, Holding No. 66, Paschim Monipur, Mirpur, Dhaka-1216, Bangladesh

## License

Proprietary. All rights reserved by Datatech BD Ltd. Not for redistribution.
