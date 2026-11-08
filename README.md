# DT Restora

**DT Restora** is a restaurant owner control system by **Datatech BD Ltd** (datatechbd.com) — _Software that runs business._ It gives owners financial visibility across branches (profit, recipe costing, wastage, inventory, accounting) and runs the customer side too: online ordering, loyalty, delivery tracking and delivery-app order intake. The interface is bilingual (English and Bangla).

This repository is a **monorepo**. The web application and the mobile application live side by side and share one set of documentation.

| Folder | What it is | Stack |
| ------ | ---------- | ----- |
| [`web-app/`](web-app/README.md) | Backend, admin panel, POS, public website and the `/api/v1` REST API | Laravel 13, PHP 8.3+, Inertia, Vue 3, Tailwind CSS v4 |
| [`flutter-app/`](flutter-app/README.md) | Staff and owner mobile app (Android and iOS) | Flutter 3, Dart 3 |
| [`docs/`](docs/README.md) | Shared specification: PRD, architecture, API, design, roadmap, deployment, marketing | Markdown |

The Flutter app is a client of the web app: it talks to `web-app` over `/api/v1` (see [`docs/api.md`](docs/api.md)). Change the API in `web-app/` and the client in `flutter-app/` in the same commit when they must move together.

## Repository layout

```text
dt-restora/
├── web-app/            Laravel application (own composer.json, package.json, Docker, tests)
├── flutter-app/        Flutter application (own pubspec.yaml, tests)
├── docs/               Shared documentation
├── .github/workflows/  web-app.yml and flutter-app.yml, each runs only for its own folder
├── Makefile            Shortcuts for both apps (run `make help`)
├── AGENTS.md           Instructions for AI coding agents (CLAUDE.md is the same file)
└── .editorconfig       Shared editor settings
```

Each app is self-contained: run its commands from inside its folder. Nothing in the repository root is needed to build or test either app.

## Requirements

| Tool | Version | Needed for |
| ---- | ------- | ---------- |
| PHP | 8.3+ | web-app |
| Composer | 2.x | web-app |
| Node.js and npm | 20+ | web-app assets |
| Flutter SDK | stable, Dart `^3.13` (see `flutter-app/pubspec.yaml`) | flutter-app |
| Docker and Docker Compose | optional | full web stack (MySQL, Redis, Reverb) |
| Android Studio or Xcode | optional | running the mobile app on an emulator or simulator |

## Quick start

Clone, then set up both apps:

```sh
git clone <repository-url> dt-restora
cd dt-restora
make setup
```

Or step by step.

**Web app** (SQLite, no Docker needed for a local run):

```sh
cd web-app
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed     # prints the admin password once
npm install
npm run build
php artisan serve              # http://localhost:8000
```

**Flutter app:**

```sh
cd flutter-app
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

`10.0.2.2` is how the Android emulator reaches the host machine. On the iOS simulator use `http://localhost:8000/api/v1`. On a phone on the same Wi-Fi use `http://<computer-ip>:8000/api/v1` and start the server with `php artisan serve --host=0.0.0.0`. The server address can also be entered inside the app on its setup screen.

## Common commands

Run `make help` for the full list. The most useful:

| Command | Does |
| ------- | ---- |
| `make setup` | Install dependencies for both apps |
| `make web-serve` | Start the Laravel server on port 8000 |
| `make web-dev` | Start the Vite dev server (hot reload) |
| `make web-test` | Pest tests for the web app |
| `make web-lint` | Pint (style check) and Larastan (static analysis) |
| `make web-build` | Production asset build |
| `make app-run` | Run the Flutter app (uses `API_BASE_URL`, default Android emulator address) |
| `make app-test` | Flutter analyze and tests |
| `make check` | Everything CI runs, for both apps |

## Documentation

Everything is indexed in [`docs/README.md`](docs/README.md). Start with:

- [`docs/prd.md`](docs/prd.md): product requirements and business rules
- [`docs/architecture.md`](docs/architecture.md): structure and coding rules
- [`docs/api.md`](docs/api.md): the API contract shared by both apps
- [`docs/roadmap.md`](docs/roadmap.md): build phases
- [`docs/production/deployment.md`](docs/production/deployment.md): deploying the web app
- [`docs/mobile/README.md`](docs/mobile/README.md): building and releasing the Flutter app

## Branches and deployment

- `main`: integration branch.
- `production/armanskitchen`: the live Arman's Kitchen site. Deploy it with `web-app/deploy.sh` on the server (see the deployment guide).
- `production/demo`: the public demo site.

CI runs per app. A change that only touches `flutter-app/` does not run the web app pipeline, and the other way round.

## Contributing

1. Work from the app's folder and follow its README.
2. Read [`AGENTS.md`](AGENTS.md) for the shared rules (no emojis anywhere, every text key in both `en` and `bn`, tests for every feature).
3. Run `make check` before committing.
4. Commit messages: a short imperative summary. Mention the app when only one is touched, for example `web-app: ...` or `flutter-app: ...`.

## Support

Datatech BD Ltd — datatechbd.com — sales@datatechbd.com — +880 1575-888775 (phone and WhatsApp).

## License

Proprietary. All rights reserved by Datatech BD Ltd. Not for redistribution.
