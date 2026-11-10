# Flutter app parity plan

Plan to make `flutter-app/` able to do everything the `web-app/` admin panel can do, in a standard, testable way.

| | |
| --- | --- |
| Status | Draft for review |
| Written | 2026-11-10, against `main` at the time of writing |
| Applies to | `flutter-app/` (client) and `web-app/` (API work it depends on) |
| Companion docs | [prd.md](prd.md), [api.md](api.md), [design.md](design.md), [mobile/README.md](mobile/README.md), [roadmap.md](roadmap.md) |

Rules in force while doing this work: the PRD wins on business rules, `design.md` on look and feel, `api.md` plus the OpenAPI output on the contract. Every endpoint change updates the Flutter client and the docs in the same change. No emojis or symbol icons anywhere. Every user-facing text exists in English and Bangla.

---

## 1. Goal and scope

**Goal.** A person with the right permission can run the whole restaurant from the phone or tablet app: sell, kitchen, stock, people, money, website content and every setting that the web admin panel offers, without opening the web panel.

**Three audiences, one app.**

| Audience | Web today | Flutter today | In this plan |
| --- | --- | --- | --- |
| Staff and owner (admin panel) | About 40 admin areas, 195 admin routes | 31 modules with many operations missing (section 4) | Yes, main body of work |
| Rider | Rider page | "My deliveries" screen | Yes, close the gaps (OTP, live location) |
| Customer (website ordering, account, tracking) | Public site, 19 customer API routes | Not present | Yes, as the last phase; the API already exists |

**Out of scope.** Search-engine pages of the public site (SEO, SSR, blog reading pages), the demo-site banner and journey tracking, the `/admin/ui-kit` showcase, server operations (backups, deployment).

**Not the goal.** Pixel copies of the web screens. The app keeps native mobile patterns; the web panel stays the better place for bulk work (large tables, CSV export, long forms). Where a web screen is mostly a wide table, the mobile version is a list with filters and a detail page.

---

## 2. How the comparison was made, and its limits

- **Web side:** all admin routes (`php artisan route:list`), all `/api/v1` routes, `resources/js/pages/admin/*`, the PRD and the work done in this repository up to the date above (units, SMS module, invoice print, branch-wise settings, due sales, order edit and delete flags, bill review).
- **Flutter side:** every file under `flutter-app/lib` (41 files, about 12,800 lines): the module list in `home_screen.dart`, every `api.get/post/put/patch/delete` call, the payment sheet, receipt printing, POS settings, offline queue and tests.
- **Not done: running the app in an emulator.** The tooling available while this plan was written could not do it: the Flutter SDK could not write to its own cache, there is no Android SDK or `adb`, and the iOS Simulator needs a full Xcode install that is not set up. **Every statement about Flutter below comes from reading the code, not from using the app.** Phase 0 therefore starts with a hands-on audit on a real emulator, and any row in the matrix may change after it. To run it yourself:

```sh
cd web-app && php artisan serve --host=0.0.0.0          # backend on :8000
cd flutter-app && flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1   # Android emulator
flutter run --dart-define=API_BASE_URL=http://localhost:8000/api/v1  # iOS simulator
```

---

## 3. Where we are today

| Measure | web-app | flutter-app |
| --- | --- | --- |
| Admin operations (routes) | 195 | reached only through the API |
| API v1 routes | 138 | used through about 40 explicit write calls plus shared list and form helpers |
| Modules in navigation | 40 areas | 31 (drawer) plus Settings and Sync |
| Languages in the interface | English and Bangla (`lang/en`, `lang/bn`) | English only; Bangla only for data names (`name_bn`) |
| Realtime | Reverb (kitchen, new orders, delivery) | none; screens refresh by pull or timer |
| Push notifications | none for staff (bell and sounds in browser) | none (no Firebase) |
| Offline | PWA shell, offline order queue in POS | SQLite cache plus outbox for orders, shifts and attendance |
| Printing | 6 invoice styles, 4 paper sizes, kitchen token | 80 mm receipt, 58 mm token (fixed layout) |
| Automated tests | 1,046 PHP tests | 3 Dart test files |
| Large single files | POS page is one large component | `pos_screen.dart` 2,023 lines, `inventory_screens.dart` 1,086 lines |

Strengths worth keeping: working offline order queue with idempotency keys, PDF receipts with Bangla shaping, role and permission aware navigation, theme and brand colour switching, a generic record form and list view that already power several modules.

---

## 4. Parity matrix

Status key. **Full**: the app does what the web does. **Partial**: usable but missing important operations. **Missing**: not in the app. **API**: the operation exists only as a web (Inertia) route today, so the API needs work first.

