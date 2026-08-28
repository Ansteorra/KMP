---
layout: default
title: Getting started
description: Run and verify the supported local multi-tenant KMP development stack.
---

[← Documentation home](index.md)

# 2. Getting started

The supported local environment is Docker Compose. It runs PostgreSQL 16,
Apache/PHP, the application, the background scheduler, Mailpit, and pgAdmin.
The reset pipeline creates a platform database plus two independent tenant
databases so isolation can be tested locally.

## Prerequisites

Install:

- Docker Engine with the Compose plugin (Docker Desktop is suitable);
- Git; and
- a current browser.

PHP, Composer, Node, PostgreSQL, and browser-test dependencies run in the
containers. Installing them on the host is optional.

## Start the stack

From the repository root:

```bash
git clone <repository-url> kmp
cd kmp
./dev-up.sh --build
```

On first use, `dev-up.sh` copies `app/config/.env.example` to
`app/config/.env`. The sample is for local development only. The default startup
also runs `./dev-reset-db.sh --seed`; this is destructive to the local KMP
application, platform, and test databases.

Later starts normally need only:

```bash
./dev-up.sh
```

Set `KMP_RESET_DB_ON_UP=false` in `app/config/.env` when a start must preserve
the current local data.

## Local endpoints

| Service | Default URL |
| --- | --- |
| Primary tenant | `http://kmp.localhost:8080` |
| Second tenant | `http://kmp2.localhost:8080` |
| Platform administration | `http://platform.kmp.localhost:8080/platform-admin` |
| Mailpit | `http://localhost:8025` |
| pgAdmin | `http://localhost:5050` |

The platform administration portal is disabled by default. Enable it only when
you are intentionally working on platform operations. The `.localhost` names
normally resolve to loopback automatically; if the host does not support that,
add `kmp.localhost`, `kmp2.localhost`, and `platform.kmp.localhost` to the local
hosts file.

The seeded reset prints the available demo identities. Tenant member passwords
are reset to `TestPassword`. Use the login page on the same tenant host you are
testing; authentication state and data must not be assumed to cross hosts.

## Verify the tenant boundary

After startup:

1. Open both tenant URLs and confirm that each responds.
2. Change a harmless record in one tenant and confirm it is unchanged in the
   other tenant.
3. Request an unknown host and confirm KMP does not fall back to a default
   kingdom.
4. Tail the scheduler logs while exercising queued or scheduled work.

These checks catch accidental host aliases and connection leaks early. Do not
add a controller option, form field, or query parameter that selects a tenant.

## Common commands

Run application commands from `app/` inside the app container:

```bash
docker compose exec app bash
composer test
npm run test:js
npm run dev
bash bin/verify.sh
bin/cake routes
```

Or invoke a single command without an interactive shell:

```bash
docker compose exec app bin/cake tenant migrate --all --include-suspended --fail-fast
docker compose exec app vendor/bin/phpunit --testsuite core-unit
```

Useful host-side commands:

```bash
docker compose logs -f app
docker compose logs -f scheduler
./dev-reset-db.sh --seed
./dev-down.sh
```

`dev-reset-db.sh --seed` rebuilds the baseline tenant, platform, second-tenant,
and test databases. Stop background services before database surgery outside
this script; the script already coordinates them during its own reset.

## Frontend development

Vite owns JavaScript and CSS builds. Source files live under `app/assets`; the
CakePHP `ViteHelper` reads `app/webroot/.vite/manifest.json`.

```bash
docker compose exec app npm run dev
docker compose exec app npm run test:js
```

Do not add Laravel Mix, Webpack, or `AssetMix` references. Turbo Drive is
intentionally disabled; Turbo Frames are used for selected dynamic regions.

## Debugging startup

Check service state and logs first:

```bash
docker compose ps
docker compose logs app
docker compose logs db
docker compose logs scheduler
```

Common causes are a port already in use, an old fixed-name KMP container from a
different checkout, an edited `.env`, or a failed database reset. `dev-up.sh`
removes conflicting KMP development containers after identifying them, but it
does not delete the named data volumes.

Xdebug defaults are in `app/config/.env.example`. Configure the IDE to listen on
port `9003` with server name `KMP-Docker` when step debugging is needed.

## What to read next

- [Configuration](2-configuration.md)
- [Architecture](3-architecture.md)
- [Multi-tenant architecture](3.1-multi-tenant-architecture.md)
- [Development workflow](7-development-workflow.md)
- [Docker development details](docker-development.md)
