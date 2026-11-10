# PRODUCT REQUIREMENTS: DT Restora

> **This is the only file you rewrite for each new product.** Keep the same section headings so the AI always finds things in the same place.

---

## 1. Vision

**DT Restora** is an "Owner Control System" for restaurants and cafés in Bangladesh. Standard POS systems stop at order entry and receipt printing. DT Restora gives the owner financial transparency (profit per branch, recipe costing, wastage analysis, inventory intelligence, full accounting) and also runs the customer-facing side: online ordering, loyalty, delivery tracking and delivery-app order intake.

**Company:** Datatech BD Ltd, Dhaka. **Market:** restaurants and cafés in Bangladesh. **Languages:** English and Bangla.

**Mobile apps:** native mobile apps (customer, rider, staff) will be built as a **separate project later**. This project does **not** contain a mobile app, but it must expose a complete, documented, versioned API that those apps can use (see module N).

---

## 2. Deployment Model _(decision)_

**One installation per client** (single-tenant): each restaurant group gets its own deployment and database, with multiple branches inside it. No multi-tenant SaaS in v1.

> Change this line only if you decide to build a shared SaaS; that changes the database design.

---

## 3. Roles

| Role              | Scope                       | Main needs                                                                                           |
| ----------------- | --------------------------- | ---------------------------------------------------------------------------------------------------- |
| `owner`           | All branches                | Dashboard, profit, branch comparison, approvals, all reports                                         |
| `branch-manager`  | Assigned branch             | Daily operations, stock requests, staff shifts, local expenses, discount approval, delivery dispatch |
| `cashier`         | Assigned branch             | POS, payments, split bills, cash closing                                                             |
| `waiter`          | Assigned branch             | Take orders by table, view own orders                                                                |
| `kitchen-manager` | Assigned branch             | Stock intake, recipes, wastage logging, KDS                                                          |
| `accountant`      | All branches (finance only) | Journals, vouchers, payables, financial statements                                                   |
| `rider`           | Assigned branch             | Sees assigned deliveries, updates status, shares live location                                       |

`super-admin` (Datatech support) exists in addition.

**Customers are not staff users.** They live in a separate `customers` table with their own auth guard (mobile OTP or Google). They can never reach `/admin`. Walk-in customers captured at the POS by mobile number are the same customer records, so loyalty works across every channel.

Permissions are seeded per role (e.g. `orders.void`, `discounts.override`, `stock.adjust`, `journals.post`).

---

## 4. Modules

### A. Multi-Branch

- Branch CRUD and a branch switcher.
- Role-based scoping: non-owners see only their branch.
- Inter-branch stock transfers (request → approve → receive).
- Branch-wise profit & loss.
- Per-branch settings: opening hours, VAT, service charge, delivery zones, online ordering on/off.

### B. Owner Dashboard

For today / week / month and per branch:

- Gross sales vs net sales (and by channel: dine-in, takeaway, website, delivery apps)
- Food cost vs ingredient consumption
- Salary allocation and operating expenses
- Wastage cost
- Aggregator commission cost
- Estimated net profit
- Alerts: wastage spike (configurable % over the 7-day average) and stock below reorder level

### C. POS & Payments

- Tables, order types (dine-in, takeaway, delivery), hold and resume orders.
- Menu items support **variants** (size) and **add-ons**, used by POS and website.
- Payment methods: **Cash** (change calculator), **bKash**, **Nagad**, **SSLCommerz** (card / online), and **split payments** across methods.
- Orders tagged with waiter, cashier, terminal, table, channel and branch.
- Kitchen Display System (KDS) updated in real time via Reverb.
- Receipt printing (80mm thermal) and reprint with audit log.

### D. Recipes, Inventory & Purchasing

- **Recipe builder:** menu item → ingredients with exact quantity in g / ml / pcs. Supports unit conversion (kg↔g, l↔ml).
- **Auto-deduction:** stock reduces when an order is confirmed (or served, configurable). Voided orders restore stock.
- **Costing engine:** food cost % per item using the latest (or weighted-average) purchase price.
- **Purchases:** suppliers, purchase orders, goods receipt, price history, reorder notifications.
- **Wastage logger:** spoiled / dropped / complimentary, with mandatory reason code and cost.
- **Stock count:** periodic physical count with variance report.

### E. Employees & Payroll Allocation

- Staff profiles (waiter, chef, cashier, manager, rider) with branch and monthly salary.
- Shift logs (clock in / out).
- Salary cost is allocated daily into branch net profit and posted to accounting. _(No payroll payment processing in v1.)_

