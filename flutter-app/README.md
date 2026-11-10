# DT Restora Mobile App

Flutter app for staff and owners (POS, kitchen, orders, delivery, inventory, accounting, reports). Part of the [DT Restora monorepo](../README.md); it talks to the Laravel backend in [`../web-app`](../web-app/README.md) over `/api/v1`.

## Run

```sh
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

`10.0.2.2` reaches the host machine from the Android emulator. Other targets and release builds: [`../docs/mobile/README.md`](../docs/mobile/README.md).

## Check

```sh
flutter analyze
flutter test
```

## Structure

```text
lib/
├── main.dart
├── core/       API client, config, app state, local database, offline queue, theme, PDF helpers
├── features/   One folder per module (pos, orders, delivery, inventory, accounting, reports, ...)
└── ui/         Shared widgets
test/           Unit and widget tests
assets/         Images and fonts
android/, ios/  Platform projects
dist/           Local release packages (git-ignored)

```

## Notes

- The server address is set on the first-run setup screen or with `--dart-define=API_BASE_URL=...`; the default is in `lib/core/config.dart`.
- Requirements: Flutter stable with Dart `^3.13` (see `pubspec.yaml`).
- Shared rules and the API contract: [`../docs/`](../docs/README.md).
