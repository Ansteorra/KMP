# Docker Development Environment

Local Docker Compose is the default KMP development workflow. The source tree stays on your machine and is bind-mounted into the app container, so PHP, template, CSS, and JavaScript source changes are visible without rebuilding the image.

## Quick Start

```bash
./dev-up.sh --build
```

If `app/config/.env` does not exist yet, `./dev-up.sh` creates it from `app/config/.env.example`. Before startup it also removes stale `kmp-*` containers and containers publishing the configured local dev ports, so the current worktree owns the active stack. After the app is healthy, it runs `./dev-reset-db.sh` by default so the database matches the current code. Set `KMP_RESET_DB_ON_UP_ARGS=--seed` when you want seeded dev users. After the first build, use `./dev-up.sh` for normal startup. Stop the stack with `./dev-down.sh`; add `--volumes` only when you intentionally want to delete the local database and pgAdmin volumes.

## Architecture

| Service | Container | Purpose | Default host access |
|---------|-----------|---------|---------------------|
| `app` | `kmp-app` | PHP 8.3, Apache, Composer, Node, Xdebug | http://kmp.localhost:8080 |
| `worker` | `kmp-worker` | Queue worker using the same app image and database config | Docker logs |
| `scheduler` | `kmp-scheduler` | Local scheduled-task loop using the same app image and database config | Docker logs |
| `db` | `kmp-db` | PostgreSQL 16 | `127.0.0.1:5432` |
| `pgadmin` | `kmp-pgadmin` | PostgreSQL administration UI for local development | http://localhost:5050 |
| `mailpit` | `kmp-mailpit` | Local email capture | http://localhost:8025, SMTP `127.0.0.1:1025` |

All host ports are configurable in `app/config/.env` and bind to `127.0.0.1` by default.

## Source and Dependency Workflow

The app source is mounted from `./app` to `/var/www/html`. Run project commands inside the app container so the host machine does not need PHP, Composer, Node, or npm packages installed:

```bash
docker compose exec app bash
docker compose exec app composer install
docker compose exec app bin/cake migrations status
docker compose exec app npm install
docker compose exec app npm run build
docker compose exec app vendor/bin/phpunit
```

`node_modules` is stored in a Docker volume. This keeps dependencies out of the host checkout; use container commands for Vite, Jest, Playwright, and other npm-based tools.

## Local Host Aliases

The app container accepts any HTTP `Host` header. To test multiple local hostnames, add aliases to `/etc/hosts`:

```text
127.0.0.1 kmp.localhost kmp2.localhost platform.kmp.localhost
```

Then list those aliases in `app/config/.env` so `./dev-up.sh` prints them:

```bash
KMP_HOST_ALIASES="kmp.localhost kmp2.localhost platform.kmp.localhost"
```

With the default app port, the aliases are available at:

```text
http://kmp.localhost:8080
http://kmp2.localhost:8080
http://platform.kmp.localhost:8080
```

If a feature generates absolute URLs, review the relevant CakePHP settings such as `App.fullBaseUrl`, cookie domain, CSRF, CORS, and callback URLs for the hostname being tested.

## Xdebug

Xdebug is enabled in the app container and listens back to the host on port `9003`.

| Setting | Default |
|---------|---------|
| `XDEBUG_MODE` | `debug,develop` |
| `XDEBUG_CLIENT_HOST` | `host.docker.internal` |
| `XDEBUG_CLIENT_PORT` | `9003` |
| `PHP_IDE_SERVER_NAME` | `KMP-Docker` |

The compose stack adds `host.docker.internal:host-gateway`, so Xdebug works on Linux as well as Docker Desktop. VS Code is configured with this path mapping:

```json
"/var/www/html": "${workspaceFolder}/app"
```

The image uses `xdebug.start_with_request=trigger`. Start a debug session by listening on port `9003` in your IDE and setting an `XDEBUG_TRIGGER` cookie/query parameter, or by using an Xdebug browser extension.

## Database Management

```bash
./dev-reset-db.sh
docker compose exec db psql -UKMPSQLDEV -d KMP_DEV
docker compose exec db psql -UKMPSQLDEV -d KMP_PLATFORM
docker compose logs db
```

pgAdmin is included as a development-only sidecar for browser-based PostgreSQL
inspection:

```text
URL:      http://localhost:5050
Email:    admin@kmpdev.org
Password: kmpdevpass
```

The sidecar imports `docker/pgadmin/servers.json` on startup and preconfigures
`KMP Development` (`KMP_DEV`) and `KMP Platform` (`KMP_PLATFORM`) under the
`KMP Local` group. Both use host `db`, port `5432`, username `KMPSQLDEV`, and
the local `POSTGRES_PASSWORD` value. These are local development defaults only;
do not use them for production.

The database volume is preserved across `./dev-down.sh`. To start from an empty volume:

```bash
./dev-down.sh --volumes
./dev-up.sh
```

