# Mobile app (Flutter)

The staff and owner app in `flutter-app/`. It is a client of the web app's `/api/v1` API (see [api.md](api.md)) and keeps working offline: orders are queued in a local SQLite database and synced when the connection returns.

All commands run from `flutter-app/`.

## Setup

```sh
flutter pub get
flutter doctor      # confirm the toolchain
```

## Choosing the server

The API address is read in this order: the address saved in the app (setup screen) or the `API_BASE_URL` build define, then the default in `lib/core/config.dart` (`https://dt-restora.datatechbd.com/api/v1`).

| Where the app runs | `API_BASE_URL` |
| ------------------ | -------------- |
| Android emulator | `http://10.0.2.2:8000/api/v1` |
| iOS simulator | `http://localhost:8000/api/v1` |
| Phone on the same Wi-Fi | `http://<computer-ip>:8000/api/v1` (run `php artisan serve --host=0.0.0.0` in `web-app/`) |
| Live server | `https://<your-domain>/api/v1` |

```sh
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

To get a working local backend, start `web-app` first (see [../README.md](../README.md)) and note the admin password printed by `php artisan migrate --seed`.

## Quality checks

```sh
flutter analyze
flutter test
```

## Releases

Build output goes to `build/`. Signed packages that are kept for distribution live in `dist/`; both folders are git-ignored.

```sh
flutter build apk --release --dart-define=API_BASE_URL=https://<your-domain>/api/v1
flutter build appbundle --release --dart-define=API_BASE_URL=https://<your-domain>/api/v1
```

Bump `version:` in `pubspec.yaml` (`1.1.0+3` is version name `1.1.0`, build number `3`) before every store upload. The Play Store listing text and screenshots are in `flutter-app/dist/playstore/` (local copy), the privacy page is `flutter-app/docs/privacy.html`.

## Keeping web and mobile in step

- An endpoint change in `web-app/` that is not backward compatible needs the Flutter change in the same commit.
- Mobile clients may be older than the server. Add API fields, do not remove or rename them without a version bump.
- Order and payment requests send an `Idempotency-Key`; keep that when touching the sync code.