### 4.1 Selling

| Module | Web-app capability | Flutter today | Status | Main gaps | Epic |
| --- | --- | --- | --- | --- | --- |
| Login and session | Username, email or mobile; Google; OTP; profile page with photo and password change | Username, email or mobile and password; sign out | Partial | Google sign-in, OTP sign-in, profile (name, photo, password), "remember me", PIN lock | E1 |
| POS: build the order | Menu with search and item number, variants, add-ons, hold and resume, customer suggestions, table pick, order types, bill discount, coupon, loyalty redeem, scheduled orders, stock warnings from recipes | Menu, variants, add-ons, hold and resume, table, customer by mobile, discount, offline queue | Partial | Coupon, loyalty redeem, scheduled orders, stock warnings, customer suggestions by name, item number entry, reservation link | E2 |
| POS: save and pay | Send to kitchen, quick save, confirm payment, due sale, bill review modal, instant print per action, per-branch button settings, order edit and delete flags | Send, pay with split lines and quick cash, edit order, void request | Partial | Due sale, bill review, per-action print and clear behaviour from branch settings, "sell without stock", edit and delete flags | E2 |
| Payments | Cash, split, single card or bank, manual wallet with approval, gateway (bKash, SSLCommerz), payment methods managed in Settings | Split lines with methods from the branch, reference for bKash and Nagad | Partial | Gateway checkout, manual payment approval, methods list from Settings | E2 |
| Orders | List and filters, detail, status changes, edit, void request and approval, delete in one step, reprint with log, kitchen token | List, detail, status, edit, pay, void request and approve, receipt and token | Partial | Delete in one step with reason, reprint log, filters (date, channel, status, search), pagination | E2 |
| Dues | Due list, collect partial payments, balance, customer | None | Missing, API | Whole module | E2 |
| Cash shifts | Open, close, expected cash, difference | Open and close | Full | Shift report and cash movements if the web adds them | E2 |
| Kitchen display | Tickets by stage with timers, realtime | Tickets by stage with timers | Partial | Realtime updates, sound on new ticket, per-station filters if added | E3 |
| Tables | Floor list, create, edit, delete, QR code per table, regenerate, release, table QR menu | Read-only grid | Partial, API | Create and edit, QR view and share, release table | E3 |
| Reservations | Create, edit, delete, status actions, deposit hold and outcomes, table assignment | Create, status change | Partial | Edit, delete, deposit flows, table assignment | E3 |
| Delivery dispatch | Assign rider, COD reconcile, zones | Assign rider | Partial | COD reconcile, delivery zones and fees (Settings) | E3 |

### 4.2 Stock and purchasing

| Module | Web-app capability | Flutter today | Status | Main gaps | Epic |
| --- | --- | --- | --- | --- | --- |
| Ingredients | CRUD, categories, reorder level, units, low-stock alerts, branch stock | Create and edit, stock list | Partial, API | Categories (CRUD and filter), delete, low-stock view with units, show stock in the ingredient's unit | E4 |
| Units and conversions | Settings tab: fixed standard units, custom units (box, large box), relations, lock when in use | Ingredient unit only | Missing, API | Units manager; unit picker with size hint on every quantity line; `units` reference with type and factor | E4 |
| Purchases | Create, edit, safe correct after receive, delete, receive, per-line unit and supplier, create ingredient or supplier inline, price history | Create (ingredient unit only) | Partial, API | Edit, correct, delete, receive later, per-line unit and conversion hint, inline create | E4 |
| Suppliers | CRUD | CRUD | Full | None known | E4 |
| Recipes | Builder with per-line unit (g, kg, mg, ml, l, tsp ...), live cost and margin, profit percent | Builder in base unit, cost | Partial | Per-line unit, live cost, margin and profit percent | E4 |
| Wastage | Log with reason codes, approve, delete | Log | Partial | Reason codes list, approval, delete, unit picker | E4 |
| Stock count | Start, count items, close with variance, delete | Count entry | Partial | Close with variance report, delete, history | E4 |
| Stock transfers | Create, approve, receive, delete | Create, approve, receive | Partial | Delete, unit picker | E4 |
| Menu management | Categories CRUD, items with variants and add-ons, photos, availability, branch scope, safe delete | Item create and edit | Partial, API | Categories CRUD, variants and add-ons editor, photo upload, delete with safe-delete rules | E5 |

### 4.3 People and customers

