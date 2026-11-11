# Software development standards and preferences (how this owner builds software)

Purpose: a new AI agent in another project reads this file to learn how the owner designs and builds software: stack, architecture, folder layout, code rules, UI look and behaviour, effects, sound, settings, API design, offline rules, testing and process. Follow it by default in new projects, and only deviate when the new project's own spec says so.

Source: the DT Restora monorepo (`web-app/` Laravel + Vue, `flutter-app/` Flutter, `docs/`), read on 12 Nov 2026. Statements below come from `docs/architecture.md`, `docs/design.md`, `docs/api.md`, `docs/prd.md`, `docs/roadmap.md`, `docs/docker.md`, `docs/flutter-parity-plan.md`, `web-app/AGENTS.md`, `CLAUDE.md`, and the real code (`resources/js/lib/sounds.js`, `resources/css/app.css`, `package.json`, `composer.json`, `pubspec.yaml`). Where the repo contains two different statements, both are noted. Companion file: `marketing-reels-and-meta-ads-agent-playbook.md` (marketing and ads work).

---

## 1. The owner's development philosophy

1. **Specification first.** Docs are written before code and are the contract: `prd.md` (business rules and roles), `architecture.md` (code structure), `api.md` (API contract), `design.md` (look and behaviour), `roadmap.md` (phases with acceptance criteria). **The PRD wins on business rules and roles; `architecture.md` wins on code structure.**
2. **Same standards for every product.** `architecture.md`, `design.md` and the conventions of `api.md` are meant to be copied unchanged into each new product. Only `prd.md`, the endpoint list in `api.md`, and `roadmap.md` are rewritten per product.
3. **One phase at a time.** Build one roadmap phase, run tests, stop. Never start the next phase on your own.
4. **Dense, fast, professional.** Compact controls, information-dense screens, instant feedback, both light and dark mode, Bangla and English everywhere.
5. **No over-engineering.** No factories, abstractions or patterns without a concrete need today. No repository layer. Thin controllers, business logic in Actions.
6. **Business correctness over cleverness.** Money as decimals, stock and payments in transactions, accounting entries balanced and immutable, branch data isolated and tested.
7. **Ask before assuming.** If the docs are unclear or contradict each other, ask. (Written in `CLAUDE.md` and `architecture.md` section 14.)
8. **Keep client and docs in sync.** When an endpoint changes, update the Flutter client and the docs in the same change.
9. **Never use emojis or Unicode symbol icons anywhere** (UI, lang files, SMS, receipts, PDFs, seeders, comments, commits, docs). A Pest test enforces it.
10. **Real data and real flows over mock-ups.** Demo and marketing use seeded, believable data (see section 17).

---

## 2. Technology stack (default choices)

| Area | Choice |
| --- | --- |
| Backend | Laravel 13.x, PHP 8.3+ (`AGENTS.md` states the dev machine runs PHP 8.5; keep code valid on 8.3) |
| Database | MySQL 8 in production; SQLite in local development and for marketing data |
| Cache, session, queue | Redis in production (`CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` = `redis`) |
| Frontend | Inertia.js v2/v3, Vue 3 `<script setup>`, Tailwind CSS v4 (CSS-first `@theme` tokens), shadcn-vue style components on `reka-ui` (Radix Vue successor) |
| Toasts | `vue-sonner` behind one `notify.*` wrapper |
| Icons | Font Awesome Free (tree-shaken SVG, solid style) wrapped in `AppIcon`; never import FA directly in pages; replace shadcn's default Lucide icons |
| Charts | `chart.js` + `vue-chartjs` only, wrapped in `ChartCard` |
| Maps | Leaflet |
| Real-time | Laravel Reverb (Pusher protocol), Laravel Echo client |
| Auth | Laravel Sanctum (API tokens), session guards for the web; login by username, email or mobile; Google login via Socialite; mobile OTP |
| Permissions | Spatie Permission (permissions, not role names, in policies) |
| Audit | `spatie/laravel-activitylog` (use `logOnly([...])`, never `logAll()`) |
| Backup | `spatie/laravel-backup` (daily DB backup, weekly clean, notify on failure) |
| API docs | `dedoc/scramble` (OpenAPI at `/api-docs`) |
| Offline | `vite-plugin-pwa` (Workbox) plus IndexedDB via Dexie |
| SMS | Provider chosen in Settings (BD Bulk SMS driver today), sent through queued jobs |
| Payments | bKash, Nagad, SSLCommerz behind a `PaymentGateway` interface |
| Bot protection | Cloudflare Turnstile on guest checkout |
| Push | FCM only when credentials exist |
| Testing | Pest (feature, unit), Playwright for offline and PWA end to end |
| Quality | Laravel Pint, Larastan level 5 minimum, `flutter analyze` |
| Dev tooling for AI | Laravel Boost MCP (docs search, database query/schema, logs, URLs) |
| Containers | Docker Compose: nginx, php-fpm app, mysql, redis, queue worker, scheduler, reverb (local override adds mailpit, adminer, redisinsight) |
| Mobile | Flutter (Dart SDK ^3.13) with `http`, `sqflite`, `shared_preferences`, `connectivity_plus`, `intl`, `google_fonts`, `pdf` + `printing`, `cached_network_image`, `wakelock_plus`, `audioplayers`, `image_picker` |
| Build helpers | `Makefile` with `setup`, `web-serve`, `web-dev`, `web-test`, `web-lint`, `web-build`, `app-run`, `app-test`, `check` (everything CI runs) |

