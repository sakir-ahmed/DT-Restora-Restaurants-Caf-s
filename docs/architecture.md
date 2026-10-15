# ARCHITECTURE & CODING RULES

> Same for every product. Copy this file unchanged into each project's `docs/`.
> If this file conflicts with the product's `prd.md`, **the PRD wins** for business rules and roles. This file wins for code structure.

---

## 1. Tech Stack

| Area                    | Choice                                                                                       |
| ----------------------- | -------------------------------------------------------------------------------------------- |
| Framework               | Laravel 13.x (latest stable), PHP 8.3+. Check with `composer show laravel/framework`.        |
| Database                | MySQL 8                                                                                      |
| Cache / Session / Queue | Redis (`CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` = `redis`)                        |
| Frontend                | Inertia.js v2, Vue 3 (`<script setup>`), Tailwind CSS v4, shadcn-vue (Radix Vue)             |
| Toasts                  | `vue-sonner`                                                                                 |
| Auth                    | Login by username / email / mobile. Google login via Socialite. Spatie Permission for roles. |
| Real-time               | Laravel Reverb (Pusher protocol)                                                             |
| Audit                   | `spatie/laravel-activitylog`                                                                 |
| Backup                  | `spatie/laravel-backup` (daily, via scheduler)                                               |
| Localization            | `en` and `bn` (`lang/en`, `lang/bn`)                                                         |
| SMS                     | BdBulkSMS via `SmsService` (section 5)                                                       |
| Testing                 | Pest                                                                                         |
| Code quality            | Laravel Pint, Larastan (level 5 minimum)                                                     |
| Containers              | Docker Compose: nginx, php-fpm, mysql, redis, queue worker, scheduler, reverb                |
| Icons                   | Font Awesome Free via `@fortawesome/vue-fontawesome` (tree-shaken SVG), wrapped in `AppIcon` |
| Charts                  | `chart.js` + `vue-chartjs` (one library only)                                                |
| Offline / PWA           | `vite-plugin-pwa` (Workbox) + IndexedDB via Dexie                                            |
| API docs                | `dedoc/scramble` (OpenAPI at `/api-docs`)                                                    |
| Maps                    | Leaflet, tile provider from `.env`                                                           |
| Bot protection          | Cloudflare Turnstile on guest checkout                                                       |
| Push (optional)         | FCM, only when credentials are configured                                                    |

**Rendering**

- Public pages (`Web/` controllers): Inertia SSR with dynamic meta and OpenGraph tags.
- Admin panel (`/admin`): client-side SPA, **SSR disabled**. No Filament.

---

## 2. Folder Structure

```text
app/
├── Actions/            # One class = one business operation (CreateOrderAction)
├── Services/           # External integrations + orchestration (SmsService, BkashService)
├── DataTransferObjects/# Only when an Action has 4+ inputs
├── Enums/              # OrderStatus, PaymentMethod ...
├── Http/
│   ├── Controllers/
│   │   ├── Admin/      # Inertia controllers for the SPA (POS included)
│   │   ├── Api/V1/     # JSON API (see api.md)
│   │   └── Web/        # Public SSR pages
│   ├── Requests/       # Form Requests (all validation lives here)
│   └── Resources/      # API Resources
└── Models/             # Relationships, scopes, casts, LogsActivity. No business logic.
```

**No Repository layer.** Eloquent is the data layer. Add an interface only when there are two or more real implementations. In this product that applies to exactly two places: `PaymentGateway` (bKash, Nagad, SSLCommerz) and `AggregatorChannel` (one adapter per delivery platform). Put them in `app/Services/Payments/` and `app/Services/Aggregators/`.

---

## 3. Core Rules

1. **Thin controllers.** Validate (Form Request) → call an Action → return a response.
2. **Admin controllers and API controllers call the same Actions.** Never duplicate business logic.
3. **Strict types.** `declare(strict_types=1);`, typed parameters, return types and properties everywhere.
4. **No over-engineering.** No factories, abstractions or patterns without a concrete need today.
5. **Money:** store as `decimal(12,2)` in BDT. Never use floats. API returns money as strings (`"1250.00"`).
6. **Stock and payments run inside `DB::transaction()`.** Use `lockForUpdate()` when deducting stock.
7. **Branch scoping:** every branch-owned model uses a global scope. Non-owner users must never read another branch's data. Test this.
8. **All user-facing text uses translation keys** (`__('orders.created')`), never hard-coded strings.
9. **Secrets only in `.env`**, accessed via `config()`. Never call `env()` outside config files.
10. **No emojis anywhere** (UI, translations, SMS, receipts, seeders, comments, commits). Icons come from Font Awesome through `AppIcon`; see `design.md` section 23.
11. **Eager load relations** to avoid N+1. Add DB indexes on foreign keys and common filters.

