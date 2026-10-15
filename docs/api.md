# API SPECIFICATION: DT Restora

> The conventions (section 1) are the same for every product. Rewrite the endpoint list (sections 3+) per product.

---

## 1. Purpose & Conventions

**Who uses the API:** the future native mobile apps (customer, rider, staff), KDS screens and third-party integrations. **The mobile apps are built in a separate project later; this project only provides the API.**
**The admin web app, POS and public website use Inertia controllers (session auth)** that call the _same Actions_ as the API. Do not build two copies of business logic.

| Topic         | Rule                                                                                                                                                              |
| ------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Base path     | `/api/v1/...`, defined in `routes/api.php`                                                                                                                        |
| Auth          | Laravel Sanctum bearer tokens. Two guards: `staff` (users table) and `customer` (customers table). Tokens carry abilities (`staff`, `rider`, `customer`).         |
| Docs          | OpenAPI generated automatically (Scramble) and served at `/api-docs`. Every endpoint must appear with request and response examples.                              |
| Versioning    | No breaking change inside `v1`. Add fields freely, never rename or remove. Clients send `X-App-Version` and `X-Platform` (`android`/`ios`/`web`) for diagnostics. |
| Format        | JSON, `Accept: application/json`                                                                                                                                  |
| Success       | `{"success": true, "data": {...}, "message": "Localized message"}`                                                                                                |
| Error         | `{"success": false, "error": {"code": "INSUFFICIENT_STOCK", "message": "...", "details": {...}}}` with the matching HTTP status                                   |
| Language      | `Accept-Language: en` or `bn`                                                                                                                                     |
| Money         | Decimal **string** in BDT, e.g. `"1250.00"`                                                                                                                       |
| Dates         | ISO 8601 with timezone (`Asia/Dhaka`)                                                                                                                             |
| Pagination    | `?page=1&per_page=25`, response includes `meta` (`current_page`, `last_page`, `total`)                                                                            |
| Filtering     | `?filter[status]=pending&filter[date_from]=2026-01-01&sort=-created_at`                                                                                           |
| Idempotency   | `POST /orders` and any payment `POST` require an `Idempotency-Key` header. Same key within 24h returns the original response and creates nothing new.             |
| Rate limits   | Login and OTP: strict. Others: 60/min per token.                                                                                                                  |
| Branch access | Every `/branches/{branch}/...` route checks the user is allowed that branch, else **404**.                                                                        |

### Error codes

| HTTP | Code                      | When                                      |
| ---- | ------------------------- | ----------------------------------------- |
| 401  | `UNAUTHENTICATED`         | Missing or invalid token                  |
| 403  | `FORBIDDEN`               | Role lacks permission                     |
| 404  | `NOT_FOUND`               | Missing, or belongs to another branch     |
| 422  | `VALIDATION_FAILED`       | Invalid input, `details` has field errors |
| 409  | `INSUFFICIENT_STOCK`      | Not enough ingredient stock               |
| 409  | `ORDER_ALREADY_PAID`      | Payment on a paid order                   |
| 409  | `SHIFT_NOT_OPEN`          | Cashier has no open shift                 |
| 422  | `PAYMENT_AMOUNT_MISMATCH` | Payments don't equal the total            |
| 429  | `TOO_MANY_REQUESTS`       | Rate limit hit                            |
| 502  | `PAYMENT_GATEWAY_ERROR`   | bKash / Nagad / SSLCommerz failure        |
| 500  | `SERVER_ERROR`            | Unexpected (details never exposed)        |

---

## 2. Authentication (`/api/v1/auth`)

| Method | Path          | Notes                                                                    |
| ------ | ------------- | ------------------------------------------------------------------------ |
| POST   | `/login`      | `login` (username, email or mobile) + `password`. Returns Sanctum token. |
| POST   | `/logout`     | Revokes current token                                                    |
| GET    | `/me`         | Current user, roles, permissions, branches                               |
| POST   | `/google`     | Exchange Google token via Socialite                                      |
| POST   | `/otp/send`   | Sends OTP via BdBulkSMS. Rate limited by IP + mobile.                    |
| POST   | `/otp/verify` | Verifies OTP (hashed, 5-minute expiry, 5 attempts)                       |