Rendering rule: public pages are Inertia SSR with dynamic meta and OpenGraph; the admin panel (`/admin`), POS, KDS and rider views are a client-side SPA with SSR disabled. No Filament.

Fonts (self-hosted, no CDN): Poppins for Latin headlines, Inter for Latin text, Noto Sans Bengali for Bangla.

---

## 3. Repository and folder structure

```text
dt-restora/
  CLAUDE.md                 project rules for agents (read first)
  Makefile                  shortcuts for both apps
  docs/                     shared specification (README.md lists the reading order)
    prd.md architecture.md api.md design.md roadmap.md docker.md
    flutter-parity-plan.md user-guide(.bn).md
    mobile/  production/  demo/  agent-learning-docs/  prompts/
  web-app/                  Laravel 13 backend, admin SPA, public site, /api/v1
  flutter-app/              Flutter client of the same API
  marketing-assets/         reels, screenshots, build tools
```

Rules: run commands from inside the app folder you are changing (`cd web-app` or `cd flutter-app`); the repository root has no application code. Do not create new base folders without approval (`AGENTS.md`).

### web-app (Laravel)

```text
app/
  Actions/          one class = one business operation (CreateOrderAction, PayOrderAction ...)
                    grouped by domain: Sales, Inventory, Finance, Accounting, Attendance, Payroll, Delivery, Demo
  Services/         external integrations and orchestration (SmsService, OtpService, StockService,
                    CostingService, LoyaltyService, CouponService, InvoiceNumberService, SeoBuilder,
                    PushNotificationService, TurnstileService, ReservationService, Metrics/,
                    Payments/, Aggregators/, Accounting/, Sms/, Sync/)
  DataTransferObjects/   only when an Action has 4+ inputs
  Enums/            OrderStatus, OrderType, OrderChannel, PayBasis ...
  Events/ Jobs/ Observers/ Providers/ Console/ Exceptions/ Support/ helpers.php
  Http/
    Controllers/
      Admin/        Inertia controllers for the SPA (POS and KDS included), one per area
      Api/V1/       JSON API: Auth, Staff, Customer, Rider, Payments, Sync, Accounting, Attendance
      Web/          public SSR pages
      Auth/ Payments/ Rider/
    Requests/       Form Requests: all validation lives here
    Resources/      API Resources
  Models/           relationships, scopes, casts, LogsActivity; no business logic
config/ database/(migrations, seeders, seeders/assets) lang/(en, bn) routes/(web, api, channels, console)
resources/
  css/app.css       Tailwind v4 tokens, fonts, motion, base cursor rules
  js/
    app.js ssr.js ziggy.js
    layouts/        AdminLayout, AuthLayout, PublicLayout
    pages/          admin/<area>/..., auth, errors, rider, web
    components/     ui-kit/ (shared kit), admin/, site/, demo/, AppIcon.vue, icons.js, ChannelBadge, QrCode
    lib/            cart, echo, format, i18n, notify, offline, print, pwa, receipt, invoice,
                    sounds, smsText, numericInput, safeDelete, units, ...
tests/ Feature/ Unit/ e2e/ (Playwright)
docker/ docker-compose.yml docker-compose.override.yml.example
```

One Admin controller and one `pages/admin/<area>/` folder per area (about 40 areas: dashboard, pos, orders, dues, kds, reservations, tables, menu, media, ingredients, suppliers, recipes, purchases, wastage, stock-counts, transfers, customers, loyalty, sms, coupons, staff, attendance, letters, delivery, aggregators, accounting, payroll, reports, expenses, branches, activity, sync, settings, profile, blog, careers).

### flutter-app

```text
lib/
  main.dart
  core/       api_client, app_state, config, local_db (sqflite), offline_orders, theme, sounds,
              units, pdf_fonts, pdf_shaper
  features/   one folder per module: accounting, aggregators, auth, backoffice, content, customers,
              dashboard, delivery, home, inventory, orders, pos, reports, settings, setup, shifts,
              splash, staff, sync
  ui/         shared widgets (widgets.dart, generic_view.dart, food_image.dart)
assets/ (brand, fonts)   test/
```

