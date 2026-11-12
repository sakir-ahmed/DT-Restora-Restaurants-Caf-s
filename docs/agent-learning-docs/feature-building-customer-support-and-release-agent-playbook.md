# Agent playbook: building a feature end to end, explaining numbers to a customer, and releasing (learned from the DT Restora pre-order session)

Purpose: this file records what the owner asked for, how they wanted it done, what went wrong and what was verified in one long session, so a new AI agent can repeat the good habits and skip the mistakes. It covers three kinds of work: (1) building a complete feature (the pre-order module and dashboard changes), (2) investigating a customer's confusing report with the owner's browser and writing a plain Bangla explanation, (3) committing, pushing and updating the production branches. Read sections 1 and 2 first, then the section for the job at hand.

Written 13 Nov 2026. Source project: DT Restora (restaurant management and POS software by Datatech BD Ltd., Dhaka, Bangladesh), Laravel 13 + Inertia + Vue 3 in `web-app/`. Companion files in this folder: `software-development-standards-and-preferences.md` (stack, code and UI rules, read it before coding) and `marketing-reels-and-meta-ads-agent-playbook.md` (marketing work). This file does not repeat them; it adds what this session taught.

---

## 1. How the owner worked with the agent in this session

1. **Short instructions in mixed Bangla-English with typos.** Read the intent. Examples: "pre order menu after order menu" meant "place the Pre-order sidebar item after Orders"; "here check date and number of guest weight sizes" meant "the width and alignment of these two fields look wrong"; "purono self" meant "old sales".
2. **One request, several requirements, no priorities stated.** The pre-order request alone had about ten requirements (book, device-wise layouts, optional fields, print, SMS, share link and image, dashboard count). Split them, build all, and report each one back so nothing is silently dropped.
3. **They send a screenshot and expect you to compare it with the real screen.** The tablet dashboard photo drove the "3 columns, more compact" change. The form screenshot showed one label wrapping and misaligning a field. Fix exactly what the image shows, then check at that width.
4. **They challenge UX decisions with a plain question.** "Advance payment really need full width? what UX is it?" was a hint that the field was wrong, not a request for an explanation. Answer in one line, then fix it (a short field next to the table field, labels shortened).
5. **They ask for the same message in different lengths.** First a full Bangla message, then "make it in small". Keep a long version and a short version in mind; the short one lists changes in four to eight bullets.
6. **Customer-facing text is for a non-technical restaurant owner** (details in section 6). The owner forwards it to the customer almost as written.
7. **"Use my browser, no need to fix, just describe."** When a customer reports a wrong number, the owner wants the cause found in the real production data, read only, and explained. Never change data while investigating.
8. **They say "do git push in main and then update production/* with main"** after the work is done. Commit everything that is theirs (see section 7), push `main`, fast-forward the production branches, report the three hashes.
9. **They ask status questions about the repo** ("all branch are pushed?"). Answer from real `git` output for every local branch, not from memory.
10. **They want a playbook file after a big session** so the next agent needs no briefing (this file). Only create documentation files when asked, as here; `web-app/AGENTS.md` forbids unrequested docs.

Communication that worked: a one-line "what I am doing" when work takes long, then a short final summary with what changed, what was verified, what was not, and one decision needed. No padding, no emojis.

---

## 2. Rules from the project that applied every time

- Never use emojis or Unicode symbol icons (UI, lang files, SMS, receipts, code comments, commit messages, docs). `NoEmojiTest` enforces it. Even a middle dot HTML entity in a template was replaced with a hyphen. Use `AppIcon` (Font Awesome) for icons.
- Every user-facing text exists in `web-app/lang/en` and `web-app/lang/bn`. Add both together. `LocalizationTest` checks them.
- No `alert`, `confirm` or `prompt`; use `useConfirm()` (options: `variant`, `level: 'simple'`, `title`, `message`, `confirmText`; result has `.confirmed`).
- Follow sibling files: Actions for business logic, thin controllers, Form-request style validation, Pest tests, `BelongsToBranch` on branch-owned models.
- Before finishing PHP work: `./vendor/bin/pint`, `./vendor/bin/phpstan`, `php artisan test`. Before finishing frontend work: `npm run build`, then look at the screen.
- After adding or removing named routes run `php artisan ziggy:generate resources/js/ziggy.js`; `ZiggyRoutesTest` fails otherwise.
- Adding an SMS feature or template changes list counts asserted in `SmsFeaturesTest` (features and templates went from 10 to 11). Search tests for hard-coded counts when you add to a registry.