---

## 3. Endpoints

All branch routes are prefixed `/api/v1/branches/{branch}`.

### Menu & Orders

| Method | Path                               | Notes                                                                                           |
| ------ | ---------------------------------- | ----------------------------------------------------------------------------------------------- |
| GET    | `/menu`                            | Active categories and items, with availability from stock                                       |
| GET    | `/orders`                          | List, filterable by status, date, table, waiter                                                 |
| POST   | `/orders`                          | Create order (table, waiter, items). Needs `Idempotency-Key`. Broadcasts `OrderCreated` to KDS. |
| GET    | `/orders/{order}`                  | Order detail                                                                                    |
| PATCH  | `/orders/{order}/status`           | `pending → preparing → ready → served` (KDS uses this)                                          |
| POST   | `/orders/{order}/payments`         | One or many payment lines. Needs `Idempotency-Key`.                                             |
| POST   | `/orders/{order}/void-requests`    | Cashier requests a void with reason                                                             |
| POST   | `/void-requests/{request}/approve` | Manager approval                                                                                |

### Inventory & Recipes

| Method | Path                                                   | Notes                                                                             |
| ------ | ------------------------------------------------------ | --------------------------------------------------------------------------------- |
| GET    | `/inventory/stock`                                     | Current stock per ingredient                                                      |
| POST   | `/inventory/wastage`                                   | Mandatory `reason_code`; cost is calculated by the server                         |
| GET    | `/recipes` / `POST /recipes` / `PUT /recipes/{recipe}` | Recipe builder                                                                    |
| GET    | `/recipes/{recipe}/cost`                               | Current food cost and food-cost %                                                 |
| POST   | `/purchases` / `GET /purchases`                        | Purchase orders and receipts                                                      |
| POST   | `/stock-transfers`                                     | Transfer to another branch; approve and receive endpoints follow the same pattern |

### Dashboard & Reports (owner / manager)

| Method | Path                                        | Notes                                                                            |
| ------ | ------------------------------------------- | -------------------------------------------------------------------------------- | --- | ----- |
| GET    | `/api/v1/dashboard?branch=all&period=today` | Gross sales, net sales, food cost, wastage, salary, expenses, net profit, alerts |
| GET    | `/reports/{type}`                           | `sales`, `wastage`, `stock-valuation`, `food-cost`, ... supports `?format=json   | pdf | xlsx` |

### Staff & Shifts

| Method   | Path                             | Notes                                                 |
| -------- | -------------------------------- | ----------------------------------------------------- |
| GET/POST | `/employees`                     | Staff profiles                                        |
| POST     | `/shifts/open` / `/shifts/close` | Cashier cash shift, close returns expected vs counted |

---

## 4. Payment Gateways

**Principle:** never trust the browser or the callback payload alone. Always confirm the final status with the gateway server-to-server, then update the order inside a transaction. Make confirmation idempotent (a duplicate callback must not double-pay).

Gateway callbacks and IPN are **public web routes** (`routes/web.php`, CSRF-excluded), not Sanctum API routes. Gateway credentials live in `.env` / `config/services.php`.

### bKash (Tokenized Checkout)

1. `POST /api/v1/payments/bkash/create`: server gets a grant token, creates the payment, returns the `bkashURL`.
2. Customer pays on bKash and is redirected to `GET /payments/bkash/callback` (web route).
3. Callback calls bKash **execute payment**, then **query payment**, and only marks the payment `completed` if the query confirms it.
4. Store `paymentID` and `trxID` on the payment record.

### Nagad

Same pattern as bKash: initialize → redirect → callback → server-side verify. Define the exact endpoints from the Nagad merchant documentation you receive. Until credentials are available, build it behind a `PaymentGateway` config switch and a **manual verification fallback** (cashier enters the TrxID; manager can review).

### SSLCommerz