---

## 4. Roles & Permissions

Spatie Permission. Default seed is **replaced by the roles listed in `prd.md`**. Always seed:

- `super-admin` (Datatech support, full access)
- the product roles from the PRD (for a restaurant: `owner`, `branch-manager`, `cashier`, `waiter`, `kitchen-manager`)

Use permissions (`orders.void`), not role names, in policies and `@can` checks. Seed a default root account from `.env` values (never hard-code a password in code).

---

## 5. Authentication, OTP & SMS

### Login (username, email or mobile)

```php
public function getCredentials(): array
{
    $login = trim($this->string('login')->toString());

    $field = match (true) {
        filter_var($login, FILTER_VALIDATE_EMAIL) !== false => 'email',
        preg_match('/^(\+?88)?01[3-9]\d{8}$/', $login) === 1 => 'mobile',
        default => 'username',
    };

    if ($field === 'mobile') {
        $login = normalize_bd_mobile($login); // -> 8801XXXXXXXXX, store mobiles in this format
    }

    return [$field => $login, 'password' => $this->input('password')];
}
```

Throttle login with `RateLimiter` (5 attempts per minute per login + IP).

### SmsService (transport only)

```php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(string $mobile, string $message): bool
    {
        $response = Http::asForm()
            ->timeout(10)
            ->post(config('services.bdbulksms.url'), [
                'to' => $mobile,
                'message' => $message,
                'token' => config('services.bdbulksms.token'),
            ]);

        // Confirm the exact success format in the BdBulkSMS API docs.
        $sent = $response->successful() && str_starts_with($response->body(), 'Ok');

        $sent
            ? Log::info('SMS sent.', ['mobile' => $mobile])
            : Log::error('SMS failed.', ['mobile' => $mobile, 'status' => $response->status()]);

        return $sent;
    }
}
```

- **Never disable SSL verification** (`'verify' => false` is forbidden).
- Config in `config/services.php`: `bdbulksms.url`, `bdbulksms.token`.
- Send SMS through a queued Job so a slow gateway never blocks a request.

### OtpService (rules)

- Generate with `random_int(100000, 999999)`.
- Store **hashed** (`Hash::make`) in Redis cache, key `otp:{mobile}`, **TTL 5 minutes**.
- Send limit: `RateLimiter` keyed by **IP + mobile** (max 5 per hour). Not session-based; clearing cookies must not bypass it.
- Verify limit: max 5 wrong attempts per code, then invalidate the code.
- Delete the code after successful verification.

---

## 6. Logging

Use `Log` with PSR-3 levels and context arrays. Never log passwords, OTPs, tokens or full card/wallet numbers.

```php
Log::info('Order created.', ['order_id' => $order->id, 'branch_id' => $order->branch_id]);
Log::warning('Stock below threshold.', ['ingredient_id' => $id]);
Log::error('Payment failed.', ['order_id' => $id, 'error' => $e->getMessage()]);
```

Do not log the stack trace in every message; the exception handler already does it. Log with `'exception' => $e` only when needed.

---

## 7. Activity Log & Backup