The Flutter app is a client of the web API, keeps native mobile patterns (not pixel copies of the web), supports offline orders, and follows `docs/flutter-parity-plan.md`.

### Naming conventions seen

- Controllers `<Area>Controller`, Actions `<Verb><Noun>Action`, Form Requests per action, Enums TitleCase keys, translation files per area (`lang/en/orders.php`), Vue pages `pages/admin/<area>/Index.vue`, shared components PascalCase, JS helpers camelCase in `lib/`.
- Feature tests named by area (`DashboardReportTest`, `BranchIsolationTest`, `ApiContractTest`).
- Money stored `decimal(12,2)`; API money as string `"1250.00"`; mobiles stored as `8801XXXXXXXXX`; dates ISO 8601, timezone Asia/Dhaka.

---

## 4. Code rules (backend)

1. Thin controllers: validate with a Form Request, call an Action, return a response (Inertia or JSON). Admin controllers and API controllers call the same Actions.
2. `declare(strict_types=1);`, typed parameters, return types and properties everywhere; constructor property promotion; curly braces always; TitleCase enum keys; PHPDoc array shapes; PHPDoc over inline comments.
3. Money: decimal, never float. Stock and payments inside `DB::transaction()` with `lockForUpdate()` when deducting stock.
4. Branch scoping: every branch-owned model uses a global scope; a non-owner must never read another branch's data and gets 404 (tested).
5. Every user-facing string is a translation key in `lang/en` and `lang/bn`; both must exist.
6. Secrets only in `.env`, read through `config()`; never `env()` outside config files.
7. Eager load relations; index foreign keys and common filters.
8. Logging with PSR-3 levels and context arrays; never log passwords, OTPs, tokens or card/wallet numbers.
9. One order pipeline: website, POS, API and aggregators all go through `CreateOrderAction`. One accounting pipeline: all postings through `PostJournalEntryAction` in the same transaction as the business event; entries balance, are immutable, corrected by reversal; locked periods reject postings.
10. Idempotency by key for order and payment creation (header `Idempotency-Key`, 24 h) and by `client_id` (ULID) for offline pushes.
11. SSL verification is never disabled. SMS is sent from queued jobs.
12. Do not change dependencies or create new base folders without approval. Do not create documentation files unless asked (the owner asks explicitly; this folder `docs/agent-learning-docs` was requested).
13. Before using a Laravel-ecosystem API, search the docs (Boost `search-docs`) and check installed versions. Prefer tests over tinker for verification.

### Authentication and OTP rules

- Login field accepts username, email or mobile (regex `^(\+?88)?01[3-9]\d{8}$`, normalised to `8801XXXXXXXXX`); throttle 5 per minute per login plus IP.
- OTP: `random_int(100000, 999999)`, stored hashed in Redis for 5 minutes, send limit 5 per hour by IP plus mobile, 5 wrong attempts invalidate the code, deleted after success.
- Staff use `users` + `staff` guard; customers use `customers` + `customer` guard; never mix; customers never get roles; customer-owned models use ownership policies (404 otherwise).

### Roles (restaurant product)

`super-admin` (vendor support), `owner`, `branch-manager`, `cashier`, `waiter`, `kitchen-manager`, `accountant`, `rider`. Permissions are named like `orders.void`, `discounts.override`, `stock.adjust`, `journals.post`. A root account is seeded from `.env`; a random password is printed once.

---

## 5. UI and design preferences

### Principles

Dense and fast; instant feedback (every action a toast); light and dark both first class; two density modes (compact for back office, touch for POS, KDS, rider, customer site); never rely on colour alone (pair with icon or text).

### Brand and colour tokens

- Datatech palette: Datatech Blue `#0B63CE` (primary), Midnight Navy `#0A1428`, Bright Sky `#38A8FF` (accent), Slate `#4A5568`, Soft Grey `#AFC0D6`, Cloud `#F5F7FA`.
- Defined once as CSS variables in `app.css` (`@theme`), used through token classes (`bg-primary`, `text-success`), never raw palette classes. Dark mode swaps the variables (`.dark`), `@custom-variant dark`.
- Semantic colours: success emerald (profit, paid, in stock), danger rose (loss, void, wastage, low stock), warning amber (pending only). Demo keeps the Datatech palette; a production client overrides colours through `BRAND_*` values in `.env` (`config/brand.php`) and `Brand::manifest()` for PWA icons.
- Brand mark rendered by `BrandMark.vue` and `BrandName.vue` ("DT" in text colour, "Restora" in blue).

### Typography