---

## 3. Building a feature end to end (pre-order module, as the worked example)

### 3.1 What was asked and what was built

A "pre-order" is not a reservation: no table is required, no time slot is held, and name, mobile, time, table and everything else are optional. The module added:

- Sidebar item "Pre-order" (placed after Orders, permission `preorders.view`) opening a **menu book** page for staff, with a second tab listing taken pre-orders.
- A public, shareable book at `/preorder/{branch code}` (guests browse and send a pre-order; rate limited, 5 per device per hour, 100 per branch per hour).
- Optional fields: name, mobile, address, visit date and time, guests, table (staff only), note, advance payment (staff only, may not exceed the total).
- Print copy (`resources/views/receipts/preorder.blade.php`, 80 mm slip, address, advance and balance shown), SMS copy when a mobile number is given and the `preorder_confirmed` SMS feature is on, share as link or as a 1080 x 1350 picture with a QR code.
- Dashboard: the existing Pre-orders KPI now counts pending menu-book pre-orders plus bookings that carry a pre-order; the card and the Low stock card show only when above zero and open a list modal.

### 3.2 Files to know

Backend: `app/Models/Preorder.php`, `app/Services/PreorderService.php` (prices through `ReservationService::priceLines`, creates, sends SMS), `app/Support/PreorderInput.php` (shared validation rules and mapping for staff and public), `app/Http/Controllers/Admin/PreorderController.php`, `app/Http/Controllers/Web/PreorderMenuController.php`, migrations `2026_11_12_100000_create_preorders_table.php` (also creates permissions `preorders.view` and `preorders.manage` and gives them to owner, branch-manager, cashier, waiter, super-admin) and `2026_11_12_200000_add_address_and_advance_to_preorders_table.php`, `RoleSeeder`, `SmsFeatures`, `SmsTemplates`, `lang/{en,bn}/preorders.php`, public translation group `preorders` registered in `HandleInertiaRequests::PUBLIC_TRANSLATION_GROUPS`.

Frontend: `resources/js/components/preorder/MenuBook.vue` (one component for both staff and public), `pages/admin/preorders/Index.vue`, `pages/web/PreorderBook.vue`, `lib/menuImage.js` (canvas poster), `components/ui-kit/ListModal.vue`, `components/ui-kit/KpiCard.vue` (new `action` and `tone` props), `pages/admin/Dashboard.vue`.

Tests: `tests/Feature/PreorderTest.php`. Routes: `web.preorder`, `web.preorder.store`, `web.preorder.slip` (secret token link), `admin.preorders.index|store|print|transition`.

### 3.3 Design decisions worth reusing

- **One component, two contexts.** The same `MenuBook.vue` serves the admin page (`embedded`, tables, advance) and the public page (language switch, no tables, no advance). Differences are props, not copies.
- **Server decides what the guest may claim.** The public route rejects `advance_amount` and `restaurant_table_id` with the `prohibited` rule; the form also hides them.
- **A secret token, not the order number, opens the guest copy** (`token`, 32 random characters). The order number is short and readable (`PRE-1112-K7QD`, confusing letters replaced).
- **A new migration for new columns** once the first migration has been pushed; never edit a pushed migration.
- **Keep records, do not post money you were not asked to post.** The advance is stored on the pre-order and printed; it is not posted to the cash book. This was stated to the owner as an open decision.
- **Dashboard lists come from the controller, not extra requests**: `lowStockItems` (max 50) and `waitingPreorders` (max 30, soonest first, each with `kind`, `planned`, `at`).

### 3.4 Responsive menu book standards that were accepted

Breakpoints used by the component (by `window.innerWidth`): phone under 640, tablet 640 to 1023, desktop 1024 to 1279, wide 1280 and up.