### F. Notifications & SMS

- BdBulkSMS: order confirmation, OTP, delivery status, owner daily summary.
- Reverb: KDS, table status, low-stock alerts, customer and rider live updates.

### G. Reports

Sales summary, item-wise sales, channel-wise sales, payment-method report, cashier report, wastage, stock valuation, food cost, branch P&L, discount and void report, delivery performance, loyalty report. Export to PDF and Excel.

### H. Customer Ordering Website

Public, bilingual, mobile-first, rendered with **Inertia SSR** for SEO.

- Branch / area selection, menu with photos, search, categories, variants and add-ons.
- **QR table ordering:** scanning the table QR opens the menu for that table.
- Order types: delivery, pickup, dine-in (via QR). Scheduled (pre-order) orders.
- Delivery zones with fee per zone, minimum order amount, branch opening hours.
- Checkout as logged-in customer or guest verified by OTP.
- Payment: Cash on Delivery / pay at counter, bKash, Nagad, SSLCommerz.
- Customer account: addresses, order history, reorder, loyalty balance, coupons.
- Live order tracking page (status timeline, rider location when out for delivery).
- SEO: meta + OpenGraph tags, `Restaurant` / `Menu` JSON-LD, sitemap, fast loading.
- Abuse protection: OTP rate limits and Cloudflare Turnstile on guest checkout.
- Privacy policy and terms pages.

### I. Loyalty & Coupons

- Points earned per order amount (default 1 point per ৳100, configurable); redemption value configurable; points expiry; optional tiers (e.g. Silver / Gold) with extra earn rate.
- Immutable **points ledger** (earned, redeemed, expired, adjusted) per customer.
- Redeem at POS (lookup by mobile + OTP, or manager override) and on the website.
- **Coupons:** code, percent or fixed, minimum order, validity dates, total and per-customer usage limits, applicable channel and branch.
- Simple SMS campaigns: birthday offer, win-back for inactive customers.

### J. Delivery & Rider Tracking

- Delivery orders with address, map pin, zone and fee.
- Rider profiles; manager assigns a rider (system suggests free riders).
- Statuses: `placed → accepted → preparing → ready → picked_up → on_the_way → delivered` (plus `failed` / `cancelled` with reason).
- **Rider web view** (mobile-friendly PWA page, not a native app): assigned orders, status buttons, navigation link, shares live GPS while the page is open.
- Customer live tracking through Reverb; tracking link works without login using a signed token.
- Optional delivery OTP at handover as proof of delivery.
- Cash-on-delivery reconciliation per rider and rider performance report (delivery time, count, failures).
- Map tiles provider set in `.env` (Leaflet + an OpenStreetMap-compatible provider by default; Google Maps possible if the client pays for it).

### K. Aggregator Integrations

- Channel adapters for delivery apps (e.g. Foodpanda, Pathao Food). Each adapter implements one `AggregatorChannel` contract: receive order (webhook or polling) → map to internal order tagged with the channel → accept / reject → push status updates back → sync menu availability.
- Per-channel price list and **commission %**, so reports show profit after commission.
- Payout reconciliation per channel (expected vs received).
- A built-in **"manual aggregator order"** entry screen works for every channel from day one.
- Real API adapters are built only for platforms where the client has partner credentials and documentation. Datatech must obtain these from each platform.

### L. Accounting

- **Double-entry** ledger with a seeded restaurant chart of accounts (assets, liabilities, equity, income, expense, COGS), editable.
- Cash, bank and mobile-wallet accounts (bKash, Nagad, SSLCommerz settlement).
- **Automatic postings** for sales, VAT payable, service charge, payments by method, purchases and supplier payables, expenses, wastage, salary allocation, aggregator commission, refunds.
- Expense and payment vouchers with attachments, supplier payables and payments, customer receivables (corporate accounts) if enabled.
- Manual journal entries with approval; posted entries are never edited, only reversed.
- Fiscal years and period locking.
- Reports: trial balance, general ledger, profit & loss, balance sheet, cash flow, VAT summary, payables aging. Export PDF / Excel.
- Basic reconciliation of wallet / bank statements (manual matching).
- Not included: NBR e-filing or tax return submission, automated bank feeds.

### M. Full Offline Mode

The admin SPA, POS and KDS work as an installable **PWA** and keep working without internet.