Poppins (headlines), Inter (body), Noto Sans Bengali (Bangla, slightly more line height `leading-relaxed`, smallest sizes grow about 1px). Body and table text 13px; page title `text-lg font-semibold`; dashboard value `text-2xl font-bold tracking-tight`; badges 11 to 12px. Currency `৳` prefix, Bangladesh grouping (12,34,567), 2 decimals in documents, compact form allowed on dashboards (lakh, crore), one shared formatter for digits (English or Bangla) and dates (`15 Oct 2026, 2:30 PM`, Asia/Dhaka).

### Size scale (all controls in a row share one height)

`xs` 24px/12px (row actions), `default` 28px/13px (almost everything), `lg` 36px/14px (customer site, mobile admin), `touch` 44 to 48px/15 to 16px (POS, KDS, rider). Radius 6px controls, 8px cards, 12px dialogs, nothing above 12px, no heavy shadows. Buttons: weight 500, no uppercase, one primary per region (primary, secondary, ghost, accent only on POS, destructive); states hover, pressed, 2px accent focus ring, disabled 50% with tooltip, loading spinner without width change. Page padding 16px (12px mobile), card gap 12px, top bar 48px, sidebar 220px (56px collapsed), nav item 32px.

### Components (one shared UI kit, built first)

`AppIcon, PageHeader, ConfirmDialog + useConfirm(), ReasonDialog, ApprovalDialog (manager PIN), TypedConfirmDialog, SlideOver, FormField, MoneyInput, MobileInput, QuantityStepper, Combobox, MultiSelect, DateRangePicker, FileUpload, ImageCropper, DataTable (filters, bulk actions, states), FilterBar, StatusBadge, KpiCard, ChartCard, EmptyState, ErrorState, SkeletonRows, MoneyText, DateText, NotificationBell, SyncStatusPill, CommandPalette, ThemeToggle, LanguageSwitch, ErrorPage, InfoButton/InfoModal, ModalStepper, UpdatePrompt`. A hidden `/admin/ui-kit` page renders every component in light, dark, `en`, `bn`. Never build one-off dialogs, tables or charts inside a page.

### Dialogs and confirmations

Never `alert`, `confirm`, `prompt`. Confirm dialog (small, centered, coloured icon circle, title is the question, message states the consequence, key facts shown, verb plus object button labels, focus on Cancel for destructive). Slide-over (right, 30 to 40% width) for create, edit and detail; bottom sheets on phones. Four confirmation levels: simple, reason (audit), approval (manager PIN), typed (type the name or `CONFIRM`). One promise-based `useConfirm()` composable. Unsaved-changes guard, session-timeout warning (POS uses PIN lock), blocking error dialog only when the user cannot continue. No dialog inside a dialog.

### Forms and validation

Server Form Request is the source of truth. Errors under fields (with icon, red border, `aria-describedby`), summary banner at 3 or more errors, toast only for outcomes, scroll and focus the first invalid field, never clear the form, validate on blur then on keystroke, spinner and double-submit protection, labels above inputs (never placeholders as labels), two-column grid max width 672px, 12px field spacing, required `*` marks, helper text, `Enter` submits, `Ctrl+Enter` saves long forms, sticky action bar and local draft auto-save for long forms, read-only fields show a lock icon.

### Tables

Alignment by data type: text, ids, dates and status left; numbers, money, percentages right with `tabular-nums`; icon columns centre; row actions last column right; money 2 decimals with currency in the header; negatives show minus and rose; zero or empty shows a muted en dash. Row height 32px, header 12px semibold sentence case, 1px horizontal borders only (no vertical lines, no zebra), hover highlight, totals row pinned bottom with a 2px top border, footer "Showing 1-25 of 312" plus page size, server-side pagination, 300ms debounced search, filter chips, column chooser, bulk-action bar, inline edit (`Enter` saves, `Esc` cancels), relative time with exact tooltip, skeleton rows, two kinds of empty state, card list on small screens.

### Dashboard, charts, reports

- Dashboard: sticky filter bar (presets, branch, channel, URL synced), KPI row (label, big value, change vs previous period with arrow icon and %, sparkline, click drills into the report), main charts (sales trend, channel donut, top items, profit breakdown), alerts panel, compact tables, independent skeleton loading, real-time updates via Reverb with a subtle highlight flash, setup checklist for a new install, role-specific views.
- Charts: Chart.js through `ChartCard`; token-based colours read at runtime; at most 6 series, donut at most 6 slices; bars start at zero; axes labelled with units; 400ms animation disabled under `prefers-reduced-motion`; loading, empty, error states; `aria-label` summary and "View as table"; export PNG and CSV; readable at 360px.
- Reports: identical layout (header with Export PDF, Excel, CSV and Print; filter bar with Apply and Reset; summary cards; optional chart; sortable table with pinned totals; drill-down slide-over). Exports match the screen exactly and add a header block. PDFs embed Noto Sans Bengali; Excel keeps real numeric cells and a frozen header. Heavy reports run as queued jobs with a notification. Print view for A4 and 80mm. Reports must equal dashboard numbers for the same filters.