| Module | Web-app capability | Flutter today | Status | Main gaps | Epic |
| --- | --- | --- | --- | --- | --- |
| Customers | List, detail, orders, loyalty, edit | List, loyalty adjust | Partial, API | Detail with order history, edit, search by mobile | E6 |
| Loyalty | Settings, adjust, redeem, expiry | Settings, adjust | Partial | Redeem from POS (see E2), ledger view | E6 |
| Coupons | CRUD with rules | Create, edit, validate | Partial | Delete, usage count, validity dates | E6 |
| Staff | Employees and system users, roles, branch, delete | Employee create | Partial, API | Edit, delete, system users and role assignment | E6 |
| Attendance | Records, devices, tokens, manual entry | Punch in and out | Partial | Devices and tokens, manual entry, history by date | E6 |
| Payroll | Monthly payroll, advances | View | Partial | Advances, run and lock payroll | E6 |
| Letters | Templates and PDF | List and PDF | Partial | Edit, delete if absent | E6 |

### 4.4 Money and reports

| Module | Web-app capability | Flutter today | Status | Main gaps | Epic |
| --- | --- | --- | --- | --- | --- |
| Dashboard | KPI cards with info, date ranges, sparkline, dues, expenses, alerts | KPI, low stock, daily bars | Partial | Date range picker, dues and expense cards, notifications, info text | E7 |
| Reports | Sales, channel, cashier, delivery, loyalty, wastage and more, table and CSV | 10 report types, view | Partial | Date filters on all, CSV share, charts parity | E7 |
| Accounting | Accounts, journals, approve, reverse, vouchers, statements, matching, fiscal-year lock, payables | Accounts, journals, approve, reverse, vouchers | Partial | Statements and matching, fiscal-year lock, period lock | E7 |
| Expenses | Create, delete | Create, delete | Full | None known | E7 |
| Aggregators | Channels, connection test, menu sync, import, outlet status, payouts, manual order, decision | Channels, menu sync, manual order, decision | Partial | Connection test, import, outlet status, payouts | E7 |
| Branches | CRUD, details only (tax and POS options moved to Settings) | CRUD with POS settings screen | Partial | Align with the new split: details here, tax and POS options in Settings | E8 |
| Activity log | Filterable log | List | Full | Filters | E7 |
| Sync conflicts | Retry or discard | Sync screen with retry and discard | Full | Server-side conflicts list if different | E1 |

### 4.5 Settings (largest gap)

The web Settings page now has tabs the app has no equivalent for. The app has a small local Settings screen (branch, language, theme, brand colour, sound) and a per-branch POS settings screen.

| Settings area | Web-app | Flutter today | Status | Epic |
| --- | --- | --- | --- | --- |
| General (website switch, tax number, social links, limits, delete delay, show deleted) | Yes | None | Missing, API | E8 |
| Payment methods (add, order, enable, type) | Yes | None | Missing, API | E8 |
| POS and billing, per branch (tax, service charge, buttons, cash suggestions, sounds, order edit and delete, sell without stock) | Yes | Partly in POS settings screen | Partial | E8 |
| Invoice print (6 styles, 4 paper sizes, live preview, test print) | Yes | Fixed 80 mm layout | Missing | E8 |
| SMS: provider, token, test, feature switches, wording per language | Yes | None | Missing, API | E8 |
| SMS module: history, promotion | Yes | None | Missing, API | E8 |
| Units and conversions | Yes | None | Missing, API | E4 |
| Delivery areas and the delivery switch, per branch | Yes | None | Missing, API | E8 |
| Scope notes (shared vs this branch) | Yes | None | Missing | E8 |

### 4.6 Website content

| Module | Web-app | Flutter today | Status | Epic |
| --- | --- | --- | --- | --- |
| Blog | CRUD | CRUD | Full | E9 |
| Careers | Openings, applications | Openings, applications | Full | E9 |
| Media | Photos and videos, order, feature, replace, captions | List, upload image, edit, move, delete | Partial (video, feature, replace, captions) | E9 |

### 4.7 Rider and customer

| Module | Web-app | Flutter today | Status | Epic |
| --- | --- | --- | --- | --- |
| Rider deliveries | List, status steps, COD, OTP on delivery, live location | List, status steps, COD amount | Partial (OTP verify, location, COD summary) | E10 |
| Customer ordering | Menu, cart, checkout, coupons, quote, pay online, tracking, addresses, loyalty, profile, device tokens | None | Missing (API exists) | E11 |

---

## 5. Cross-cutting gaps