- Menu, prices, tables, staff, customers (loyalty lookup) and settings are cached locally; the app opens and runs with no connection.
- Orders, cash payments, discounts, shift open / close, wastage logs, stock counts and attendance are saved in a local queue (IndexedDB) and synced automatically when the connection returns.
- **Online-only by nature:** bKash, Nagad, SSLCommerz payments, aggregator and website order intake, SMS, loyalty OTP verification. When offline these show a clear "needs internet" message, and the cashier can use Cash or record a manual wallet payment (TrxID) for later verification.
- A visible online / offline / syncing indicator, a pending-sync counter, and a **Sync Conflicts** screen for items the server could not accept.
- Offline orders print receipts locally and use a per-terminal invoice series (see business rules).
- Technical rules are in `architecture.md`.

### N. Mobile-Ready API (no mobile app in this project)

- Everything the future customer, rider and staff apps need is available through `/api/v1` (see `api.md`): auth, menu, orders, tracking, loyalty, addresses, rider operations, push token registration.
- Auto-generated OpenAPI documentation, stable versioning (no breaking changes inside `v1`), consistent errors and pagination, and Postman / OpenAPI export for the mobile team.
- Push notifications: store device tokens and send through FCM when credentials are configured.

---

## 5. Business Rules

| Topic                 | Rule                                                                                                                                                                                                                                                  |
| --------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **VAT**               | Configurable per branch (percentage, inclusive or exclusive). Default 15%. Confirm the correct rate and receipt requirements with your accountant.                                                                                                    |
| **Service charge**    | Configurable percentage, applied before VAT, can be turned off per order with permission.                                                                                                                                                             |
| **Discounts**         | Percent or fixed. Above the configured limit (default 10%) needs manager approval. All overrides are activity-logged. Coupons and loyalty redemption count as discounts.                                                                              |
| **Void / refund**     | Cashier requests, manager approves, reason required, stock restored, loyalty points reversed, accounting reversed, activity-logged. Paid orders create a refund record, never a deletion.                                                             |
| **Rounding**          | Grand total rounded to the nearest ৳1; the difference is shown as "Rounding" on the receipt.                                                                                                                                                          |
| **Cash closing**      | Each cashier opens a shift with opening cash and closes it with counted cash. System shows expected vs counted and the difference.                                                                                                                    |
| **Invoice numbers**   | Unique per branch without any shared counter: branch, terminal, date, time and a 4-character random code, for example `BR01-T02-20260101-143205-K7Q4`. A terminal makes the number itself, online or offline, so parallel offline terminals never clash; the server keeps the number the device printed and only issues a new one (reported back to the device) if another sale already has it. Website orders use terminal code `WEB`. A branch can instead choose a counter pattern with `{SEQ}` (`BR01-T02-20260101-0001`, sequential per branch, terminal and day). Confirm NBR numbering requirements with your accountant. |
| **Stock**             | Stock cannot go negative unless the branch setting "allow negative stock" is on. Offline syncs that cause negative stock are accepted and flagged for review.                                                                                         |
| **Price changes**     | Menu price changes never alter past orders (order items store a price snapshot).                                                                                                                                                                      |
| **Loyalty**           | Points are earned only on paid orders, on the amount after discount, excluding VAT, service and delivery fee. Points cannot pay for delivery fee or VAT.                                                                                              |
| **Delivery**          | Fee comes from the delivery zone; orders outside all zones are rejected; minimum order per zone is enforced on website and POS.                                                                                                                       |
| **Aggregator orders** | Stored with the channel's order ID (unique per channel, so duplicates are ignored). Commission is recorded per order.                                                                                                                                 |
| **Accounting**        | Every journal entry must balance (debits = credits). Posting happens inside the same DB transaction as the business event. Locked periods reject new postings.                                                                                        |
| **Offline sync**      | The server is the source of truth. Every queued item has a client-generated ID so a retry never creates a duplicate. The server accepts valid offline orders even if prices changed since, using the price snapshot taken on the device.              |
| **Customer data**     | Collect only what is needed (name, mobile, addresses). Customers can request deletion; order history is anonymized, not deleted.                                                                                                                      |

---

## 6. Hardware

80mm thermal printer (ESC/POS or browser print), cash drawer (opens via printer), barcode scanner (keyboard-wedge, acts as typing), tablet or touch monitor. Kitchen screen or kitchen printer for KDS.

---

## 7. Non-Functional Requirements