1. `POST /api/v1/payments/sslcommerz/init`: creates session, returns the gateway redirect URL.
2. `POST /payments/sslcommerz/ipn` (web route): receives IPN, then calls the SSLCommerz **validation API** with `val_id`. Check status, amount and currency against the local order before marking paid.
3. Success, fail and cancel redirect URLs show the customer a result page (do not trust them for status).

---

## 5. Real-time (Reverb)

Private channels, authorized by branch permission:

| Channel                         | Events                                                       |
| ------------------------------- | ------------------------------------------------------------ |
| `private-branch.{id}.kitchen`   | `OrderCreated`, `OrderStatusChanged`                         |
| `private-branch.{id}.tables`    | `TableStatusChanged`                                         |
| `private-branch.{id}.alerts`    | `LowStockAlert`, `WastageSpikeAlert`                         |
| `private-owner.alerts`          | Cross-branch alerts for owners                               |
| `private-branch.{id}.delivery`  | `DeliveryAssigned`, `DeliveryStatusChanged` (dispatch board) |
| `private-customer.{id}.orders`  | `OrderStatusChanged` for that customer                       |
| `private-order.{id}.tracking`   | `RiderLocationUpdated` (customer or signed-link tracking)    |
| `private-rider.{id}.deliveries` | `DeliveryAssigned` for that rider                            |

Event payloads contain IDs plus the minimum display data; clients refetch for details.

---

## 6. Customer API (`/api/v1/customer`) and Public Menu

Used by the website (via Inertia, same Actions) and the future customer mobile app. A customer can only access their own data; anything else returns 404.

| Method              | Path                                  | Notes                                                                |
| ------------------- | ------------------------------------- | -------------------------------------------------------------------- |
| POST                | `/auth/otp/send` / `/auth/otp/verify` | Mobile OTP login, creates the customer on first verify               |
| POST                | `/auth/google`                        | Google login                                                         |
| GET/PATCH           | `/me`                                 | Profile                                                              |
| GET/POST/PUT/DELETE | `/addresses`                          | Saved addresses with lat/lng and zone                                |
| GET                 | `/branches`                           | Branches with open/closed status and delivery availability (public)  |
| GET                 | `/branches/{branch}/menu`             | Public menu with variants, add-ons and availability (public)         |
| POST                | `/delivery/quote`                     | Body: address or coordinates. Returns zone, fee, minimum order, ETA. |
| POST                | `/orders`                             | Place order (delivery, pickup, dine-in). Needs `Idempotency-Key`.    |
| GET                 | `/orders` / `/orders/{order}`         | History and detail                                                   |
| POST                | `/orders/{order}/cancel`              | Allowed only before the kitchen accepts                              |
| POST                | `/orders/{order}/pay`                 | Starts an online payment (returns gateway URL)                       |
| GET                 | `/orders/{order}/tracking`            | Status timeline and rider location                                   |
| GET                 | `/loyalty`                            | Balance, tier, ledger (paginated)                                    |
| POST                | `/coupons/validate`                   | Checks a code against the cart and returns the discount              |
| POST                | `/devices`                            | Register push token: `token`, `platform`                             |
| DELETE              | `/devices/{token}`                    | Unregister                                                           |

**Guest tracking:** `GET /api/v1/track/{signedToken}` returns the same tracking data without login. The token is signed, tied to one order, and expires after delivery.

---

## 7. Delivery & Rider API (`/api/v1`)

| Method | Path                                      | Notes                                                                                                                  |
| ------ | ----------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| GET    | `/branches/{branch}/deliveries`           | Dispatch board (manager)                                                                                               |
| POST   | `/deliveries/{delivery}/assign`           | Body: `rider_id`                                                                                                       |
| GET    | `/rider/deliveries`                       | Rider sees only their assigned deliveries                                                                              |
| POST   | `/rider/deliveries/{delivery}/status`     | `picked_up`, `on_the_way`, `delivered`, `failed` (with reason)                                                         |
| POST   | `/rider/location`                         | Body: `lat`, `lng`, `accuracy`, `recorded_at`. Throttled (max 1 per 5 s). Broadcasts to `private-order.{id}.tracking`. |
| POST   | `/rider/deliveries/{delivery}/verify-otp` | Optional proof of delivery                                                                                             |
| GET    | `/rider/cod-summary`                      | Cash to hand over                                                                                                      |