### Navigation and layout

Sidebar grouped by module (Sales, Inventory, People, Delivery, Accounting, Reports, Settings), icons plus labels, collapsible, permission filtered. Top bar: branch switcher, global search, online and sync pill, notification bell, language switch (EN and Bangla), theme toggle, user menu. Page header: breadcrumbs, title, one primary action on the right. Command palette `Ctrl+K`. Tabs and filters live in the URL. Mobile: drawer sidebar, floating primary action, tables become card lists.

### POS, KDS, rider, customer site

- POS (touch): left 60% category tabs, autofocus search and item grid; right 40% cart, table or customer, discount, totals, large payment buttons (Cash, bKash, Nagad, Card, Split); cash change calculator with quick amounts (100, 500, 1000, Exact); usable on a 10 to 12 inch tablet and 1366x768. Shortcuts: `/` search, `F2` new order, `F4` pay, `F8` hold, `Esc` close; shown in a help dialog. Variant and add-on chooser opens when an item has sizes or add-ons.
- Receipt: 80mm thermal (about 48 monospace characters) plus PDF; fixed content order; bilingual; test Bangla on the real printer early.
- KDS: three columns Pending, Preparing, Ready with elapsed minutes, big action buttons, new-order sound.
- Rider PWA: single column, thumb-friendly status buttons, one tap call and navigation, cash on delivery amount prominent, location-sharing warning.
- Customer website: mobile first, large imagery, sticky cart, short checkout (address, payment, confirm), order tracking timeline with map, responsive WebP with lazy loading, one `<h1>`, `Restaurant` and `Menu` JSON-LD, default to system dark or light.

### Effects and motion

- Short, purposeful: 100ms hover and pressed response, 150ms fade plus scale for dialogs, 200ms transforms, 300ms page-in (`animate-page-in`, `site-page-in` keyframes), 400ms chart animation, 500ms scroll reveal on the public site (`reveal` with `--reveal-delay`), brief check animation on payment completion, subtle highlight flash for live-updated numbers, demo-only pop and glow for the demo guide button.
- `prefers-reduced-motion` collapses transitions to about 0.01ms everywhere.
- Skeletons for content, spinners only inside buttons, no full-page blockers.
- Cursor rules are global: anything clickable shows the hand, anything disabled shows not-allowed.
- Optimistic updates for low-risk actions with rollback and an error toast; undo toast (5 s) instead of confirmation for reversible actions.

### Toasts and notifications

`vue-sonner` via `notify.success|error|warning|info|promise`. Success 3 s, error until dismissed or 8 s, warning 6 s; icon plus short title, optional detail and one action (Undo, View order, Retry); at most 3 visible; top-right on desktop, top-center on mobile. A bell lists persistent alerts (low stock, wastage spike, new void request, sync conflicts, incoming online orders) with unread badge, real-time via Reverb.

### Accessibility and copy

Contrast 4.5:1 in both themes, visible focus rings, keyboard operable, `aria-label` on icon-only buttons, no horizontal page scroll. Microcopy: buttons are verbs ("Save order"), error formula "what happened plus what to do", never blame the user, one term per concept (glossary in `lang/en/glossary.php`), Bangla written naturally and reviewed, leave room for longer Bangla text.

### UI Definition of Done (every screen)

Uses UI-kit components only; loading, empty, error and no-permission states; correct confirmation level; validation under fields with focus and kept input; toast on success and failure; works light and dark, English and Bangla without overflow; keyboard usable; right at 1366x768, 1024 and 390px; filters, tabs and pagination in the URL; shared formatters; no emojis or symbol icons; compact size scale and table alignment standard followed.

---

## 6. Sound effects (web and Flutter)

- The web app synthesises tiny tones (no audio files in the repo): each sound is a list of `[frequency Hz, start s, length s, wave]` notes rendered to a 16-bit mono WAV at 12 kHz the first time it is needed, cached in `localStorage` (`dt-sound:`) and decoded once into memory so playback is instant. Implemented in `resources/js/lib/sounds.js`; Flutter has `lib/core/sounds.dart` (`audioplayers`).
- Every event has its own melody so staff tell them apart without looking: `notification` (880 then 1318), `success`, `error` (low square wave), `item_added` (single short blip), `order_created` (rising triad), `order_paid` (high sparkle), `order_held`, `order_cancelled` (falling), `online_order` (repeating two-tone alert), `qr_order`, `kds_new` (double beep then high), `kds_preparing`, `kds_ready` (rising), `kds_served`.
- Settings: enabled and volume (default 70) and a per-event switch are a **branch setting** (Settings, Branch POS). Defaults: all on except `success` and `item_added`. The last copy is mirrored in `localStorage` so the first sound after a page load obeys it. Each device can mute everything on its own (`dt-sound-muted`). Browser autoplay is respected: audio unlocks after the first tap or click.
- Design rule from `design.md`: a short beep on the KDS for a new order, volume and mute saved per device.
- Rule of thumb for new projects: synthesise short, distinct, non-annoying tones per business event, make them configurable per branch or workspace and mutable per device, and never block the UI on audio.

