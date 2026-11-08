# DT Restora monorepo: agent instructions

This repository holds two applications and shared documentation:

- `web-app/`: Laravel 13 backend, admin SPA, public website, `/api/v1`. Its own detailed instructions are in `web-app/AGENTS.md` (Laravel Boost guidelines). Read that file before touching PHP, Vue or tests.
- `flutter-app/`: Flutter mobile app, a client of the web-app API.
- `docs/`: shared specification (see `docs/README.md`).

## Rules

- Run commands from inside the app folder you are changing (`cd web-app` or `cd flutter-app`). The repository root has no application code.
- Specification first: `docs/prd.md`, `docs/architecture.md`, `docs/api.md`, `docs/design.md`, `docs/roadmap.md`. The PRD wins on business rules, `architecture.md` on code structure.
- The API contract is `docs/api.md` plus the OpenAPI output of `web-app`. When an endpoint changes, update the Flutter client and the docs in the same change.
- Never use emojis or Unicode symbol icons anywhere (UI, lang files, SMS, receipts, comments, commits).
- Every user-facing text exists in both English and Bangla (`web-app/lang/en`, `web-app/lang/bn`).
- Web: run `./vendor/bin/pint`, `./vendor/bin/phpstan` and `php artisan test` before finishing. Flutter: run `flutter analyze` and `flutter test`. `make check` runs all of it.
- Do not commit `.env`, build output (`flutter-app/build`, `flutter-app/dist`, `web-app/public/build`) or dependency folders.
- Ask before assuming when something is unclear or contradictory.