`./dev-reset-db.sh --seed` loads the PostgreSQL baseline seed from
`app/tests/pg_seed_baseline.sql`, then runs the remaining migrations. This
matches the production-style flow where a known historical database snapshot is
restored before current migrations are applied. At the end of each reset, the
script rebuilds `KMP_DEV_test` from the reset development schema so PHPUnit can
run immediately.

Platform metadata uses a separate PostgreSQL database and migration track:

```bash
docker compose exec app bin/cake platform_migrate
docker compose exec app bin/cake platform_migrate status
docker compose exec app bin/cake platform_migrate rollback
```

`./dev-reset-db.sh` also recreates and migrates the local platform database, then
registers local tenant metadata. By default it creates an active `kmp` tenant
mapped to `kmp.localhost`, using `KMP_DEV` as the baseline tenant database, and
provisions an active `kmp2` tenant through `bin/cake tenant provision` at
`kmp2.localhost`, using `kmp2_dev` as an isolated second-kingdom database. Seeded
resets (`./dev-reset-db.sh --seed`) load the second database from the current
baseline schema/data, then prune it to four standard demo users with
`@amp2demo.com` login emails and no gatherings. Override `KMP_DEV_TENANT_*` in
`app/config/.env` only when testing a different baseline tenant identity; future
tenant creation should go through the platform tenant provisioning flow rather
than new environment variables.

The reset also seeds a local Platform Admin account so dev logins are stable
after every reset:

```text
URL:         http://platform.kmp.localhost:8080/platform-admin/login
Email:       admin@example.org
Password:    TestPassword
TOTP secret: QJR6QMYZYRHDZCOK5STD
```

Override `KMP_DEV_PLATFORM_ADMIN_EMAIL`, `KMP_DEV_PLATFORM_ADMIN_PASSWORD`, or
`KMP_DEV_PLATFORM_ADMIN_TOTP_SECRET` in `app/config/.env` only when you need a
different local-only platform admin identity.

Local Docker auto-creates `KMP_PLATFORM` and `KMP_PLATFORM_test` when
`KMP_AUTO_CREATE_DATABASES=true`. Production deployments should create platform
databases through infrastructure automation and leave auto-create disabled.

### Regenerate PostgreSQL Schema Dumps

After the PostgreSQL dev stack is healthy and migrations have been applied,
refresh the committed schema-only dumps with:

```bash
docker compose exec -T db pg_dump -UKMPSQLDEV --schema-only --no-owner --no-privileges KMP_DEV > app/config/schema_dump.sql
cp app/config/schema_dump.sql app/tests/kmp_sql.sql
```

Do not hand-edit or fake these dumps. If the local PostgreSQL stack cannot be
started safely, leave the existing dumps unchanged and report the blocker.

## Queue and Scheduled Jobs

Local Docker runs background work in dedicated services instead of cron inside
the web container. This mirrors the production shape where web, worker, and
scheduler processes are independently observable and restartable.

| Service | Command | Purpose |
|----------|---------|---------|
| `worker` | `bin/cake queue run -q` | Process queued jobs, then restart cleanly via Docker Compose when the configured queue worker runtime ends |
| `scheduler` | `kmp-scheduler-loop` | Run scheduled maintenance commands on configurable intervals |

The scheduler loop runs these commands with conservative local intervals:

| Variable | Default seconds | Command |
|----------|-----------------|---------|
| `KMP_WORKFLOW_SCHEDULER_INTERVAL` | `60` | `bin/cake workflow_scheduler` |
| `KMP_ACTIVE_WINDOW_SYNC_INTERVAL` | `900` | `bin/cake sync_active_window_statuses` |
| `KMP_MEMBER_WARRANTABLE_SYNC_INTERVAL` | `86400` | `bin/cake sync_member_warrantable_statuses` |
| `KMP_AGE_UP_MEMBERS_INTERVAL` | `86400` | `bin/cake age_up_members` |
| `KMP_BACKUP_CHECK_INTERVAL` | `86400` | `bin/cake backup_check` |

Inspect background output with:

```bash
docker compose logs -f worker
docker compose logs -f scheduler
```

`KMP_SKIP_CRON=true` is set for the Compose app/worker/scheduler services so
the old in-container cron path does not duplicate background work. Manual runs
use the same container-first pattern:

```bash
docker compose exec app bin/cake queue run -q
docker compose exec app bin/cake workflow_scheduler
docker compose exec app bin/cake sync_active_window_statuses
docker compose exec app bin/cake sync_member_warrantable_statuses
docker compose exec app bin/cake age_up_members
docker compose exec app bin/cake backup_check
```

## Configuration

The local helper scripts use `app/config/.env` for Docker Compose and the application. `./dev-up.sh` creates it from `app/config/.env.example` when it is missing.

