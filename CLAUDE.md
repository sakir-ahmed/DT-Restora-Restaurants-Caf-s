<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

# Project Instructions

This is a Laravel 13 project built from the specification in `docs/`.

Read these files before writing any code, in this order:

1. `docs/prd.md`: what to build (roles, modules, business rules, scope)
2. `docs/architecture.md`: tech stack, folder structure, coding rules, Definition of Done
3. `docs/api.md`: API conventions, endpoints, payment gateway flows
4. `docs/design.md`: design tokens, components, icons, sizing, table rules, POS and receipt layout
5. `docs/roadmap.md`: build phases and acceptance criteria
6. `docs/ui-preview.html`: visual reference of the intended look (open in a browser; it is a static mockup, not production code)

## Rules

- Build **one phase at a time** from `docs/roadmap.md`. Never start the next phase until asked.
- If `prd.md` and `architecture.md` conflict, the PRD wins on business rules and roles; `architecture.md` wins on code structure.
- If Laravel Boost guidelines conflict with `docs/`, the `docs/` files win for product rules, structure and design. Boost guidelines apply to Laravel and package usage details.
- Never use emojis or Unicode symbol icons anywhere (UI, lang files, SMS, receipts, comments, commits). Use Font Awesome icons through `AppIcon`.
- Follow the compact control sizes and table alignment rules in `docs/design.md` (sections 23 to 25).
- Never use `window.alert()`, `confirm()` or `prompt()`; use the shared dialog components.
- If something is unclear or contradictory, ask before assuming.
- Write Pest tests for each feature and run them before saying a feature is finished.
- Run `./vendor/bin/pint` and `./vendor/bin/phpstan` before finishing.