---

## 8. Loyalty & Coupons (staff)

| Method       | Path                             | Notes                                                              |
| ------------ | -------------------------------- | ------------------------------------------------------------------ |
| GET          | `/customers?filter[mobile]=`     | Lookup at POS                                                      |
| POST         | `/orders/{order}/loyalty/redeem` | Body: `points`, `otp` (or manager override permission)             |
| GET/POST/PUT | `/coupons`                       | Coupon management                                                  |
| POST         | `/loyalty/adjust`                | Manual adjustment, permission and reason required, activity-logged |

---

## 9. Aggregator Webhooks & Management

Webhooks are **public web routes** (not Sanctum), one per channel: `POST /webhooks/aggregators/{channel}`.

Rules for every channel:

1. Verify the signature or secret defined by that platform before doing anything.
2. Reject unknown or malformed payloads with 400, and log them without secrets.
3. Use the platform's order ID as a unique key per channel, so a repeated webhook is acknowledged but creates nothing.
4. Respond quickly; process heavy work in a queued Job.

| Method  | Path                                                    | Notes                                    |
| ------- | ------------------------------------------------------- | ---------------------------------------- |
| GET/PUT | `/api/v1/aggregators/channels`                          | Channel settings and commission %        |
| POST    | `/api/v1/aggregators/orders/{order}/accept` / `/reject` | Pushes the decision back to the platform |
| POST    | `/api/v1/aggregators/menu-sync`                         | Sync availability and prices             |
| POST    | `/api/v1/branches/{branch}/orders/manual-aggregator`    | Manual entry when no API exists          |

---

## 10. Accounting (`/api/v1/accounting`, accountant and owner)

| Method       | Path                                                                                                | Notes                                            |
| ------------ | --------------------------------------------------------------------------------------------------- | ------------------------------------------------ | --- | ----- |
| GET/POST/PUT | `/accounts`                                                                                         | Chart of accounts                                |
| GET          | `/journals` / `POST /journals`                                                                      | Manual journals (must balance, require approval) |
| POST         | `/journals/{journal}/approve` / `/reverse`                                                          | Posted entries are reversed, never edited        |
| GET/POST     | `/vouchers`                                                                                         | Expense and payment vouchers with attachments    |
| GET          | `/payables`                                                                                         | Supplier balances and aging                      |
| POST         | `/periods/{period}/lock`                                                                            | Lock a closed period                             |
| GET          | `/reports/trial-balance`, `/ledger`, `/profit-loss`, `/balance-sheet`, `/cash-flow`, `/vat-summary` | `?format=json                                    | pdf | xlsx` |

---

## 11. Offline Sync (`/api/v1/sync`)

Used by the POS PWA (and any future staff app).

| Method | Path                                 | Notes                                                                                                                                                                           |
| ------ | ------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| GET    | `/sync/bootstrap`                    | Full reference data (menu, prices, tables, settings, customers for lookup) plus a `data_version`                                                                                |
| GET    | `/sync/changes?since={data_version}` | Delta updates, including deletions                                                                                                                                              |
| POST   | `/sync/push`                         | Body: an array of queued items. Each item has `client_id` (ULID), `type` (`order`, `payment`, `shift`, `wastage`, `stock_count`, `attendance`), `payload`, `created_at_device`. |

**Rules**

- Every item is processed **idempotently** by `client_id`; resending returns the earlier result.
- The response gives a per-item result: `accepted`, `duplicate`, or `conflict` (with a reason code such as `NEGATIVE_STOCK`, `ITEM_UNAVAILABLE`, `SHIFT_CLOSED`).
- Items are applied in order of `created_at_device`. One failing item must never block the others.
- Prices come from the device's price snapshot; the server records the `data_version` used.
- Conflicts are stored and shown in the Sync Conflicts screen for a manager to resolve.
