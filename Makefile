# DT Restora monorepo shortcuts. Run `make help`.
WEB := web-app
APP := flutter-app
API_BASE_URL ?= http://10.0.2.2:8000/api/v1

.DEFAULT_GOAL := help
.PHONY: help setup web-serve web-dev web-test web-lint web-build app-run app-test check

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-12s %s\n", $$1, $$2}'

setup: ## Install dependencies for both apps
	cd $(WEB) && composer install && ([ -f .env ] || cp .env.example .env) && php artisan key:generate --no-interaction && npm install
	cd $(APP) && flutter pub get

web-serve: ## Start the Laravel server on port 8000
	cd $(WEB) && php artisan serve --port=8000

web-dev: ## Start the Vite dev server
	cd $(WEB) && npm run dev

web-test: ## Run the Pest tests
	cd $(WEB) && php artisan test

web-lint: ## Pint (check only) and Larastan
	cd $(WEB) && vendor/bin/pint --test && vendor/bin/phpstan analyse --memory-limit=1G

web-build: ## Production asset build
	cd $(WEB) && npm run build

app-run: ## Run the Flutter app (override with API_BASE_URL=...)
	cd $(APP) && flutter run --dart-define=API_BASE_URL=$(API_BASE_URL)

app-test: ## Flutter analyze and tests
	cd $(APP) && flutter analyze && flutter test

check: web-lint web-test web-build app-test ## Everything CI runs
