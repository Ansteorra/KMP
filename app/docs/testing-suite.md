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

The standard script runs the complete PHPUnit `all` suite, matching hosted CI, the skipped-test budget, seed snapshot
contracts, documentation and icon checks, Jest, the Vite development build, the Azure runtime
contract, PHPCS on changed PHP (including core and plugin templates), and PHPStan. It does not
run Playwright.

Run full verification with the complete repository available, including `.git`, root `docs/`,
and `deploy/`. The running Docker app service mounts only `app/`, so it cannot run every
repository check. With the local Docker stack initialized, run this from the repository root:

```bash
docker compose run --rm --no-deps --entrypoint bash \
  -v "$PWD:/workspace" \
  -v kmp-node-modules:/workspace/app/node_modules \
  -w /workspace/app \
  -e GIT_CONFIG_COUNT=1 \
  -e GIT_CONFIG_KEY_0=safe.directory \
  -e GIT_CONFIG_VALUE_0=/workspace \
  app -c 'bash bin/verify.sh'
```

Run one PHP test process per test database. Concurrent runs must use separate
`DATABASE_TEST_URL` and `PLATFORM_DATABASE_TEST_URL` values because each test bootstrap
reloads shared seed data.

Keep CakePHP CodeSniffer, PHPCS, Slevomat, and IDE Helper compatible when updating Composer
development dependencies. Use the locked versions and the project's `phpcs.xml`; do not
patch vendor code or disable checks to work around parser incompatibilities.

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

Nightly verification runs Drive-enabled UAT on Chromium, Firefox and WebKit in
sequence, with a fresh fixture reset for each browser. The destructive platform
lane runs separately on Chromium with its own reset and administrative worker.
Smoke/journey and PHP contracts retain the default Frames/Streams fallback.
The navigation lifecycle scenario enters To-Do, approval and recommendation
dialogs through links and checks initialization, Escape and returned focus.

Jest, its coverage lanes, and Stryker run with Node's **--disable-proto=throw** option,
including child workers. Use the npm scripts or their guarded runners rather than
invoking the Jest binary directly. This mitigates prototype poisoning in the
development-only deepmerge dependency used by jest-config
([GHSA-gcqx-w48m-w2h4](https://github.com/advisories/GHSA-gcqx-w48m-w2h4)).
There is no patched release at the time of this mitigation; the advisory remains
open and is not excluded from dependency reports. The reproduction tests exercise
null-prototype defaults, placeholder values, and target-owned prototype keys.
The safeguard does not change application bundles or browser runtime behavior.

Coverage and mutation checks are opt-in. Run `npm ci` first: mutation testing uses
the locked Stryker core and Jest runner, which must be upgraded together. Dependabot
groups `@stryker-mutator/*` updates to preserve that compatibility. Jest loads
`babel.jest.config.json` explicitly so Stryker’s Babel parser does not load
Jest’s Babel presets and plugins. Babel core, its presets/runtime/plugins, and the
Jest transformer must also be upgraded together; Dependabot groups that toolchain
to avoid incompatible peer dependencies. Regenerate npm lockfiles with the npm 11
toolchain used in CI so nested peer dependencies are recorded consistently:

```bash
bash bin/verify.sh --with-coverage
bash bin/verify.sh --with-mutation
```

Do not record test counts or timing estimates in maintained documentation; both become stale
as the suite changes.

The targeted `core-unit`, `core-feature`, and `plugins` suites are useful for focused
checks, but do not include every directory under `tests/TestCase` (including `Policy`).
Use `--testsuite all` for the complete regression gate. Test cleanup must restore an
absent optional datasource as absent and always run the parent transaction cleanup.