| Area | Gap | Why it matters | Epic |
| --- | --- | --- | --- |
| Interface language | All labels are hard-coded English | The product promise is English and Bangla for every text | E0 |
| Realtime | No Reverb client; kitchen and order lists need manual refresh | Kitchen and POS depend on live updates | E3 |
| Push notifications | No Firebase; staff only hear sounds while the app is open | New online orders and low stock must reach a phone in the pocket | E3 |
| Security | No PIN or biometric lock; token stored in preferences | A shared counter tablet needs the same lock the web POS has | E1 |
| Offline | Only orders, shifts and attendance are queued; other edits fail offline | Clear rules for what works offline and a visible outbox | E1 |
| Printing | Fixed layouts; no paper or style choice; no Bluetooth or network printer path | Restaurants print on thermal printers, often by Bluetooth | E2, E8 |
| Tablet and landscape | Phone layouts only | POS and kitchen are used on tablets | E0 |
| Code structure | Very large screen files, maps instead of typed models, API calls inside widgets | Hard to extend to 40 modules safely | E0 |
| Tests | 3 test files, no API contract tests, no golden tests | Parity work without tests will regress | E0 |
| Accessibility | Not reviewed | Large touch targets, contrast, screen reader labels | E0 |
| Observability | No crash or error reporting | Field problems cannot be seen | E0 |

---

## 6. API work needed first

The app only talks to `/api/v1`. Operations that exist only as web (Inertia) routes need a stable API endpoint with the same validation, permission and business rules, implemented by calling the same Action classes so web and API cannot drift. Each item updates `docs/api.md` and the OpenAPI output.

| Area | Endpoints to add or extend |
| --- | --- |
| Reference data | Extend `branches/{branch}/reference` and `sync/bootstrap`: units with `type`, `factor`, `is_system`; payment methods; delivery zones; ingredient categories; reason codes; SMS features (read) |
| Orders | `PUT orders/{order}` (edit), delete in one step, reprint log, due sale, due collect, bill list filters and pagination |
| Payments | split, single, manual (with approval), gateway start and complete |
| Dues | list, collect |
| Tables | create, update, delete, QR image, regenerate, release |
| Reservations | update, delete, deposit hold and outcome, table assignment |
| Menu | categories CRUD, item delete, variants and add-ons in item payload, photo upload |
| Inventory | ingredient delete, categories CRUD, purchases edit, correct, delete, receive, wastage delete and approve, stock count list, close, delete, transfer delete |
| Units | list, create, update, delete (the same rules as the web: fixed units, lock when in use) |
| Customers and staff | customer detail with orders, update; employee update and delete; system users and roles |
| Payroll and attendance | advances, devices, manual entry, history |
| Accounting | statements and matching, fiscal-year lock |
| Aggregators | connection test, import, outlet status, payouts |
| Settings | general settings read and write; payment methods CRUD; delivery zones CRUD and switch; branch POS settings; invoice print (style and paper); SMS provider read and write (secret never returned), features, templates, test; SMS history list and promotion send |
| Profile | read, update, change password, photo |
| Realtime and push | Reverb channel authorisation for staff tokens; device token registration for staff (the customer endpoint exists); push events for new order, low stock, void request |
| Rider | OTP verify and live location in the app; COD summary |

Contract rules for every new endpoint: Bearer token, `permission` middleware matching the web route, branch scoping, JSON error shape from `api.md`, idempotency key on writes that create money records, and a feature test next to the web test.

---

## 7. Target app architecture

Keep the stack (Flutter stable, `http`, SQLite) and add structure so 40 modules stay maintainable.

```text
lib/
  main.dart
  app/                 app shell, routing, theme, localisation delegates
  core/
    api/               ApiClient, interceptors (auth, idempotency, retry), error mapping
    data/              local database, cache, outbox, sync engine
    realtime/          Reverb (Pusher protocol) client, channel subscriptions
    push/              Firebase messaging wrapper, token registration
    security/          secure token storage, PIN lock, biometrics
    print/             receipt renderer (styles and paper sizes), printer transports
    permissions/       can(permission), role helpers
    l10n/              ARB files en and bn, generated code
  shared/
    models/            typed models with fromJson/toJson (Order, Branch, Unit, ...)
    widgets/           design-system widgets (section 9.3)
    utils/             money, date, number (Bangla digits), validators
  features/<module>/
    data/              repository + DTOs (talks to ApiClient and local cache)
    domain/            use cases and rules shared with POS (cart maths, units)
    presentation/      screens, controllers (ChangeNotifier or Riverpod), widgets
test/                  unit, widget, golden, contract
integration_test/      end-to-end flows against a seeded backend
```

Decisions to confirm in Phase 0:

