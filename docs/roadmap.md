# ROADMAP: DT Restora

> Rewrite per product. The AI builds **one phase at a time**, runs tests, and stops. Each phase must satisfy the Definition of Done in `architecture.md`.

Mark finished phases with `[x]`.

---

## Phase 0: Foundation

- [ ] Docker Compose (nginx, php-fpm, mysql, redis, queue, scheduler, reverb), `.env.example`
- [ ] Inertia + Vue 3 + Tailwind v4 tokens (`design.md`), Sonner, dark mode, SSR on public pages only
- [ ] Localization middleware, `en` / `bn` files, language switcher
- [ ] Staff auth (username / email / mobile), Google login, OTP service, rate limits
- [ ] Spatie Permission with the roles in `prd.md`, seeders, root account from `.env`
- [ ] Activity log and backup configured
- [ ] Admin layout: sidebar, topbar, branch switcher, notification bell, command palette
- [ ] **Shared UI kit** from `design.md` section 22: `ConfirmDialog` + `useConfirm()` (simple, reason, approval, typed levels), `SlideOver`, `FormField` and inputs, `DataTable` with filters and bulk actions, `FilterBar`, `KpiCard`, `ChartCard`, `StatusBadge`, `EmptyState`, `ErrorPage`, toast wrapper
- [ ] `AppIcon` (Font Awesome) and `NoEmojiTest` in CI
- [ ] Compact control sizes (`xs`, `default`, `lg`, `touch`) and table alignment standard implemented in the shared components
- [ ] A hidden `/admin/ui-kit` showcase page that renders every component in light, dark, `en` and `bn`
- [ ] OpenAPI docs generator installed and serving at `/api-docs`

**Acceptance:** `migrate:fresh --seed` works; each role logs in and sees only its menu; unauthorized access returns 403; the UI-kit showcase page renders every component without errors; a form with server validation errors shows messages under fields and focuses the first one; tests pass.

## Phase 1: Branches, Menu, Staff

- [ ] Branch CRUD, branch scoping global scope, per-branch settings (hours, VAT, service charge)
- [ ] Menu categories and items (bilingual names, photos), variants and add-ons
- [ ] Employee profiles and salary settings

**Acceptance:** a branch manager can never read another branch's data (tested); menu items are searchable and sortable; variants and add-ons price correctly.

## Phase 2: Inventory & Recipes

- [ ] Ingredients, units and conversions, suppliers
- [ ] Purchases and goods receipt, price history
- [ ] Recipe builder and costing engine
- [ ] Wastage logger with mandatory reason codes
- [ ] Stock count with variance report
- [ ] Inter-branch stock transfers

**Acceptance:** recipe cost updates after a purchase price change; wastage cost is calculated server-side; stock never goes negative unless allowed; transfers move stock exactly once.

## Phase 3: POS (Cash only) + KDS

- [ ] Tables, order types, hold/resume
- [ ] Order creation action, stock deduction in transaction, invoice numbering per branch and terminal
- [ ] Cash payment with change calculator, shifts open/close
- [ ] 80mm receipt print (bilingual), reprint with log
- [ ] KDS via Reverb
- [ ] Void request and manager approval, discount rules, VAT and service charge
- [ ] Customer records captured by mobile at POS

**Acceptance:** a cashier completes a cash order end to end in under 10 taps; voiding restores stock; concurrent orders keep stock consistent (tested).

## Phase 4: Digital Payments

- [ ] bKash tokenized checkout (create → execute → query)
- [ ] SSLCommerz init + IPN with validation API
- [ ] Nagad (or manual-verification fallback)
- [ ] Split payments, idempotency keys

**Acceptance:** duplicate callbacks never double-pay; payment amount mismatch is rejected; split bill works in 5 taps or fewer.

## Phase 5: Owner Dashboard & Reports

- [ ] Dashboard: gross/net sales, food cost, wastage, salary, expenses, net profit, per branch and per channel
- [ ] Alerts: wastage spike, low stock
- [ ] Reports with PDF and Excel export, branch P&L, branch comparison, using the report layout in `design.md` section 17 (filter bar, summary cards, pinned totals, drill-down, queued heavy exports)
- [ ] Dashboard charts per `design.md` section 16 with loading, empty and error states and a "view as table" toggle

**Acceptance:** dashboard totals equal report totals exactly; dashboard loads in under 2s on seeded one-year data.

