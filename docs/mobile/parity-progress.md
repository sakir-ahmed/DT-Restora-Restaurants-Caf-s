# Flutter parity: progress tracker

Working list for [../flutter-parity-plan.md](../flutter-parity-plan.md). The plan says what to build and why; this file says what is done, what is next and how each part was checked. Update it in the same commit as the part it describes.

## How each part is done

Work goes in small parts, one commit each. A part is only marked done when every box below is true.

1. **Scope** is written here first (what the part includes, what it does not).
2. **Contract**: if the part needs an API change, the endpoint is built and tested in `web-app` first (PHP test, `docs/api.md` updated), then the app part follows.
3. **Code** follows the architecture in the plan (typed code, no literal UI strings in new screens when the l10n part has landed, no emojis, shared widgets where they exist).
4. **Checks pass**:
   - `cd flutter-app && flutter analyze` shows no issues.
   - `cd flutter-app && flutter test` passes, with new tests for the logic added by the part.
   - For API parts: `cd web-app && ./vendor/bin/pint && php artisan test`.
5. **Same result as the web**: for anything with a visible result, the value is compared with the web page for the same data (written in the "Checked" column).
6. **Docs and tracker** updated; the commit message names the part.

Commands (the Flutter ones run in your own terminal; the sandbox used for AI sessions cannot open local sockets, so widget tests need a normal terminal):

```sh
cd flutter-app && flutter analyze && flutter test
cd web-app && ./vendor/bin/pint && php artisan test
```

Device checks run on the Android emulator (Pixel_API36). The SDK lives on the disk image `AndroidDev.sparsebundle`, attach it first (`diskutil image attach "/Volumes/SAKIR 1TB/AndroidDev.sparsebundle"`), then `flutter run -d emulator-5554 --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1` with the backend running on port 8000.

Status words: **Todo**, **Doing**, **Done**, **Blocked**.

## Parts

| Part | What | Plan ref | Status | Checked |
| --- | --- | --- | --- | --- |
| P01 | Baseline: `flutter analyze` clean, existing 14 tests green, tracker created | E0.7 | Done | analyze: no issues; test: 14 passed (2026-11-10) |
| P02 | Shared unit code in Dart (reference unit, relation text, picker label, conversion, stock hint), mirroring `web-app/resources/js/lib/units.js` | E4.1 | Done | analyze: no issues; `test/units_test.dart`: 10 passed; relations and the 10 l to 10,000 ml example match the web Settings and purchase form |
| P03 | API: `units` in the reference payload carry `type`, `factor`, `is_system`; ingredients carry `unit_type` and `unit_factor`; `docs/api.md` section 10b added | A1.1 | Done | `ApiReferenceTest`: 4 passed; Pint clean |
| P04 | Purchases: per-line unit picker with size hint, cost per chosen unit, "Adds N to stock" and line total | E4.3 | Done | analyze: no issues; 25 tests passed (11 new unit tests incl. `ingredientUnit`); the 10 l to 10,000 ml example matches the web form. Device check on the emulator (2026-11-11): the form opens, ingredients load, the unit picker shows `kg (1,000 g)`; labels were cut off on a phone, so the line layout was changed (qty and unit on one row, cost on its own row) and the add-ingredient field now clears after each pick. Still to do by hand: save a purchase in a different unit and compare stock with the web |
| P05a | API: average cost per base unit in the reference payload (`ingredient_costs`, for `recipes.manage`) so the app can show live recipe cost | A1.1 | Done | `ApiReferenceTest`: 6 passed; documented in `docs/api.md` 10b |
| P05b | Recipes: per-line unit, live cost and margin in the app | E4.4 | Todo | needs P05a |
| P06 | Wastage and stock transfers: unit picker | E4.5 | Todo | |
| P07 | API: dues list and collect; due sale on create | A1.4 | Todo | |
| P08 | Dues module in the app (list, detail, collect) and due sale in POS | E2.1 | Todo | |
| P09 | API: units CRUD with fixed and in-use rules | A1.5 | Todo | |
| P10 | Units manager in the app (Settings) | E4.1 | Todo | |
| P11 | API: settings (general, payment methods, delivery zones, invoice print) | A1.6 | Todo | |
| P12 | Settings hub in the app with scope notes; payment methods and delivery areas | E8.1 | Todo | |
| P13 | API: SMS provider, features, templates, history, promotion | A1.7 | Todo | |
| P14 | SMS settings and SMS module in the app | E8.3 | Todo | |
| P15 | Localisation foundation (`gen_l10n`, English and Bangla) | E0.3 | Todo | needs a decision on moving existing strings |
| P16 | Shared widget library with golden tests | E0.5 | Todo | |
| P17 | Typed models and repository layer for Orders and Reference data | E0.4 | Todo | |
| P18 | Invoice renderer: 6 styles, 4 paper sizes | E2.7 | Todo | |
| P19 | Realtime client and live kitchen board | E3.1 | Todo | needs Reverb auth for staff tokens (A1.9) |
| P20 | Push notifications | E3.2 | Todo | needs A1.9 |