1. **State management.** Today `AppState` with `ChangeNotifier`. Recommended: Riverpod for new features and gradual migration (feature by feature), because it is testable and removes the large stateful screens. Alternative: stay on `ChangeNotifier` with strict controller classes. Pick one and write it down in `mobile/README.md`.
2. **Typed models.** Replace `Map<String, dynamic>` reads with models generated or hand-written per feature; start with Order, Branch, Unit, Ingredient, Payment.
3. **Localisation.** Flutter `gen_l10n` with `app_en.arb` and `app_bn.arb`; no literal strings in widgets (enforced by a lint script in CI). Bangla digits and money formatting through one helper.
4. **Repository layer.** Widgets never call `ApiClient` directly. One repository per module hides the endpoint, caching and offline rules and is the unit to mock in tests.
5. **Offline rules.** A table in `mobile/README.md` that says, per operation, whether it works offline, how it is queued and how conflicts are shown. Default: reads are cached; writes that create sales or attendance are queued; everything else needs a connection and says so clearly.
6. **Units and conversions as shared domain code.** One Dart implementation of the unit maths (type, factor, relation text, picker label), mirroring `web-app/resources/js/lib/units.js` and `App\Support\StandardUnits`, used by purchases, recipes, wastage and transfers.
7. **Invoice rendering.** One renderer driven by (style, paper size) mirroring `App\Support\ReceiptStyle`; the 6 styles and 4 paper sizes from the web, fed by the same branch setting.

---

## 8. Navigation and information architecture

Replace the single long drawer with a structure that scales to 40 modules.

- **Bottom navigation (phone), rail (tablet):** Home, POS, Orders, Kitchen (when permitted), More.
- **More:** grouped sections identical to the web sidebar groups: Sales, Inventory, People, Finance, Website, Settings, with search ("find a module") at the top.
- **Role-based home:** the Home screen shows what the role needs: cashier (open shift, quick POS, my orders), waiter (tables, open orders), kitchen (tickets), manager (KPIs, alerts, approvals), owner (KPIs, dues, low stock), rider (my deliveries).
- **Approvals inbox:** void requests, transfers to approve, journals to approve, manual payments to approve in one list (new, mobile-first).
- **Branch switcher** in the app bar for users who can access several branches, identical to the web.
- **Scope note** (shared by all branches vs this branch) on every Settings page, reusing the web wording.

---

## 9. UI and UX plan

### 9.1 Principles

1. Follow `design.md` tokens (colour, radius, spacing, type) so the app and web feel like one product; the brand colour setting already exists and stays.
2. Touch first: 48 dp minimum target, 8 dp grid, thumb-reachable primary actions at the bottom.
3. One primary action per screen; destructive actions need confirm (the same simple, reason, approval and typed levels as `useConfirm`).
4. Every list has loading skeleton, empty state, error with retry and offline banner.
5. Bangla is a first-class language: layouts must survive longer text; Noto Sans Bengali is already bundled.
6. No emojis or symbol characters in text, labels or code; use the icon set.

### 9.2 Form factors

| Form factor | Layout |
| --- | --- |
| Phone portrait | Single column, bottom navigation, bottom sheets for pickers and payment |
| Phone landscape | POS in two panes (menu left, ticket right) from 600 dp width |
| Tablet | Navigation rail; POS three zones (categories, items, ticket); kitchen board with columns by stage; master-detail for orders, customers, purchases |
| Large text | Support system font scale up to 1.3 without clipped buttons |

### 9.3 Shared widget library (build once, use everywhere)

`AppScaffold`, `SectionHeader`, `ScopeNote` (shared vs this branch), `StatusChip`, `MoneyText`, `MoneyField`, `QuantityStepper`, `UnitField` (quantity plus unit with size hint), `SearchBar`, `FilterChips`, `DateRangeBar`, `RecordTile`, `KpiCard`, `ChartCard`, `EmptyState`, `ErrorState`, `OfflineBanner`, `ConfirmSheet` (four levels), `FormSection`, `SwitchRow`, `PickerSheet`, `Skeleton`, `PermissionGate`, `ToastPreview` (demo SMS card). Golden tests for each in light, dark, English and Bangla.

### 9.4 Key flows to design before building (wireframes in `docs/mobile/wireframes/`)

| Flow | Screens |
| --- | --- |
| POS sale | Menu grid, item sheet (variant, add-ons, note), ticket, customer sheet, bill review, payment sheet (split, due), receipt preview and print |
| Due collection | Due list, due detail, collect sheet |
| Purchase | List, new purchase (supplier, lines with unit picker and size hint, receive now), edit and correct |
| Recipe builder | Lines with unit picker, live cost and margin bar |
| Units manager | Grouped list with relations, add or edit sheet with "1 box = 12 pcs" sentence |
| Settings hub | Search, groups, each page with scope note |
| SMS | Provider sheet, message list with switches, wording editor (variables, count, preview), history list with filters, promotion composer with confirm |
| Invoice print | Style and paper pickers, live preview, test print |
| Kitchen | Board by stage, ticket detail, sound and push on new ticket |
| Approvals | Inbox with approve and reject, reason entry |

