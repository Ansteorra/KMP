# KMP CakePHP application

This directory contains the Kingdom Management Portal runtime. It is a PHP 8.4
and CakePHP 5.4 application with first-party CakePHP plugins, a Vite-built
Stimulus/Bootstrap frontend, and PHPUnit, Jest, and Playwright coverage.

Use the repository-level Docker Compose environment for normal development; it
provides the platform database, two tenant databases, mail capture, and the
background worker. See the [root quick start](../README.md) and
[Docker development guide](../docs/docker-development.md).

## Application layout

| Path | Responsibility |
| --- | --- |
| `src/Controller` | Web and REST controllers |
| `src/Model` | CakePHP tables, entities, and behaviors |
| `src/Policy` | Authorization policies and query scopes |
| `src/Services` | Domain, platform, tenancy, workflow, and integration services |
| `src/KMP` | Shared domain helpers, tenant context, and grid definitions |
| `templates` | CakePHP layouts, pages, elements, and email templates |
| `assets` | Vite JavaScript and CSS sources |
| `plugins` | First-party and bundled CakePHP plugins |
| `config/Migrations` | Tenant application migrations |
| `config/PlatformMigrations` | Shared platform metadata migrations |
| `tests` | PHPUnit, Jest, Playwright BDD, fixtures, and test support |

## Common commands

Run these from `app/` inside the app container unless noted otherwise.

```bash
bash bin/verify.sh
vendor/bin/phpunit --testsuite core-unit
vendor/bin/phpunit --testsuite core-feature
vendor/bin/phpunit --testsuite plugins
npm run test:js
npm run dev
npm run test:ui
bin/cake routes
bin/cake tenant migrate --all --include-suspended --status
```

`npm run dev` is a development-mode Vite build, not a long-running dev server.
Vite writes hashed assets and `webroot/.vite/manifest.json`; CakePHP templates
resolve them through `App\View\Helper\ViteHelper`.

## Tenancy boundary

HTTP requests resolve their hostname through the platform database before the
application datasource is bound. Tenant work must run inside
`App\KMP\TenantContext` and use the dynamically scoped default datasource.
Business tables intentionally do not use a shared `tenant_id` discriminator.
Platform-only code uses the `platform` datasource and must not query tenant
business data directly.

Read the [multi-tenant architecture guide](../docs/3.1-multi-tenant-architecture.md)
before adding background work, caches, storage, mail configuration, migrations,
or platform-admin operations.

## Contribution contracts

Read [`AGENTS.md`](AGENTS.md) and the nearest child `AGENTS.md` before editing.
The detailed published guides start at [`../docs/index.md`](../docs/index.md),
and app-local implementation contracts live in [`docs/`](docs/).
