# DT Restora Demo - Google Play Store submission kit

Everything needed for the Play Console, in one place.

## Package identity
- **App name:** DT Restora Demo
- **Package:** `bd.com.datatechbd.dt_restora` (permanent once published)
- **Version:** 1.1.0 (versionCode 3)
- **Launcher label:** "DT Restora Demo"

## Signing
The upload keystore lives outside the repo (see `android/key.properties`). Build the store bundle with:

```bash
flutter build appbundle --release \
  --dart-define=API_BASE_URL=https://dt-restora.datatechbd.com/api/v1
```

Privacy policy URL: https://sakir-ahmed.github.io/dt-restora-mobile-app/privacy.html

## Short description (80 chars max)
Point of sale, kitchen display, inventory, accounting and reports for restaurants.

## Full description
DT Restora Demo is the staff companion app of the DT Restora restaurant
management suite - try the full system against the public demo server.

FOR THE COUNTER
- Fast point of sale with sizes, add-ons, bill discounts and split
  payments across cash, bKash, Nagad and card
- Hold and resume orders mid-shift; the cart survives restarts
- Cash shifts with drawer counting and difference reporting
- Receipt printing

FOR THE KITCHEN
- Live kitchen display with new-ticket chimes, timers and colour-coded
  urgency; tickets move with one tap

FOR THE MANAGER
- Reservations, tables and delivery dispatch with rider assignment
- Inventory: stock, wastage log, purchases, stock counts, transfers,
  recipes and ingredient management
- Staff, attendance punch in/out and payroll overview
- Accounting statements, journals and vouchers; 12 report types
- Loyalty points, coupons and customer records

WORKS OFFLINE
Orders, payments, shifts, wastage and counts are saved on the device
and synced automatically when the connection returns.

BRAND IT YOURS
Name the app and pick a brand colour in Settings; the splash screen,
buttons and charts follow.

Demo accounts are listed on the sign-in screen (password: Password123).
Point the app at your own DT Restora server from the first-run setup.

## Assets (this folder)
- `icon-512.png` - 512x512 app icon
- `feature-graphic-1024x500.png` - feature graphic
- `01-splash.png` ... `09-orders.png`, `09-dashboard.png` - phone
  screenshots (1080x2400, 16:9 portrait)

## Suggested screenshots (pick 4-8)
05-pos, 06-order, 07-kitchen, 04-home, 09-dashboard, 08-orders

## Data safety form (suggested answers)
- Data collected: Personal info (name, email, phone - account), Financial
  info (purchase history - app functionality)
- Data transmitted: encrypted in transit (HTTPS)
- Data deleted: account data deleted on logout / app storage clear
- No ads, no data sharing with third parties

## Content rating
Questionnaire: no user-generated content visible to strangers, no
violence/gambling - expect Everyone rating.

## Privacy policy (host and link this)
DT Restora Demo stores your sign-in token, preferences (theme, sounds,
brand) and offline drafts on this device only. Order and payment data is
sent over HTTPS to the DT Restora server you configure in the app and is
handled under that restaurant's policies. No data is sold or shared with
third parties. Deleting the app or signing out removes account data from
the device.
