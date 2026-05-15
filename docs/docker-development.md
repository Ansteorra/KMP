# Docker Development Environment

Local Docker Compose is the default KMP development workflow. The source tree stays on your machine and is bind-mounted into the app container, so PHP, template, CSS, and JavaScript source changes are visible without rebuilding the image.

## Quick Start

```bash
./dev-up.sh --build
```

If `app/config/.env` does not exist yet, `./dev-up.sh` creates it from `app/config/.env.example`. Before startup it also removes stale `kmp-*` containers and containers publishing the configured local dev ports, so the current worktree owns the active stack. After the app is healthy, it runs `./dev-reset-db.sh --seed` by default so the database matches the current code and seeded dev users. After the first build, use `./dev-up.sh` for normal startup. Stop the stack with `./dev-down.sh`; add `--volumes` only when you intentionally want to delete the local database volume.

## Architecture

| Service | Container | Purpose | Default host access |
|---------|-----------|---------|---------------------|
| `app` | `kmp-app` | PHP 8.3, Apache, Composer, Node, Xdebug, cron | http://localhost:8080 |
| `db` | `kmp-db` | MariaDB 11 | `127.0.0.1:3306` |
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
127.0.0.1 kmp.localhost admin.kmp.localhost
```

Then list those aliases in `app/config/.env` so `./dev-up.sh` prints them:

```bash
KMP_HOST_ALIASES="kmp.localhost admin.kmp.localhost"
```

With the default app port, the aliases are available at:

```text
http://kmp.localhost:8080
http://admin.kmp.localhost:8080
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
./dev-reset-db.sh --seed
docker compose exec db mysql -uKMPSQLDEV -pP@ssw0rd KMP_DEV
docker compose logs db
```

The database volume is preserved across `./dev-down.sh`. To start from an empty volume:

```bash
./dev-down.sh --volumes
./dev-up.sh
```

## Queue and Scheduled Jobs

Local Docker starts cron inside the app container by default. The cron entries keep queue processing and scheduled application maintenance active:

| Schedule | Command | Purpose |
|----------|---------|---------|
| Every 2 minutes | `bin/cake queue run -q` | Process queued jobs |
| Every minute | `bin/cake workflow_scheduler` | Dispatch scheduled workflow triggers |
| Every 15 minutes | `bin/cake sync_active_window_statuses` | Move active-window records between Upcoming, Current, and Expired |
| Daily | `bin/cake sync_member_warrantable_statuses` | Recompute warrant eligibility |
| Daily | `bin/cake age_up_members` | Transition youth members to adult statuses |
| Daily | `bin/cake backup_check` | Run scheduled backups when enabled in app settings |

Inspect cron output with:

```bash
docker compose exec app tail -f /var/log/cron.log
```

Disable local cron temporarily by setting this in `app/config/.env` before starting the stack:

```bash
KMP_SKIP_CRON=true
```

Manual runs use the same container-first pattern:

```bash
docker compose exec app bin/cake queue run -q
docker compose exec app bin/cake workflow_scheduler --dry-run
docker compose exec app bin/cake sync_active_window_statuses --dry-run
docker compose exec app bin/cake sync_member_warrantable_statuses --dry-run
docker compose exec app bin/cake age_up_members --dry-run
docker compose exec app bin/cake backup_check
```

## Configuration

The local helper scripts use `app/config/.env` for Docker Compose and the application. `./dev-up.sh` creates it from `app/config/.env.example` when it is missing.

| Variable | Default | Description |
|----------|---------|-------------|
| `KMP_APP_PORT` | `8080` | Host port for the app |
| `KMP_DB_HOST_PORT` | `3306` | Host port for MariaDB |
| `KMP_MAILPIT_WEB_PORT` | `8025` | Host port for Mailpit UI |
| `KMP_MAILPIT_SMTP_PORT` | `1025` | Host port for Mailpit SMTP |
| `KMP_HOST_ALIASES` | `kmp.localhost admin.kmp.localhost` | Space-separated host aliases printed by `./dev-up.sh` |
| `MYSQL_DB_NAME` | `KMP_DEV` | Development database name |
| `MYSQL_USERNAME` | `KMPSQLDEV` | Development database user |
| `MYSQL_PASSWORD` | `P@ssw0rd` | Development database password |
| `XDEBUG_MODE` | `debug,develop` | Runtime Xdebug mode |
| `KMP_SKIP_CRON` | `false` | Disable local cron setup when `true` |
| `KMP_RESET_DB_ON_UP` | `true` | Run `dev-reset-db.sh` after the app becomes healthy |
| `KMP_RESET_DB_ON_UP_ARGS` | `--seed` | Arguments passed to `dev-reset-db.sh` during startup |

## Troubleshooting

### Container won't start

```bash
docker compose logs app
docker compose logs db
docker compose down -v
./dev-up.sh --build
```

### Wrong worktree is serving locally

Run `./dev-up.sh` from the worktree you want active. It removes existing `kmp-app`, `kmp-db`, and `kmp-mailpit` containers and any running containers publishing the configured app, database, or Mailpit ports before starting the current stack.

### Database connection issues

```bash
docker compose ps
docker compose logs db
docker compose exec db mysql -uroot -prootpassword -e "SHOW DATABASES;"
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

docker-compose.yml      # Local service definitions
dev-up.sh               # Start environment
dev-down.sh             # Stop environment
dev-reset-db.sh         # Reset database
app/config/.env.example # Environment template for app and local Docker
```