| Variable | Default | Description |
|----------|---------|-------------|
| `KMP_APP_URL` | `http://kmp.localhost:8080` | Primary local app URL used by `./dev-up.sh` health checks and output |
| `KMP_APP_PORT` | `8080` | Host port for the app |
| `KMP_DB_HOST_PORT` | `5432` | Host port for PostgreSQL |
| `KMP_PGADMIN_BIND_ADDR` | `127.0.0.1` | Bind address for pgAdmin |
| `KMP_PGADMIN_PORT` | `5050` | Host port for pgAdmin |
| `KMP_PGADMIN_EMAIL` | `admin@kmpdev.org` | Local pgAdmin login email |
| `KMP_PGADMIN_PASSWORD` | `kmpdevpass` | Local pgAdmin login password |
| `KMP_MAILPIT_WEB_PORT` | `8025` | Host port for Mailpit UI |
| `KMP_MAILPIT_SMTP_PORT` | `1025` | Host port for Mailpit SMTP |
| `KMP_HOST_ALIASES` | `kmp.localhost kmp2.localhost platform.kmp.localhost` | Space-separated host aliases printed by `./dev-up.sh` |
| `KMP_DB_DRIVER` | `postgres` | CakePHP database driver |
| `POSTGRES_DB` / `DB_DATABASE` | `KMP_DEV` | Development database name |
| `POSTGRES_USER` / `DB_USERNAME` | `KMPSQLDEV` | Development database user |
| `POSTGRES_PASSWORD` / `DB_PASSWORD` | `kmpdevpass` | Development database password |
| `DATABASE_URL` | `postgres://KMPSQLDEV:kmpdevpass@db:5432/KMP_DEV` | App database DSN |
| `DATABASE_TEST_URL` | `postgres://KMPSQLDEV:kmpdevpass@db:5432/KMP_DEV_test` | Test database DSN |
| `PLATFORM_DB_DATABASE` | `KMP_PLATFORM` | Platform metadata database name |
| `PLATFORM_DATABASE_URL` | `postgres://KMPSQLDEV:kmpdevpass@db:5432/KMP_PLATFORM` | Platform metadata database DSN |
| `PLATFORM_DATABASE_TEST_URL` | `postgres://KMPSQLDEV:kmpdevpass@db:5432/KMP_PLATFORM_test` | Test platform metadata database DSN |
| `KMP_AUTO_CREATE_DATABASES` | `true` | Local-only database auto-create guard used by the container entrypoint |
| `KMP_TENANCY_ENABLED` | `true` | Enable host-based tenant resolution for local tenant testing |
| `KMP_DEV_TENANT_SLUG` | `kmp` | Local baseline tenant slug registered during database reset |
| `KMP_DEV_TENANT_DISPLAY_NAME` | `KMP Development` | Local baseline tenant display name |
| `KMP_DEV_TENANT_HOST` | `kmp.localhost` | Host mapped to the local baseline tenant |
| `XDEBUG_MODE` | `debug,develop` | Runtime Xdebug mode |
| `KMP_SKIP_CRON` | `true` | Disable legacy cron setup; Compose worker/scheduler services own background work |
| `KMP_*_INTERVAL` | See Queue and Scheduled Jobs | Scheduler loop intervals for local background commands |
| `KMP_RESET_DB_ON_UP` | `true` | Run `dev-reset-db.sh` after the app becomes healthy |
| `KMP_RESET_DB_ON_UP_ARGS` | empty | Arguments passed to `dev-reset-db.sh` during startup |

## Troubleshooting

### Container won't start

```bash
docker compose logs app
docker compose logs db
docker compose down -v
./dev-up.sh --build
```

### Wrong worktree is serving locally

Run `./dev-up.sh` from the worktree you want active. It removes existing
`kmp-app`, `kmp-worker`, `kmp-scheduler`, `kmp-db`, `kmp-pgadmin`, and `kmp-mailpit`
containers and any running containers publishing the configured app, database,
pgAdmin, or Mailpit ports before starting the current stack.

### Database connection issues

```bash
docker compose ps
docker compose logs db
docker compose exec db psql -UKMPSQLDEV -d KMP_DEV -c "\\dt"
```

### Permission issues

```bash
docker compose exec app chown -R www-data:www-data /var/www/html/logs /var/www/html/tmp
docker compose exec app chmod -R 775 /var/www/html/logs /var/www/html/tmp
```

### Clear all caches

```bash
docker compose exec app bin/cake cache clear_all
```

## Devcontainer Notes

The `.devcontainer/` configuration remains available as optional legacy tooling, but Docker Compose is the default local development path. Do not run the devcontainer and the root Docker Compose stack at the same time unless you have changed ports; they can compete for the same host bindings.

## File Structure

```text
docker/
|-- Dockerfile.app      # PHP/Apache development container
|-- apache-vhost.conf   # Apache configuration
|-- app_local.php       # CakePHP config copied by the entrypoint
|-- entrypoint.sh       # Container initialization and cron setup
|-- scheduler-loop.sh   # Local scheduler service loop

docker-compose.yml      # Local service definitions
dev-up.sh               # Start environment
dev-down.sh             # Stop environment
dev-reset-db.sh         # Reset database
app/config/.env.example # Environment template for app and local Docker
```