Sketch of the POS ticket on a phone (layout intent only):

```text
+--------------------------------+
| POS  Takeaway   Table: -   [..]|
+--------------------------------+
| French Fry         2   200.00  |
|   + Cheese          1    30.00 |
| Chicken Fry (1)     1   120.00 |
+--------------------------------+
| Customer 01712345678 (invoice  |
| SMS)                           |
| Discount  [ % | Tk ]  0         |
| Subtotal 350.00  VAT 0.00      |
| TOTAL 350.00                   |
+--------------------------------+
| [Hold] [Send to kitchen]       |
| [Quick save] [Confirm payment] |
+--------------------------------+
```

### 9.5 Accessibility and quality bar

Semantic labels on every icon button, focus order, contrast checked in both themes, text scale test, no colour-only status (chips carry text), haptic feedback on key actions, screen reader pass on POS and payment.

---

## 10. Roadmap

Sizes: **S** up to 2 days, **M** 3 to 5 days, **L** 1 to 2 weeks, **XL** more than 2 weeks. One developer assumed per epic; API tasks can run in parallel with app tasks of the previous phase. Check items off here as they ship, in the same style as `roadmap.md`.

### Phase 0: Foundation and audit (before any feature)

- [ ] E0.1 Run the app on an Android emulator and an iOS simulator against a seeded backend; record a screen-by-screen audit (works, broken, missing) in `docs/mobile/audit-<date>.md`. **M**
- [ ] E0.2 Decide state management, write the architecture section into `mobile/README.md`. **S**
- [ ] E0.3 Introduce `gen_l10n` with English and Bangla ARB files; move existing strings; add a CI check that rejects literal UI strings. **L**
- [ ] E0.4 Create typed models for Order, Branch, Unit, Ingredient, Payment, Customer; repository layer for Orders and Reference data. **L**
- [ ] E0.5 Build the shared widget library (section 9.3) with golden tests (light, dark, en, bn). **L**
- [ ] E0.6 Responsive scaffold: bottom navigation, rail, More screen with module search, role-based Home. **M**
- [ ] E0.7 CI: `flutter analyze`, `flutter test`, golden tests, build debug APK on every push. **S**
- [ ] E0.8 Crash and error reporting (Sentry or Firebase Crashlytics), app version in every API call header. **S**
- [ ] E0.9 Split `pos_screen.dart` and `inventory_screens.dart` into feature folders without changing behaviour; tests first. **L**

**Acceptance:** audit document exists; app builds in CI; every existing screen renders in English and Bangla; no literal strings fail the check; existing tests plus new golden tests pass.

### Phase 1: API parity foundation (web-app)

- [ ] A1.1 Extend reference and bootstrap payloads (units with type and factor, payment methods, delivery zones, categories, reason codes). **M**
- [ ] A1.2 Orders: edit, delete in one step, reprint log, filters and pagination. **M**
- [ ] A1.3 Payments: split, single, manual with approval, gateway start and complete. **M**
- [ ] A1.4 Dues: list and collect; due sale on create. **M**
- [ ] A1.5 Units CRUD with fixed and lock rules. **S**
- [ ] A1.6 Settings endpoints: general, payment methods, delivery zones and switch, branch POS and tax, invoice print. **L**
- [ ] A1.7 SMS endpoints: provider (secret write-only), features, templates, test, history, promotion. **L**
- [ ] A1.8 Profile endpoints. **S**
- [ ] A1.9 Realtime: Reverb auth for staff tokens; staff device tokens and push events. **L**
- [ ] A1.10 Update `docs/api.md`, OpenAPI, and add API feature tests that run the same cases as the web tests. **M**

**Acceptance:** every endpoint documented and tested; permission and branch scope tested for a role without access; no business rule is duplicated outside the Action classes.

### Phase 2: Core selling parity (E1, E2)

