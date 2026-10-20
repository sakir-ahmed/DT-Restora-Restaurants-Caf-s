# Docker

How the DT Restora stack runs in Docker, and how logs are written and managed.

## Services

Defined in `docker-compose.yml`:

| Service    | Image / build                                  | Purpose                                            | Host port                    |
| ---------- | ---------------------------------------------- | -------------------------------------------------- | ---------------------------- |
| `app`      | built from `docker/Dockerfile` (PHP 8.3 FPM)   | Laravel application container (artisan, tinker)    | none (via nginx)             |
| `nginx`    | `nginx:1.27-alpine` with `docker/nginx.conf`   | Web server, forwards PHP to `app`                  | `${APP_PORT:-80}`            |
| `mysql`    | `mysql:8.4`                                    | Database (`dt_restora` / user `dt_restora`)        | none by default              |
| `redis`    | `redis:7-alpine`                               | Cache, sessions and queues                         | none by default              |
| `queue`    | built from `docker/Dockerfile`                 | `php artisan queue:work redis`                     | none                         |
| `scheduler`| built from `docker/Dockerfile`                 | `php artisan schedule:work`                        | none                         |
| `reverb`   | built from `docker/Dockerfile`                 | `php artisan reverb:start` (WebSockets)            | `8080`                       |

Every long-running service carries the shared `x-logging` anchor (see
[Docker log management](#docker-log-management)). `mysql` and `redis` have
healthchecks; `app`, `queue` and `scheduler` start only after they are healthy.

The local override file (below) adds: host ports for `mysql`/`redis`, a
`mailpit` service, and a `tools` profile with `adminer` and `redisinsight`.

## Quick start

```sh
cp .env.example .env
cp docker-compose.override.yml.example docker-compose.override.yml   # local overrides (git-ignored)
docker compose up -d --build

docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

Open `http://localhost` (or `http://localhost:${APP_PORT}` if you changed the
port). Reverb's WebSocket server listens on `http://localhost:8080`.

The override file is merged automatically by `docker compose up`. To run
production-like without it:

```sh
docker compose -f docker-compose.yml up -d
```

## Common commands

```sh
docker compose logs -f app                        # follow one service's logs (omit the name for all)
docker compose exec app php artisan migrate       # run migrations
docker compose exec app php artisan queue:restart # restart queue workers after code changes
docker compose exec app php artisan tinker        # interactive Laravel shell
docker compose up -d --build                      # rebuild images after Dockerfile / composer changes
docker compose down                               # stop everything, keep mysql/redis data
docker compose down -v                            # stop and delete the data volumes
docker compose --profile tools up -d              # start Adminer (8081) + RedisInsight (5540)
```

## Docker log management

All containers use the `json-file` log driver through the shared `x-logging`
anchor in `docker-compose.yml`:

```yaml
x-logging: &default-logging
  driver: json-file
  options:
    max-size: "10m"
    max-file: "3"
```

Each service keeps at most 3 rotated log files of 10 MB, so logs cannot fill
the disk. View them with Compose:

```sh
docker compose logs                # everything
docker compose logs -f app nginx   # follow selected services
docker compose logs --since 1h app # last hour only
```

`docker compose logs` reads the same files Docker stores on disk (find the path
with `docker inspect --format '{{.LogPath}}' <container>`); rotation happens in
the background and needs no manual cleanup.

## Application logging standard

How application logs are written (code-level source of truth:
`docs/architecture.md`, section 6).

**Where logs go** — set in `.env`:

```env
LOG_STACK=stderr,daily
LOG_DAILY_DAYS=14
```

- `stderr` sends every log to the container's stderr, so `docker compose logs
  app` shows the live application log.
- `daily` also writes dated files in `storage/logs/laravel-YYYY-MM-DD.log`,
  kept for 14 days (`LOG_DAILY_DAYS=14`).

**Level** — `LOG_LEVEL=debug` locally, `LOG_LEVEL=info` in production.

**How to write entries** — PSR-3 levels with a structured context array.
Never build messages with string interpolation; put the data in the context
array so it stays queryable:

```php
// Good
Log::warning('Payment gateway timeout', ['gateway' => $name, 'attempt' => $n]);
Log::info('Order created.', ['order_id' => $order->id, 'branch_id' => $order->branch_id]);

// Bad
Log::warning("Payment gateway {$name} timed out on attempt {$n}");
```

**Never log PII or customer data.** No passwords, OTPs, tokens, full
card/wallet numbers, and no customer names, phone numbers, addresses or order
contents in free-text logs. This follows the data rules in
`docs/architecture.md` sections 11 and 12. Log identifiers (`order_id`,
`customer_id`), not the data itself.

**Exceptions** — the exception handler already reports and logs exceptions with
stack traces; use `report($e)` / let the handler do it. Do not log the stack
trace in every message; pass `'exception' => $e` in context only when the
surrounding context genuinely adds information.

**Domain audit trail** — business-relevant changes (who changed what) go
through Spatie Activitylog (`LogsActivity` with `logOnly([...])`, or explicit
`activity()->causedBy($user)->performedOn($model)->log('order.voided')` for
sensitive actions), not through ad-hoc `Log::` calls. See
`docs/architecture.md` section 7.