- Phone: one page, 5 dishes per page, chapter tabs on top, bottom dock (previous, contents, next, basket button), basket as a bottom sheet, form in **two steps** (items and totals, then optional details) so the form fits without scrolling.
- Tablet: one wide page, 6 dishes per page, basket as a right drawer 26 rem wide.
- Desktop: two-page spread. Wide: spread plus the basket as a sticky column beside the book (grid: stage in row 1, dock in row 2, basket spans both rows).
- Page sheets fill the screen height (`min-height: max(27rem, calc(100dvh - 15.5rem))` and similar per size) so there is no empty gap under the book.
- Touch targets at least 40 px (steppers and quick-add buttons), 44 px inputs, `enterkeyhint`, `inputmode`, `autocomplete` set.
- The dish row is a `div role="button"` containing a real plus button: the plus adds a plain dish at once (count badge turns green), and opens the picker only when the dish has sizes or extras. Never nest a `button` inside a `button`.
- Form layout rules the owner enforced: short numeric fields are not full width (Table and Advance share one row, Date and Guests share one row, 7 rem and 6 rem for the short field), labels must stay on one line (use "Guests", "Advance"), paired inputs align to the bottom of the row.
- Dashboard KPI cards: 2 columns on phones, 3 on tablets (`md`), 6 on very wide screens; padding `p-2.5`, value `text-xl`; the empty change row is not rendered.
- Share picture: a fixed 1080 x 1350 canvas (4:5). Two columns, whole categories packed to balance, dishes trimmed from the longest category until all fit above the footer, QR and link inside the footer. The first version grew with the menu and the footer overlapped the last rows; the owner saw the crop. Fixed size and a fit loop solved it.

---

## 4. Verification routine that caught real bugs

Do this after every UI change, not only when asked:

1. `npm run build`, then reload the page in the in-app browser (`preview_start`), sign in with the demo user if the session is gone (a new port means a new session).
2. Check the exact widths that matter: 390 (phone), 820 or 900 (tablet), desktop. Use `resize_window`, then return it with the `desktop` preset when done.
3. Drive the real flow: open the book, add items, continue, fill the form, place, see the success panel, open the print link. For admin-only pieces use the admin page.
4. Read the DOM for the facts a screenshot hides (for example both inputs report the same `top` and `height`).
5. Run the narrow tests, then the whole suite once at the end (about 2 minutes, 1,093 tests at the end of the session).

Bugs found only because the screen was checked: scoped CSS variables were missing inside teleported sheets (the dish sheet rendered with no paper background); the wide layout would have placed the dock over the book; the share picture footer overlapped dishes; one label wrapped and misaligned a field; a label was truncated in a narrow column.

---

## 5. Investigating a customer's wrong-looking number (net profit minus 4,850)

### 5.1 How the dashboard computes money (`app/Services/Metrics/MetricsService::summary`)