- [ ] E1.1 Secure token storage, PIN lock with idle timeout from the POS setting. **M**
- [ ] E1.2 Profile screen (name, photo, password); Google and OTP sign-in. **M**
- [ ] E1.3 Offline rules table, outbox screen with clear states, retry and discard. **M**
- [ ] E2.1 Due sale and Dues module (list, detail, collect). **L**
- [ ] E2.2 Coupons and loyalty redeem in POS; scheduled orders. **M**
- [ ] E2.3 Bill review step controlled by the branch setting; per-action print and clear behaviour. **M**
- [ ] E2.4 Payments: manual with approval, gateway checkout (web view or deep link), payment methods from Settings. **L**
- [ ] E2.5 Orders: delete in one step with reason, reprint log, filters, pagination, order edit and delete flags. **M**
- [ ] E2.6 Stock warnings in POS from recipe stock; "sell without stock" setting. **M**
- [ ] E2.7 Invoice renderer with 6 styles and 4 paper sizes; Bluetooth and network printer transport; test print. **XL**

**Acceptance:** a cashier completes cash, split, due and gateway sales on the phone, offline sales sync once, every receipt matches the web preview for the same order, role without permission cannot see the action.

### Phase 3: Kitchen, floor and delivery (E3)

- [ ] E3.1 Realtime client; kitchen board and orders list update live; reconnect and backoff. **L**
- [ ] E3.2 Push notifications for new online order, void request, low stock, with deep links. **L**
- [ ] E3.3 Tables: create, edit, delete, QR view and share, release. **M**
- [ ] E3.4 Reservations: edit, delete, deposit, table assignment. **M**
- [ ] E3.5 Delivery: COD reconcile, zones. **M**

**Acceptance:** a new order appears on the kitchen board within 2 seconds on a good connection; a push opens the right order; table QR matches the web.

### Phase 4: Inventory and units (E4, E5)

- [ ] E4.1 Shared unit domain code and `UnitField`; units manager screen. **M**
- [ ] E4.2 Ingredients: categories, delete, low-stock view in the ingredient unit. **M**
- [ ] E4.3 Purchases: edit, correct, delete, receive, per-line unit and conversion hint, inline create. **L**
- [ ] E4.4 Recipes: per-line unit, live cost and margin. **M**
- [ ] E4.5 Wastage reasons and approval; stock count close and variance; transfer delete. **M**
- [ ] E5.1 Menu: categories, variants and add-ons editor, photo upload, safe delete. **L**

**Acceptance:** buying 10 l of a ml ingredient shows "Adds 10,000 ml to stock" and stores the same quantity the web does; recipe cost equals the web value; unit rules (fixed, locked when used) match the web.

### Phase 5: People and customers (E6)

- [ ] E6.1 Customers: detail with orders, edit. **M**
- [ ] E6.2 Staff: edit, delete, system users and roles. **M**
- [ ] E6.3 Attendance devices and manual entry; payroll advances and run. **M**
- [ ] E6.4 Coupons and letters completion. **S**

**Acceptance:** each role-permission combination is covered by a widget test that proves the action is shown or hidden.

### Phase 6: Finance, reports, settings (E7, E8, E9)

- [ ] E7.1 Dashboard parity: date ranges, dues and expense cards, notifications, info text. **M**
- [ ] E7.2 Reports: filters on all, CSV share, charts. **M**
- [ ] E7.3 Accounting: statements and matching, period and fiscal-year lock; aggregators remaining actions. **L**
- [ ] E8.1 Settings hub with scope notes; General, Payment methods, Delivery areas, branch POS and tax. **L**
- [ ] E8.2 Invoice print settings with live preview. **M**
- [ ] E8.3 SMS: provider, features, wording editor with variables and part count, history, promotion. **L**
- [ ] E9.1 Media completion (video, feature, replace, captions). **M**

**Acceptance:** every setting on the web Settings page can be changed in the app and shows the same result on both; secrets are never displayed.

### Phase 7: Rider and customer app (E10, E11)

- [ ] E10.1 Rider: OTP verify on delivery, live location, COD summary. **M**
- [ ] E11.1 Customer mode: branch list, menu, cart, checkout, delivery quote, coupons. **XL**
- [ ] E11.2 Online payment, order tracking, addresses, loyalty, profile, push. **XL**

**Acceptance:** a customer can place and track an order end to end; a rider can finish a delivery with OTP; both flows have integration tests.

### Phase 8: Hardening and release

- [ ] R1 Integration tests for the 10 critical flows (open shift, sell cash, split pay, due and collect, purchase and receive, recipe cost, void and approve, kitchen update, delivery with OTP, settings change). **L**
- [ ] R2 Performance pass: cold start, POS with 500 menu items, kitchen with 100 tickets; low-end Android device. **M**
- [ ] R3 Accessibility and Bangla review. **M**
- [ ] R4 Update `docs/user-guide*.md`, Play Store listing and privacy page; staged rollout (internal, closed, production). **M**