---

## 7. Settings structure

- Admin Settings page tabs: General, Payment methods, POS and billing, Invoice print (layouts classic, compact, bold, modern, detailed, minimal; paper 80mm, A5, A4; sample preview), SMS, Units, Delivery areas. Branch-level settings: opening hours, VAT, service charge, delivery zones, online ordering on or off, receipt footer, sound events and volume.
- Stored in a `settings` key-value table (some values encrypted, for example SMS provider credentials); owner-editable text per language for automatic SMS (`settings.sms_templates`) validated for known and required variables and length (GSM 7-bit 160/153, Unicode 70/67 parts), live counter in `lib/smsText.js` mirroring the PHP `SmsText`.
- Per-feature SMS switches (`SmsFeatures`: otp locked on, order_confirmed, invoice, delivery_status, reservation_confirmed, due_reminder, birthday, win_back, promotion, owner_daily_summary), with a demo mode that shows each SMS as a toast instead of sending (`SMS_DEMO`, ignored in production).
- Provider pattern: a registry entry (name, fields, driver) plus a driver class; the settings form is generated from the declared fields. The same registry idea is used for payment gateways and delivery aggregators.
- Environment switches: `IS_DEMO` (banner, daily reset, seeded sample data, demo guide), `BRAND_*` colours, Turnstile and FCM keys, map tile provider, Reverb keys. `.env` is never committed.
- Demo mode: seeds branches, staff, menu, customers, inventory, sales, operations and aggregator channels; shows a demo banner and a floating demo guide; rebuilds the database daily; production installs seed the real restaurant instead (`ProductionDataSeeder`, only on a fresh install).

---

## 8. API architecture

Conventions (same for every product):
- Base `/api/v1/...` in `routes/api.php`; Sanctum bearer tokens with abilities (`staff`, `rider`, `customer`); OpenAPI generated by Scramble at `/api-docs`; clients send `X-App-Version`, `X-Platform`, `Accept-Language` (`en` or `bn`).
- Success `{"success": true, "data": {...}, "message": "..."}`. Error `{"success": false, "error": {"code": "INSUFFICIENT_STOCK", "message": "...", "details": {...}}}` with matching HTTP status. Codes: `UNAUTHENTICATED` 401, `FORBIDDEN` 403, `NOT_FOUND` 404 (also for another branch), `VALIDATION_FAILED` 422, `INSUFFICIENT_STOCK`, `ORDER_ALREADY_PAID`, `SHIFT_NOT_OPEN` 409, `PAYMENT_AMOUNT_MISMATCH` 422, `TOO_MANY_REQUESTS` 429, `PAYMENT_GATEWAY_ERROR` 502, `SERVER_ERROR` 500.
- Money as decimal strings; dates ISO 8601 Asia/Dhaka; pagination `?page=1&per_page=25` with `meta`; filtering `?filter[status]=pending&sort=-created_at`; no breaking changes inside `v1` (add fields freely, never rename or remove).
- Idempotency-Key header on order and payment POSTs; rate limits strict on login and OTP, 60 per minute per token otherwise; every `/branches/{branch}/...` route checks branch access.
- Resource groups: auth (login, logout, me, google, otp), menu and orders (create, status, payments, void requests and approval), inventory (stock, wastage, recipes and cost, purchases, stock transfers), dashboard and reports (`json`, `pdf`, `xlsx`), staff and shifts, customer (orders, addresses, loyalty), rider (deliveries, location), accounting, attendance, sync (`bootstrap`, `changes`, `push`).
- Payments: never trust the browser or callback alone; confirm server to server with the gateway, update inside a transaction, make confirmation idempotent. Gateway callbacks and IPN are public web routes (CSRF-excluded), not API routes. bKash tokenized checkout (create, redirect, execute, query), Nagad same pattern with a manual TrxID fallback, SSLCommerz init plus IPN validation with `val_id`, amount and currency checks. Only `PaymentGateway` and `AggregatorChannel` get interfaces.
- Real-time on private Reverb channels authorised by branch permission: `branch.{id}.kitchen` (OrderCreated, OrderStatusChanged), `.tables`, `.alerts` (LowStock, WastageSpike), `.delivery`, and `owner.alerts`.
- The web admin, POS and website use Inertia controllers that call the same Actions as the API; never duplicate business logic.
- Mobile apps and integrations consume the API; the OpenAPI output plus `docs/api.md` are the contract; an API contract test (`ApiContractTest`, `ApiReferenceTest`) guards it.

