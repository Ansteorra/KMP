# Application test suite

This is the app-local quick reference. The maintained testing contract, including when each
layer is required, lives in [`../../docs/7.3-testing-infrastructure.md`](../../docs/7.3-testing-infrastructure.md).
Run commands from `app/`.

## Test layers

| Layer | Locations | Primary command |
| --- | --- | --- |
| Core unit/service | `tests/TestCase/Core/Unit`, `Model`, `Services`, `KMP` | `vendor/bin/phpunit --testsuite core-unit` |
| Core HTTP/feature | `tests/TestCase/Core/Feature`, `Controller`, `Command`, `Middleware`, `View` | `vendor/bin/phpunit --testsuite core-feature` |
| Plugins | `tests/TestCase/Plugins`, `plugins/*/tests/TestCase` | `vendor/bin/phpunit --testsuite plugins` |
| JavaScript | `tests/js` | `npm run test:js` |
| Browser journeys | `tests/ui` and generated Playwright-BDD specs | see lanes below |

Use the project harnesses:

- `tests/TestCase/BaseTestCase.php` for seeded database tests and transaction cleanup.
- `tests/TestCase/Support/HttpIntegrationTestCase.php` for controller/route behavior.
- `tests/TestCase/Support/PluginIntegrationTestCase.php` for plugin HTTP behavior.
- `tests/ui/support/ui-helpers.cjs` and `tenant-context.cjs` for browser setup.

Prefer constants from `BaseTestCase` and semantic lookups over raw IDs. Call
`reseedDatabase()` only when a test cannot be isolated by the normal transaction boundary.

## Multi-tenant coverage

The ordinary PHPUnit bootstrap aliases the `test` datasource to `default` and disables HTTP
tenant resolution. That is suitable for most tenant-domain unit and feature tests, but it does
not prove isolation by itself.

Changes to tenancy must separately cover the relevant boundary:

- middleware resolution and fail-closed status handling;
- physical connection switching and cleanup in `TenantConnectionManager`;
- platform versus tenant datasource ownership;
- host-bound browser contexts for `kmp.localhost` and `kmp2.localhost`;
- positive visibility in one tenant and negative visibility in the other;
- platform provisioning through `npm run test:ui:platform-provisioning` when that flow changes.

Never simulate tenant selection with a request field or a `tenant_id` predicate. Tests should
exercise host resolution or explicitly enter a tenant connection/context using the same
service boundary as production.

## Standard verification

```bash
bash bin/verify.sh
```

The standard script runs all three PHPUnit suites, the skipped-test budget, seed snapshot
contracts, Jest, the Vite development build, the Azure runtime contract, PHPCS on changed PHP,
and PHPStan. It does not run Playwright.

Browser lanes are intentionally separate and share the local Docker stack, so run one at a
time:

```bash
npm run test:ui:smoke
npm run test:ui:journey
npm run test:ui:platform-provisioning
npm run test:ui          # full UAT lane
```

Each lane resets its database by default. Use `PLAYWRIGHT_RESET_DB=0` only for a targeted
rerun after a successful reset; do not add competing resets inside scenarios. Scope queue and
Mailpit assertions with unique fixture tokens so background work from another scenario cannot
satisfy them accidentally.

Coverage and mutation checks are opt-in:

```bash
bash bin/verify.sh --with-coverage
bash bin/verify.sh --with-mutation
```

Do not record test counts or timing estimates in maintained documentation; both become stale
as the suite changes.