**Estimated total:** about 20 to 26 weeks for one developer, 12 to 14 weeks for two developers working API and app in parallel. These numbers are a first guess and should be reset after Phase 0.

---

## 11. Test and quality plan

| Layer | What | Tooling |
| --- | --- | --- |
| Unit | Cart and tax maths, units and conversion, invoice number, offline queue, permission helper | `flutter_test` |
| Widget | Every shared widget and each module's main screen with fake repositories | `flutter_test` |
| Golden | Shared widgets and receipts in light, dark, English, Bangla | `flutter_test` goldens |
| Contract | The app's models parse the real JSON from the OpenAPI examples; fails when the API changes | recorded fixtures plus a CI job against the backend |
| Integration | The 10 critical flows against a seeded backend in an emulator | `integration_test` |
| Static | `flutter analyze`, no literal UI strings, no emojis, import rules | CI scripts |
| Manual | Device matrix: one low-end and one current Android phone, one Android tablet, one iPhone; thermal printer test | checklist in `mobile/README.md` |

Rule: a feature is not done until it has tests at the unit and widget level, golden images where it adds a shared widget, and a note in `docs/api.md` if it needed an API change.

---

## 12. Definition of Done (per module)

- [ ] Works for each role that has the permission; hidden for roles without it; server also denies (tested).
- [ ] English and Bangla text, Bangla digits where the web uses them, no literal strings.
- [ ] Loading, empty, error and offline states.
- [ ] Phone and tablet layouts checked; large text checked.
- [ ] Offline behaviour matches the offline table in `mobile/README.md`.
- [ ] Tests added and passing; `flutter analyze` clean.
- [ ] Same result as the web for the same data (spot-checked against the web page).
- [ ] `docs/api.md`, `docs/mobile/README.md`, user guide updated.

---

## 13. Risks and open questions

| Risk or question | Effect | Mitigation or decision needed |
| --- | --- | --- |
| Hands-on audit not done yet | Matrix may be wrong in places | Phase 0 task E0.1 |
| Printing on Bluetooth thermal printers varies by model | Receipt layouts differ | Pick 2 supported models; transport interface with a test print screen |
| Payment gateways need redirects | Complex in an app | Use an in-app browser with a return deep link; decide with the payments owner |
| Reverb on mobile networks | Dropped connections | Reconnect with backoff, fall back to polling |
| One person doing API and app | Long critical path | Split API and app work as in Phase 1 and 2 |
| Customer app scope | Largest phase | Decide whether the customer experience stays on the website (PWA) instead |
| Keeping `web-app` and the app in step after this | Drift returns | Parity checklist in every web PR that touches an admin operation; the contract tests in section 11 |
| App store rules for payments and accounts | Review delays | Follow store guidance early; keep staff-only features behind login |

Decisions needed from the product owner before Phase 2:

1. State management choice (section 7, item 1).
2. Whether the customer app is part of this app or stays web-only (Phase 7).
3. Which printer models are supported.
4. Whether bulk tables (reports, accounting statements) must be fully usable on a phone or can offer "open in the web panel" for the widest views.

---

## Appendix A: Web operations with no mobile equivalent today

Operations that exist in `web-app` and are not used by `flutter-app` at all, grouped for quick checking. (The detailed matrix is section 4.)

- Orders: `PUT orders/{order}` edit through the web flow, `orders/{order}/delete`, `orders/{order}/due`, `orders/{order}/split`, `single-payment`, `manual-payment`, `gateway-payment`, `payments/{payment}/approve`, `pos/orders/{order}/clear`, `pos/tables/{table}/release`, `pos/unlock`
- Dues: `dues/{order}/collect`
- Tables: create, update, delete, QR, regenerate all
- Reservations: update, delete, deposit and outcomes
- Menu: categories CRUD, item delete
- Inventory: ingredient delete and categories, purchase edit, receive, delete, wastage delete, stock count close and delete, transfer delete
- Settings: all of section 4.5
- SMS module: history, promotion
- Staff: users and employees update and delete
- Attendance: devices and tokens, manual record
- Payroll: advances
- Accounting: statements, matching, fiscal-year lock
- Aggregators: connection test, import, outlet status, payouts, toggle
- Media: feature, replace
- Profile: update, password
- Delivery: COD reconcile

## Appendix B: Items the app already does that should be kept

Offline order queue with idempotency keys and conflict screen; shift open and close with expected cash; PDF receipts with Bangla shaping (`pdf_shaper.dart`); kitchen token; brand colour and app name customisation; sound effects; per-branch POS settings screen; generic record form and list used by blog, careers, letters, media.