---

## 9. Offline-first and sync rules

- Admin, POS and KDS are a PWA: service worker built by `vite-plugin-pwa`, served from `/sw.js` by a controller with `Cache-Control: no-store`, self-contained (no `importScripts`), registered only on `/admin`, `/rider`, `/login`. Opened screens cached (network first, 3 s timeout); a never-opened screen shows the precached `/offline` page; caches are emptied at sign out. "Update available" prompt, never reload mid-order.
- Reference data from `/api/v1/sync/bootstrap` and `/sync/changes` into IndexedDB (Dexie). Writes go to a local outbox with `client_id` (ULID), type, payload, timestamp, then `/sync/push`; the server is idempotent by `client_id`. UI writes locally first and shows the result immediately.
- Never queue gateway payments, SMS, aggregator calls or OTP verification ("needs internet", disabled with a tooltip, never hidden). No passwords or API secrets on the device; POS locks with a staff PIN after inactivity.
- Server is the source of truth; conflicts are recorded and shown on a Sync Conflicts screen, never silently dropped.
- Status pill in the top bar: Online, Offline, Syncing with a pending count; clock icon for unsynced orders, check for synced, warning for conflicts; toast "12 orders synced".
- Playwright tests go offline, take orders, reconnect and assert no duplicates or losses (`tests/e2e`). Flutter keeps offline orders in `sqflite` (`offline_orders.dart`).

---

## 10. Services (named in the code base)

`SmsService`, `OtpService`, `StockService`, `CostingService`, `LoyaltyService`, `CouponService`, `InvoiceNumberService`, `ReservationService`, `DueReminderService`, `SmsCampaignService`, `SmsPromotionService`, `PosSettings`, `PublicCache`, `SeoBuilder`, `BlogMarkdown`, `LetterheadPdf`, `PushNotificationService`, `TurnstileService`, `Metrics\MetricsService` (dashboard and branch comparison), `Payments\*` gateways, `Aggregators\*` adapters (Foodi, Foodpanda, HungryNaki, Pathao Food, Shohoz Food, Uber Eats), `Accounting\*` (ReportService, journal posting), `Sync\*`, `DemoJourney` services. Actions cover Sales (create, pay, void request and approve, shifts, due collection, revise settled sale, gateway payments), Inventory (receive purchase, transfers, stock counts, wastage, recipes), Finance, Accounting, Attendance, Payroll, Delivery.

Modules in the product spec (`prd.md`): multi-branch, owner dashboard, POS and payments, KDS, recipes and costing, inventory and purchasing, wastage, staff shifts and payroll, customer website and online ordering, loyalty and coupons, delivery and rider tracking, aggregator intake, accounting, offline mode, mobile API.

---

## 11. Testing and quality

- Pest feature tests for happy paths and main failures; authorization tested (allowed passes, other role 403, other branch 404); `BranchIsolationTest`; `NoEmojiTest` scans `app/ resources/ lang/ database/ docs/` for emoji and symbol ranges and runs in CI; `BrandPaletteTest`, `LocalizationTest`, `DemoModeTest`, `ApiContractTest`; Playwright specs for offline, PWA and a POS offline journey.
- Definition of Done for a backend feature: tests pass, `./vendor/bin/pint` clean, `./vendor/bin/phpstan` passes (Larastan level 5+), both languages added, sensitive actions log activity, `NoEmojiTest` passes, UI checklist passes, `migrate:fresh --seed` works, roadmap acceptance criteria met.
- Before finishing in this repo: web `./vendor/bin/pint`, `./vendor/bin/phpstan`, `php artisan test`; Flutter `flutter analyze` and `flutter test`; `make check` runs all.
- Prefer feature tests over scripts; do not create ad hoc verification scripts when tests cover the behaviour.

---

## 12. Roadmap and delivery method

Phases (build in order, one at a time, stop after tests): 0 Foundation (Docker, Inertia, tokens, localization, auth, roles, activity log, backup, admin layout, shared UI kit, no-emoji test, API docs), 1 Branches, menu, staff, 2 Inventory and recipes, 3 POS (cash) plus KDS, 4 Digital payments, 5 Owner dashboard and reports, 6 Customer website and online ordering, 7 Loyalty and coupons, 8 Delivery and rider tracking, 9 Aggregator integrations, 10 Accounting, 11 Full offline mode, 12 Mobile API readiness and polish. Each feature: migration, model, Form Request, Action, controller, Vue page, tests. Mark finished items `[x]`.

---

## 13. Docker, deploy and operations

