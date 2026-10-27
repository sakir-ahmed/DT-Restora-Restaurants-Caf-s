# DT Restora deployment

A fresh server can be deployed from this guide. The application is Laravel with Inertia, a database, a queue worker, a scheduler, and Reverb for live kitchen and rider updates.

## Server

- PHP 8.5 with the extensions Laravel requires, including `intl`, `mbstring`, `pdo`, and `redis` if you use Redis
- A database: MySQL or MariaDB in production
- Node.js for the production asset build
- A process supervisor for `php artisan queue:work`, `php artisan schedule:work`, and `php artisan reverb:start`

## First install

1. Copy the repository onto the server and point the web root at `public/`.
2. Copy `.env.example` to `.env`. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, and the database credentials.
3. Set `APP_KEY` with `php artisan key:generate`.
4. Keep `IS_DEMO=false`. The seeder then creates only the bootstrap data (roles, chart of accounts, units, payment methods, default settings) and the root administrator. Create the first branch and menu in the admin panel.
5. Install dependencies and build the interface:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

6. Prepare the database and the first accounts:

```bash
php artisan migrate --force
php artisan db:seed --force
```

The seeder prints the root administrator's username and a random strong password once. Save it straight away. If it is lost, run `php artisan admin:reset-password` to generate a new one.

For Arman's Kitchen, load the outlet, printed menu, photos and website gallery with:

```bash
php artisan db:seed --class=ArmansKitchenDataSeeder --force
```

```bash
php artisan storage:link
```

7. Cache the configuration:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Always-on processes

- Queue worker: `php artisan queue:work --tries=3`
- Scheduler: run `php artisan schedule:run` every minute, or keep `php artisan schedule:work` running. This posts the daily salary allocation and sends birthday and win-back SMS.
- Reverb: `php artisan reverb:start` and expose its port only to the application.

## Optional services

Leave a service blank when you do not use it. The application keeps working with the manual fallback.

- Google sign-in: `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`
- bKash, Nagad, and SSLCommerz keys in `.env`
- SMS: `BDBULKSMS_TOKEN`
- Map tiles: `MAP_TILES_URL`
- Push notifications: `FCM_SERVER_KEY`. Device tokens are stored either way. Messages are sent only when this key is set.

## API for the mobile apps

The versioned API is `/api/v1`. OpenAPI is at `/api-docs`. A Postman collection is written with:

```bash
php artisan api:postman
```

Staff and customer apps send `Authorization: Bearer <token>`. Order and payment posts send `Idempotency-Key`.

## Update

Put the site in maintenance mode, pull the new code, install dependencies, build assets, migrate, and restart the workers:

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan up
```

Restart the queue worker, scheduler, and Reverb so they load the new code.
