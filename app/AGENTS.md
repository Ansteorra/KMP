# KMP application guide

## Purpose

Own the CakePHP application runtime: PHP source, configuration, templates, frontend assets, plugins, tests, and app-local documentation. Use this guide with the root `AGENTS.md` for any work under `app/`.

## Ownership

- `app/config` controls app routes, plugin loading, migrations configuration, and environment-facing defaults.
- `app/src` owns core CakePHP controllers, models, policies, services, mailers, commands, middleware, cells, and KMP helpers.
- `app/templates` owns user-facing CakePHP markup and reusable elements.
- `app/assets` owns Vite-bundled JavaScript and CSS source.
- `app/plugins` owns first-party and bundled plugin code.
- `app/tests` owns PHPUnit, Jest, and Playwright coverage.
- `app/docs` owns app-local implementation notes that are not part of the published docs site.

## Local Contracts

- Run application commands from `app/` unless a child guide says otherwise.
- Keep CakePHP behavior in the proper layer: controllers orchestrate, services hold workflows, tables/entities hold persistence behavior, policies hold authorization, and templates render.
- Store date/time values in UTC and convert or pre-format for display through project timezone helpers.
- Use Vite (`vite.config.js`) for frontend bundles and the generated manifest in `webroot/.vite/manifest.json`.
- Keep plugin behavior isolated in the plugin unless a shared abstraction belongs in core `app/src`.
- Runtime database roles are DML-only. Schema/provisioning/restore work requires a dedicated CLI `KMP_ADMIN_JOB` with separate administrative URLs; ordinary queue workers leave platform operational jobs for the administrative runner.
- Release deployment first runs `platform secrets import-env` and backup-key
  reconciliation, then uses `tenant migrate --all --include-suspended
  --fail-fast`; the import is missing-only so a stale environment value cannot
  overwrite a rotated database-backed secret. The tenant command inspects every
  core/plugin history, skips current databases without backups, and verifies pending tenants after migration.
- Suspended tenants must remain migration-current and cannot be reactivated when their recorded schema is behind the
  migration target shipped by the running code.
- Do not commit runtime output from `tmp/`, `logs/`, generated reports, built caches, or local environment files.

## Work Guidance

1. Read this guide and the nearest child guide before editing under `app/`.
2. Search for existing CakePHP, Stimulus, grid, service, and plugin patterns before adding new abstractions.
3. Update app-local documentation when durable commands, contracts, workflow behavior, or extension points change.
4. Keep generated frontend output out of source edits unless the project explicitly tracks the generated artifact being changed.

## Verification

| Need | Command |
| --- | --- |
| Full app verification | `bash bin/verify.sh` |
| PHP regression suite | `composer test` |
| Targeted PHPUnit | `vendor/bin/phpunit --testsuite core-unit`, `core-feature`, or `plugins` |
| JavaScript unit tests | `npm run test:js` |
| Markdown and JSDoc integrity | `npm run docs:check && npm run docs:js:check` |
| PHP API docblocks | `composer docs:php` |
| Vite build | `npm run dev` |
| Playwright UAT lane | `npm run test:ui` |

## Child AGENTS index

| Path | Purpose |
| --- | --- |
| `app/config/AGENTS.md` | Routes, plugin activation, migrations configuration, and environment-facing app configuration |
| `app/src/AGENTS.md` | Core CakePHP source boundaries and backend development contracts |
| `app/templates/AGENTS.md` | CakePHP views, layouts, elements, Bootstrap, Turbo Frames, and accessibility |
| `app/assets/AGENTS.md` | Vite build, frontend entrypoints, CSS, asset output, and frontend dependencies |
| `app/plugins/AGENTS.md` | Plugin structure, registration, migration order, tests, and active plugin child guides |
| `app/tests/AGENTS.md` | PHPUnit, Jest, Playwright, fixtures, generated test output, and verification workflows |
| `app/docs/AGENTS.md` | App-local implementation notes and developer documentation |