## Phase 6: Customer Website & Online Ordering

- [ ] Customer auth (mobile OTP, Google) with separate guard and `customers` table
- [ ] Public SSR pages: home, branch, menu, item, with meta, OpenGraph, JSON-LD, sitemap
- [ ] Cart, checkout (guest with OTP or logged in), delivery zones and fees, minimum order, scheduled orders
- [ ] QR table ordering
- [ ] Online payments (reuse Phase 4 gateways) and Cash on Delivery
- [ ] Customer account: addresses, order history, reorder
- [ ] BdBulkSMS order confirmation and status SMS (queued), owner daily summary
- [ ] Turnstile on guest checkout, privacy and terms pages

**Acceptance:** an online order reaches the KDS within 3 seconds; a customer can only read their own orders (tested); Lighthouse SEO 90 or more on public pages; guest checkout is rate limited.

## Phase 7: Loyalty & Coupons

- [ ] Points ledger, earn and redeem rules, expiry job, optional tiers
- [ ] Redemption at POS and website
- [ ] Coupon engine with limits and channel / branch rules
- [ ] SMS campaigns (birthday, win-back)
- [ ] Loyalty report

**Acceptance:** ledger balance always equals the sum of entries; refunds reverse points; coupon usage limits cannot be bypassed by concurrent requests (tested).

## Phase 8: Delivery & Rider Tracking

- [ ] Delivery orders, rider profiles, dispatch board with assignment
- [ ] Rider web view (PWA) with status buttons and GPS sharing
- [ ] Live customer tracking via Reverb, signed tracking links, optional delivery OTP
- [ ] COD reconciliation and delivery performance report

**Acceptance:** rider location reaches the customer within 10 seconds; a rider sees only their assigned orders; tracking links expire after delivery.

## Phase 9: Aggregator Integrations

- [ ] `AggregatorChannel` contract, channel and commission settings, per-channel price lists
- [ ] Manual aggregator order entry screen
- [ ] Webhook / polling intake with signature verification and duplicate protection
- [ ] Status push-back and menu availability sync
- [ ] Payout reconciliation
- [ ] Real adapters for platforms where credentials and documentation are available

**Acceptance:** duplicate webhooks create one order; invalid signatures are rejected; commission appears in profit reports.

## Phase 10: Accounting

- [ ] Chart of accounts (seeded), cash / bank / wallet accounts, fiscal years and period lock
- [ ] `PostJournalEntryAction` and automatic postings from sales, payments, purchases, expenses, wastage, salary, commission, refunds
- [ ] Vouchers, payables, manual journals with approval, reversals
- [ ] Wallet / bank reconciliation
- [ ] Trial balance, ledger, P&L, balance sheet, cash flow, VAT summary, payables aging

**Acceptance:** every journal balances; trial balance totals match; P&L net profit matches the owner dashboard; locked periods reject postings (all tested).

## Phase 11: Full Offline Mode

- [ ] PWA: installable, service worker, versioned cache of app shell and reference data
- [ ] IndexedDB queue with client-generated IDs, background sync and retry
- [ ] Sync endpoints, conflict detection, Sync Conflicts screen
- [ ] Offline receipt printing, per-terminal invoice series, offline shift close
- [ ] Online / offline / syncing indicator and pending counter
- [ ] Clear "needs internet" handling for gateway payments, aggregator, SMS

**Acceptance:** with the network off, the POS opens, takes cash orders and prints receipts; on reconnect orders sync with no duplicates and no lost items; killing the browser mid-queue loses nothing (all tested, including a Playwright offline test).

## Phase 12: Mobile API Readiness & Polish

- [ ] Review every `/api/v1` endpoint used by customer, rider and staff apps; fill gaps
- [ ] Push token registration and FCM sending (when configured)
- [ ] OpenAPI documentation complete, example requests, Postman export
- [ ] API contract tests so v1 cannot break silently
- [ ] Activity log viewer, settings screens, empty/error state review, accessibility pass
- [ ] User guide (English and Bangla) and deployment guide

**Acceptance:** full test suite green, Pint and Larastan clean, a mobile developer can build against the OpenAPI docs without asking the backend team questions, and a fresh server can be deployed from the guide.

---

## Backlog (not in this project)

Native mobile apps (separate project), multi-tenant SaaS, payroll payment processing, NBR e-filing, automated bank feeds.