Later parts (orders delete and reprint, tables, reservations, menu categories, staff, accounting, rider OTP, customer app) are in the plan's phases 2 to 7 and are added here as they are scheduled.

## Decisions waiting for the product owner

- State management for new features (plan section 7, item 1). Until decided, new parts keep the current `ChangeNotifier` and `AppScope` pattern and put logic in plain Dart classes that do not depend on widgets, so they can move later.
- Whether the customer app is part of this app.
- Supported thermal printer models.

## Log

| Date | Part | Notes |
| --- | --- | --- |
| 2026-11-10 | P01 | Baseline recorded. `flutter analyze`: no issues. `flutter test`: 14 passed. |
| 2026-11-10 | P04 | Purchase form: `_PurchaseLine`, unit picker, hint and total. `flutter analyze`: no issues. `flutter test`: 25 passed. Not run on a device. |
| 2026-11-10 | P03 | Reference payload extended in `StaffApiController::reference`, documented in `docs/api.md` 10b. PHP test added. The sync bootstrap has no units list, so nothing to change there. |
| 2026-11-10 | P02 | `lib/core/units.dart` and `test/units_test.dart` added. `flutter analyze`: no issues. New tests: 10 passed. |

## Device audit: Android emulator, 2026-11-11

Signed in as a local test owner and opened every Sales and Inventory screen. No exceptions in the Flutter log. App analysis: no issues; tests: 26 passed.

| Screen | Result |
| --- | --- |
| Login, Home | Works. The demo-accounts list on the login screen only works against a database that has those users |
| Dashboard | Works (net sales, orders, food cost, net profit, daily bars, low stock) |
| Orders | Works; list, status chips, totals match the web |
| Reservations | Works; booked reservation with Seated, No show, Cancelled |
| Kitchen | Works (empty state shown) |
| Cash shift | Works (open shift prompt) |
| Tables | Works |
| Menu | Works, but items show names only, no prices (web shows prices) |
| Stock | **Fixed.** Stock was shown in base units next to the ingredient unit (2,000 g shown as "2000 kg") and the low-stock flag compared the wrong units. Now converted with the ingredient's unit factor; `stockInUnit` has tests |
| Purchases | Works; list, new purchase sheet, ingredient list, unit picker. Layout fixed (see P04) |
| Stock count | Works, but "System" quantities are base units with no unit symbol, so a counted quantity is ambiguous. **Open** |
| Transfers | Works; transfers have no unit picker. **Open** (P06) |
| Wastage | Works, but the Log wastage form sits inside a second app bar (double header) and has no unit picker. **Open** (P06) |
| POS | Opens and loads the menu with prices and add buttons. One early tap on the POS tile did nothing and could not be reproduced reliably. **Watch** |

Not yet audited: People, Finance and Website modules, saving a sale, payment sheet, printing, offline mode.