`LogsActivity` is a **trait**, not an interface:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'mobile', 'username'])
            ->logOnlyDirty();
    }
}
```

- Use `logOnly([...])`, never `logAll()` (it can log password hashes).
- Explicitly log sensitive actions with `activity()->causedBy($user)->performedOn($model)->log('order.voided')`.
- Backup: `backup:run --only-db` daily via scheduler to S3 or a mounted volume, `backup:clean` weekly, and notify on failure.

---

## 8. Localization

- Files: `lang/en/*.php` and `lang/bn/*.php`. Every key must exist in both.
- A middleware sets the locale from user preference → session → default `en`.
- Vue receives translations through Inertia shared props.
- Dates and numbers: format through one helper so Bangla digits can be toggled.

---

## 9. Frontend Rules

- `<script setup>` only. One component = one responsibility.
- Show feedback on every save/delete/error with `toast.success()` / `toast.error()`.
- Handle loading, empty and error states in every list and form.
- Inertia forms use `useForm`. Show server validation errors beside fields.
- Visual and interaction rules are in `design.md`. Dialogs, confirmations, validation messages, dashboards, charts, reports and tables **must** follow sections 12 to 22 there.
- Build the shared UI kit (`resources/js/components/ui-kit/`) first and reuse it. Never use `window.alert()`, `confirm()` or `prompt()`, and never build a one-off dialog, table or chart inside a page.
- Validation lives in Form Requests. Display errors with the `FormField` component, focus the first invalid field, and never clear the form on error.

---

## 10. Testing & Definition of Done

A feature is **done** only when all of these are true:

- [ ] Pest feature tests cover the happy path and main failure cases
- [ ] Authorization tested (allowed role passes, other role gets 403, other branch gets 404)
- [ ] `./vendor/bin/pint` is clean and `./vendor/bin/phpstan` passes
- [ ] Both `en` and `bn` translations added
- [ ] Sensitive actions write an activity log entry
- [ ] `NoEmojiTest` passes (no emojis or symbol icons in code, lang files, views or docs)
- [ ] The UI Definition of Done checklist in `design.md` (section 22) passes
- [ ] Migrations run fresh (`migrate:fresh --seed`) without errors
- [ ] The acceptance criteria for the feature in `roadmap.md` are met

---

## 11. Customers, Guards & Public Website

- Staff use the `users` table and the `staff` guard. Customers use the `customers` table and the `customer` guard. Never mix them and never give customers Spatie roles.
- Customer login: mobile OTP or Google. Same OtpService rules as in section 5.
- Customer-owned models (orders, addresses, loyalty entries) use a policy: `customer_id === auth customer id`, else 404.
- Public pages are Inertia SSR; everything under `/admin`, the POS and the rider view is client-side only.
- Order placement from the website, the POS, the API and aggregators all go through **one** `CreateOrderAction` so pricing, stock, VAT, loyalty and accounting behave the same everywhere.
- Delivery tracking links use signed, expiring tokens. Rider location writes are throttled and broadcast only to the order's tracking channel.

---

## 12. Accounting Rules

- All postings go through a single `PostJournalEntryAction`. Controllers and other Actions never write journal lines directly.
- Posting happens in the **same DB transaction** as the business event (order paid, purchase received, expense approved). If posting fails, the event fails.
- A journal entry must balance (sum of debits = sum of credits), checked in code and by a database-level test.
- Posted entries are immutable. Corrections are reversal entries that reference the original.
- Locked periods reject new postings with a clear error.
- Amounts use `decimal(12,2)`. Never use floats anywhere in accounting code.
- Account mapping (which account a sale, VAT or payment method posts to) is configuration, not hard-coded IDs.

---

## 13. Offline-First Rules

- Admin, POS and KDS are a PWA. The service worker caches the app shell with versioned caches; on a new deploy, show "Update available" and never reload in the middle of an order.
- Reference data comes from `/api/v1/sync/bootstrap` and `/sync/changes`, stored in IndexedDB (Dexie).
- Every write made offline is stored in a local **outbox** with a `client_id` (ULID), type, payload and timestamp, then pushed to `/api/v1/sync/push`. Retries must be safe because the server is idempotent by `client_id`.
- The UI writes to the local database first and shows the result immediately; sync is a background concern.
- Never queue gateway payments, SMS sending, aggregator calls or OTP verification. These show "needs internet".
- Do not store passwords, API secrets or gateway credentials on the device. Lock the POS with a staff PIN after inactivity.
- The server is the source of truth. Conflicts are recorded and surfaced, never silently dropped or overwritten.
- Test offline behavior with a Playwright test that goes offline, takes orders, reconnects and asserts no duplicates and no losses.

---

## 14. Working Method (for the AI)

1. Read `docs/prd.md`, `docs/api.md`, `docs/design.md`, `docs/roadmap.md`.
2. Build **one phase at a time** from `roadmap.md`. Do not start the next phase.
3. For each feature: migration → model → Form Request → Action → controller → Vue page → tests.
4. If something in the docs is unclear or contradictory, **ask before assuming**.
5. Run tests and fix failures before reporting a feature as finished.