- **Localization:** all UI, validation, toasts, SMS and receipts in `en` and `bn`.
- **Performance:** POS actions respond in under 500ms on normal broadband (instant when offline); dashboard loads in under 2s with 1 year of data; public pages reach LCP under 2.5s on a mid-range phone and Lighthouse SEO of 90 or more.
- **Audit:** activity log for voids, discount overrides, stock adjustments, price changes, receipt reprints, role changes, manual journals, coupon and loyalty adjustments.
- **Backup:** daily automated database backup; restore procedure documented.
- **Security:** HTTPS only, hashed passwords, rate-limited login and OTP, payment callbacks and aggregator webhooks verified server-side, signed tracking links, customer data isolation (a customer can only read their own orders).
- **Browsers / devices:** latest Chrome and Edge, Android tablets and phones (Chrome), iOS Safari for the customer website.

---

## 8. Screen List (per role)

| Role               | Screens                                                                                                                                             |
| ------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| owner              | Dashboard, branch comparison, reports, branches, users & roles, settings, activity log, approvals, loyalty and coupon settings, aggregator settings |
| branch-manager     | Branch dashboard, orders, menu, staff & shifts, expenses, stock requests, transfers, approvals, delivery dispatch board, sync conflicts             |
| cashier            | POS, open orders, payments, shift open/close, order history (own), loyalty lookup                                                                   |
| waiter             | Table view, order entry, my orders                                                                                                                  |
| kitchen-manager    | KDS, ingredients, recipes, purchases, wastage, stock count                                                                                          |
| accountant         | Chart of accounts, journals, vouchers, payables, bank / wallet reconciliation, financial statements, period lock                                    |
| rider              | My deliveries, delivery detail, location sharing, COD summary                                                                                       |
| customer (website) | Home, branch / menu, item detail, cart, checkout, order tracking, account (orders, addresses, loyalty, coupons)                                     |

---

## 9. Out of Scope

- **Native mobile apps** (customer, rider, staff). To be built separately later, using the API from module N.
- Multi-tenant SaaS.
- Payroll payment processing.
- NBR e-filing / tax return submission and automated bank feeds.
- Aggregator adapters for platforms without partner API access.

---

## 10. Key User Stories & Acceptance Criteria

1. **Split bill.** _As a cashier, I split one bill between cash and bKash._
    - Done when: the order can be paid with two or more methods in 5 taps or fewer, payments equal the grand total, and the receipt lists each method.
2. **Stock deduction.** _As an owner, I want stock to reduce automatically when food is sold._
    - Done when: confirming an order reduces each recipe ingredient in the branch; voiding restores it; concurrent sales keep stock consistent.
3. **Wastage.** _As a kitchen manager, I log wastage._
    - Done when: reason code is mandatory, cost is calculated from current ingredient price, and it appears in the wastage report, profit calculation and accounting.
4. **Branch isolation.** _As a branch manager, I only see my branch._
    - Done when: requesting another branch's data returns 404 on web and API, verified by tests.
5. **Owner profit.** _As an owner, I see today's profit per branch._
    - Done when: Net Profit = Net Sales − Food Cost − Wastage − Allocated Salary − Operating Expenses − Aggregator Commission, and the dashboard matches the report totals exactly.
6. **Void approval.** _As a cashier, I void an order._
    - Done when: a manager approves, a reason is stored, stock, points and journals are reversed, and an activity log entry exists.
7. **Online order.** _As a customer, I order delivery from the website._
    - Done when: I can pick items, address and payment, receive an SMS confirmation, see live status, and the order appears on the KDS and dispatch board within 3 seconds.
8. **Loyalty.** _As a customer, I earn and redeem points._
    - Done when: points post after payment, the ledger balance equals the sum of entries, redemption reduces the bill, and a refund reverses the points.
9. **Delivery tracking.** _As a customer, I see my rider on a map._
    - Done when: rider location updates appear at least every 10 seconds while the rider page is open, and tracking stops after delivery.
10. **Aggregator order.** _As a manager, I receive a delivery-app order in the POS._
    - Done when: the order appears with its channel tag, a duplicate webhook creates no second order, and commission shows in reports.
11. **Accounting.** _As an accountant, I get correct statements._
    - Done when: every journal balances, trial balance totals match, P&L net profit matches the owner dashboard, and locked periods reject postings.
12. **Offline.** _As a cashier, I keep selling when the internet is down._
    - Done when: with the network off I can open the POS, take cash orders and print receipts; on reconnect they sync without duplicates, and conflicts appear in the Sync Conflicts screen.

---

## 11. Phasing

See `roadmap.md`. Build phase by phase; do not implement later phases early.
