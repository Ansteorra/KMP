# KMP durable development context

Use this file as a compact orientation, not as a substitute for the applicable `AGENTS.md` chain or source inspection. Test counts, timings, deployment identifiers, and seeded data change frequently; read the current scripts and configuration instead of copying snapshots into documentation.

## Repository and runtime

- Application code is under `app/`. Run Composer, PHPUnit, npm, Vite, PHPStan, PHPCS, and Playwright commands from that directory unless a command explicitly says otherwise.
- The supported application runtime is PHP 8.4 with CakePHP 5.
- Local Docker Compose uses PostgreSQL 16. The dev container also installs PostgreSQL 16 and retains MariaDB tooling for compatibility work; new documentation and verification must not assume MySQL-only behavior.
- The root `dev-up.sh` and database-reset scripts can recreate or reseed databases. Read their help/source and obtain authorization before using state-changing options.
- Never expose `.env` values, tenant secrets, credentials, backup keys, or production data.

## Multi-tenant architecture

- Tenant selection is host-based and flows through `TenantResolutionMiddleware`, `TenantConnectionManager`, `TenantAwareCache`, and tenant-aware document-storage configuration.
- Local tenant examples are `http://kmp.localhost:8080` and `http://kmp2.localhost:8080`. The platform portal is `http://platform.kmp.localhost:8080/platform-admin`.
- Tenant database work must execute on the resolved tenant connection. Platform registry, provisioning, and fleet operations must execute on the platform connection.
- Treat tenant identity as part of every cache key, background job, document path, authorization scope, test setup, and operational command. Verify that one tenant cannot read or mutate another tenant's data.
- Seeded users and the shared local test password are development fixtures only. Recheck current seeds before relying on them and never reuse those credentials outside disposable local or POC environments.

## Application patterns

- Web controllers extend `AppController`; API controllers extend `ApiController`.
- Tables extend `BaseTable`, entities extend `BaseEntity`, and policies extend `BasePolicy`.
- Use CakePHP Authorization helpers and scopes rather than ad-hoc permission checks.
- Put workflows in `app/src/Services` or the owning plugin's `src/Services`.
- Integrate plugin navigation and UI through the established registries and view cells rather than hard-coding plugin behavior into core templates.
- Use `DataverseGridTrait` with `BaseGridColumns` for grid screens and `TimezoneHelper` for user-facing date conversion.

## Frontend

- Assets are built with Vite using `app/vite.config.js` and resolved in templates through `ViteHelper`.
- Stimulus controllers live in `*-controller.js` files and register through `window.Controllers`.
- Turbo Drive is disabled. Turbo Frames are used for targeted dynamic content; do not assume page-wide Turbo navigation.
- User-facing templates, CSS, forms, modals, tabs, grids, and Stimulus changes must preserve WCAG 2.2 Level AA.

## Verification

- `cd app && bash bin/verify.sh` is the broad local verification entry point. Read the script for its current suites and optional coverage or mutation flags.
- Prefer targeted PHPUnit and PHPCS for narrow PHP changes, Jest plus a Vite build for frontend behavior/import changes, and Playwright for browser flows.
- Playwright configuration is `app/playwright.config.cjs`. The supported lane wrapper is `app/bin/run-playwright-lane.sh`.
- Browser lanes and local startup helpers may reset seeded data. Do not run them against shared or production environments.
- Do not encode passing-test totals, baseline-error counts, or expected durations in durable documentation.

## Release and deployment

- `Push to dev` and `Do a release` are exact workflow phrases governed by `.github/skills/release-deploy/SKILL.md`.
- POC validation must use the exact changelog-bearing commit and immutable image digest that production later promotes.
- The direct nightly helper is separate from the official dev/POC/production release path. Read `deploy/azure/nightly-deploy.sh help` and the nightly skill before using it.