- Gross sales = sum of order totals (VAT and service charge included), excluding void orders.
- Net sales = sum of order subtotals minus discounts.
- Net profit = net sales - food cost - wastage - salary - expenses - delivery commission.
- Food cost = sum of `orders.food_cost`. Each order stores its own `food_cost` **at the moment it is created** (`CreateOrderAction`, using `CostingService`: recipe quantity x the branch's weighted-average ingredient cost). Salary is monthly salary / 30 x days plus attendance pay.

### 5.2 What was found (read-only, in the owner's browser)

- 10 Nov: gross 594 (660 - 66 discount), food cost 30.5 percent (about 181), profit 413. Normal.
- 11 Nov: two orders (1,460 and 60), food cost 419 percent (about 6,370), profit -4,850.49. Salary, expenses and wastage were all 0.
- Cause: **Milk had an average cost of 10,000 per litre** (Reports > Stock valuation, "Avg cost"; stock was -0.7 l). Three Chocolate Milk Shakes sold that day each carried about 2,000 of milk cost. The shake recipe was not opened, so the exact milk quantity per shake was inferred from the sums, and the real milk price was an assumption of about 100 per litre.
- Nothing was lost in cash. Food cost for 9 to 11 Nov together was 308 percent for the same reason.

### 5.3 Where a bad average cost can come from (code facts)

- `StockService::applyWeightedAverageCost`: when stock after the receipt is 0 or below, the new average is set straight to the incoming unit cost. One mistyped cost becomes the average.
- `StockService::removeWeightedAverageCost` (used when a received purchase is deleted or edited): when remaining stock is 0 or below it returns without recalculating, so a wrong average stays behind.
- `DeletePurchaseAction` and `CorrectReceivedPurchaseAction`: a received purchase is undone in one transaction (stock, average cost, journal), refused if stock would go below zero unless the branch allows negative stock, the row is kept marked deleted with a reason, before and after are logged. **They never touch orders already sold.**
- Past orders can still be edited themselves: `ReviseSettledSaleAction` re-prices a paid order at the current average cost, reverses and reposts its journal and takes stock again. That is how an old wrong food cost can be corrected after the ingredient cost is fixed.
- Whether a deleted milk purchase caused the 10,000 was **a hypothesis**: the purchases list showed only one fish purchase. Say so; do not present a guess as the cause.

### 5.4 How to investigate like this (read only)

1. Reports > Sales summary (per day orders and totals), Reports > Food cost (period food cost), Reports > Stock valuation (average cost per ingredient, look for outliers such as 10,000 next to 200).
2. Recipes > Edit recipe shows "Current cost" lines (quantity x unit cost) and food cost percent per dish; Purchases > open a row shows its lines (close with Cancel, never Save).
3. Match the day's orders to dishes, then dishes to the outlier ingredient, then check the sums (6,000 plus about 370 normal cost equals about 6,370).
4. Do not edit anything. Close panels with the X (Escape did not close side panels). Close your own browser tab when done.
5. Report what is data, what is inference and what is assumption.

---

## 6. Writing the Bangla message for a customer (the owner's format)

The owner asked for this several times and corrected the format twice. Rules:

1. Open with "ভাই, ..." and say plainly what the message is about.
2. **Narrative paragraphs, not question and answer.** They said "do not make question answer, make it like vai ekhane ...". Use "ভাই, এখানে ... তাই ..." style, with the reason before the rule.
3. Plain words, no jargon. Say "খাবারের খরচ" and "গড় দাম", explain once what the system does, then give the numbers with the arithmetic (594 - 181 = 413).
4. When a rule matters, put it in one **highlighted block** (a quote with bold heading) followed by **why it is important** in a short numbered list. Example used: once an order is sold, its food cost is saved and does not change when a purchase is edited or deleted; important because old reports do not change silently, past profit cannot be tidied by editing purchases, the books keep matching orders, and every change is logged.
5. Separate what is certain from what is a guess ("এটা আমাদের অনুমান").
6. Say clearly that there was no real loss when that is true, give the simple fix, and offer to correct the old figures separately.
7. Length: a full version, then a short version on request ("make it in small"): headings by area and 3 to 8 bullets, no extra explanation.
8. No emojis, no technical class or file names in the customer text. Put technical notes for the owner after the message and label them as not part of it.
9. Keep company claims consistent: do not invent prices or numbers. Only repeat numbers you read.

---

## 7. Committing, pushing and updating production branches

Repository facts: remote `origin` (GitHub); branches `main`, `production/armanskitchen`, `production/demo`. The production branches have had no commits of their own, so updating them from `main` is a fast-forward.

Routine the owner asked for twice:

1. `git status`. Commit everything that belongs to the work, **including the owner's own pending edits** when they said "all changed" (in this session that included unrelated aggregator files). Leave out machine artefacts: `web-app/public/hot.marketing-bak` is a local file that is never committed. Mention what was included and what was left out.
2. Commit message: a clear subject, a short body in bullets, no emojis, and the attribution line the harness provides.
3. `git push origin main`.
4. Confirm the production branches are only behind: `git rev-list --count origin/main..origin/production/x` must be 0, then `git push origin main:production/armanskitchen main:production/demo`. If a production branch has its own commits, stop and ask; never force push.
5. Verify with `git ls-remote --heads origin main production/armanskitchen production/demo` and report the three hashes. They were `f7cdd9f` then `acb4ce4` in this session.
6. Remind the owner that deploy steps still apply: new migrations need `php artisan migrate` on each site. Do not run a deploy.
7. For "are all branches pushed?" list every local branch with its remote hash. Local copies of the production branches were stale (still at an old commit) because the push came from `main`; two old local Claude branches had no remote. Report both facts and do not push or delete them unasked.

---

## 8. Environment and tool facts learned here

- **GitHub from Bash** needs `allowed_domains: ["github.com"]` on the command; without it the sandbox returns a 403 tunnel error. `git fetch` with stale refs gives misleading ahead/behind counts, fetch again after pushing.
- **PHPStan fails in the sandbox** with "Unable to create opcache lock file in /tmp". Run it in the owner's terminal and say it was not run.
- **`php artisan tinker --execute` can silently do nothing** here (psysh cannot write its history file). Test rows created or deleted through it were not created or deleted. Use `sqlite3 database/database.sqlite "..."`, the Boost `database-query` tool (read only), or a test.
- **Local database** is `database/database.sqlite`; `marketing.sqlite`, `e2e.sqlite` and others also exist. Check which one the running server uses before cleaning test rows.
- **`preview_start`** may give port 8000 or 8001 and a fresh session. Demo mode adds overlays (top demo banner, a "Demo guide" button, "Did you know" tips) that cover bottom bars in screenshots; hide fixed elements outside the page with a small script while checking. The login page offers one-click demo users.
- **Claude in Chrome** (the owner's real browser): `tabs_context_mcp` with `createIfEmpty` first, work in your own tab, wait about 3 seconds after `navigate` before `get_page_text` (the single page app can return the previous page's text), use `find` for refs, `form_input` for a report dropdown, close your tab when finished. `find` and `read_page` are read only; do not click Save, Delete or Publish while investigating.
- **Pint** reindents arrays; run it after editing lang files. A blind string replace can hit the wrong place: replacing `'low_stock' => ` matched a deeper line in `dashboard.php` and `DashboardReportTest` failed ("explanations in every language"). Match with enough context or anchor on the top-level key.
- Tests that touch the whole page (`DashboardReportTest`, `ZiggyRoutesTest`, `SmsFeaturesTest`, `LocalizationTest`, `NoEmojiTest`) are the ones that break when you add routes, lang keys, features or icons. Run them after every such change.

---

## 9. Mistakes made in this session (do not repeat)

1. Scoped styles used CSS variables defined on the component root, but sheets were teleported to `body`, so they lost the variables. Define shared variables on both the root and the overlay class.
2. A test script clicked `querySelector('.pb-sheet-foot .pb-primary')`, which matched the basket's Place button, and placed real test pre-orders. Use specific selectors and clean up test rows afterwards.
3. Test pre-orders stayed in the database because the tinker cleanup silently failed. Always confirm cleanup with a count.
4. The first share picture was cropped (footer overlapped dishes, height depended on the menu). Fix the canvas size and fit the content to it.
5. The first form had every field on one long screen and a full-width advance field; the owner had to ask for the two-step flow, the shorter field and the label fixes. Think about a short field before making it full width.
6. A section was inserted into the wrong place in a lang file by a substring match (see section 8).
7. Said "all branches done" would have been wrong: local production branches were stale and two local branches were unpushed. Check each branch.
8. Earlier in the session an explanation of the cost problem was first written as questions and answers; the owner wanted narrative text. Follow section 6 from the start.

---

## 10. Open items and suggestions to raise (do not do without asking)

- The Milk average cost (10,000) is still wrong in production; the fix is a correct purchase or a stock cost correction, then revising the affected old orders. The owner said not to fix during investigation.
- Fish showed a stock value of about 23,600 against one 15,000 purchase; worth a separate look.
- Recalculate the average cost from the remaining purchases when a purchase is deleted or edited, including when stock is zero or negative (the two gaps in section 5.3).
- Show a warning in the Purchases edit and delete dialogs that old sales keep their saved cost, in English and Bangla, with the reason (section 6, point 4). The rule exists in behaviour only, not in the UI.
- Dashboard warning when a recipe or a day shows a food cost above 100 percent.
- Post the pre-order advance to the cash book and optionally include it in the SMS (template variables are a fixed list in `SmsTemplates`, so adding one means updating the default text in both languages).
- Deploy note: run `php artisan migrate` on each production site after the pre-order release.

---

## 11. Reference locations

- Pre-order module code: see section 3.2. Dashboard: `app/Http/Controllers/Admin/DashboardController.php` (`waitingPreorders`, `lowStockItems`), `resources/js/pages/admin/Dashboard.vue`.
- Money and stock logic: `app/Services/Metrics/MetricsService.php`, `app/Services/CostingService.php`, `app/Services/StockService.php`, `app/Actions/Inventory/DeletePurchaseAction.php`, `app/Actions/Inventory/CorrectReceivedPurchaseAction.php`, `app/Actions/Sales/CreateOrderAction.php`, `app/Actions/Sales/ReviseSettledSaleAction.php`.
- Project rules: `CLAUDE.md` (root and `web-app/`), `web-app/AGENTS.md`, `docs/architecture.md`, `docs/design.md`.
- Other playbooks: `software-development-standards-and-preferences.md`, `marketing-reels-and-meta-ads-agent-playbook.md` (same folder).