Services in `docker-compose.yml`: `app` (PHP-FPM), `nginx`, `mysql`, `redis`, `queue` (`queue:work redis`), `scheduler` (`schedule:work`), `reverb` (port 8080), with shared logging config and healthchecks; local override adds mailpit and tools. Backups daily via the scheduler. Production notes in `docs/production/deployment.md`; `deploy.sh` exists in `web-app/`. Laravel Cloud is mentioned in the Boost guidelines (use the `deploying-to-cloud` skill only when asked to deploy there).

---

## 14. Working with the AI agent on code (process the owner expects)

1. Read `CLAUDE.md`, then `web-app/AGENTS.md`, then the docs in the order of `docs/README.md` before touching code.
2. Follow sibling files for structure and naming; check for an existing component or Action before writing a new one.
3. Use Laravel Boost tools when available: `search-docs` before ecosystem-dependent changes, `database-schema` before migrations, `database-query` for reads, `get-absolute-url` before sharing URLs, `browser-logs` for frontend errors. Activate domain skills (for example Tailwind) when working in that domain.
4. If a frontend change does not appear, run `npm run build` or ask the user to run `npm run dev` (Vite manifest errors).
5. Report honestly: what was done, what was verified, what was not.
6. When unclear or contradictory: ask, do not assume.

---

## 15. Starting a new product: checklist for an agent

1. Copy `architecture.md`, `design.md` and the conventions section of `api.md` unchanged into the new project's `docs/`.
2. Ask the owner for (or write with them) the new `prd.md` (vision, deployment model, roles, modules, data rules) and a phased `roadmap.md` with acceptance criteria.
3. Create the monorepo layout in section 3 (docs, backend app, mobile app, marketing-assets).
4. Phase 0 first: Docker, Laravel, Inertia, Vue, Tailwind tokens, fonts, localization (en and bn), auth, roles, activity log, backup, admin layout, the shared UI kit and its showcase page, `AppIcon`, `NoEmojiTest`, OpenAPI docs.
5. Add a `Makefile`, `CLAUDE.md` rules (run from the app folder, specification first, API contract, no emojis, bilingual text, quality commands) and an `AGENTS.md` for the stack.
6. Build one phase at a time with tests; stop and report at each phase.
7. Seed believable demo data early; use it for QA, screenshots and marketing.
8. Keep these docs and the agent-learning docs updated whenever a convention changes.

---

## 16. Quick decision table (defaults when the new spec is silent)

| Question | Default |
| --- | --- |
| Where does business logic go? | An Action class, called by both web and API controllers |
| Where does validation go? | A Form Request |
| Repository layer? | No |
| Float for money? | Never; decimal(12,2), strings in the API |
| Icons? | Font Awesome through `AppIcon`; no emojis |
| Charts library? | Chart.js through `ChartCard` only |
| Dialogs? | UI-kit dialogs; never browser alert, confirm, prompt |
| Delete or void? | Confirmation level by risk (simple, reason, approval, typed); log activity |
| Dates and numbers? | One shared formatter; Asia/Dhaka; Bangladesh digit grouping |
| Real-time? | Reverb private channels per branch |
| Offline? | PWA, IndexedDB outbox, idempotent `client_id`, server wins |
| Languages? | English and Bangla files for every key |
| Sounds? | Synthesised per-event tones, per-branch settings, per-device mute |
| Branding? | Tokens in CSS, per-deployment overrides in `.env` |
| Tests? | Pest feature tests plus authorization and branch isolation; Playwright for offline |
| Unclear spec? | Ask before assuming |

---

## 17. Demo data and marketing data practice (cross-link)

Seeded demo data must look real: multiple branches, hundreds of menu items with photos, recipes for every item, customers with points, dues, a live kitchen queue, reservations, SMS history, letters, stock transfers, a varied activity log, healthy profit and food cost. Screenshots and marketing videos are taken from a separate marketing database with the demo UI hidden. The procedure and pitfalls (including the stock-unit bug in the demo seed, the kitchen-status trap, and the public storage link) are in `marketing-reels-and-meta-ads-agent-playbook.md` and in `marketing-assets/november-2026/reel/REEL-WORKFLOW.md`.

---

## 18. Known gaps and inconsistencies to be aware of

- `architecture.md` says PHP 8.3+ and MySQL 8; the dev machine runs PHP 8.5 and SQLite locally; keep code compatible with the documented minimums.
- `architecture.md` says shadcn-vue (Radix Vue); `package.json` uses `reka-ui` (its successor). Treat the doc as intent and the package list as reality.
- The brand palette in `design.md` is the Datatech blue palette; `app.css` still carries a client brand comment (warm cream, espresso, gold) used for a customer deployment; production brand colours come from `.env`.
- The kitchen display page ran out of PHP memory on a database with about 33,000 orders at the default 128 MB; the stock-transfers page depends on a session branch an owner may not have set; the demo seeder stores litre-based stock in the wrong unit. Verify before relying on these screens in new work.
