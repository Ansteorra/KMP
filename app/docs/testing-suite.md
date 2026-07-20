# KMP Testing Overview and Contract

This document defines both the current KMP test suite layout and the repo-level testing contract for new feature work. Use it when planning work, adding tests, reviewing pull requests, and deciding whether a change is ready for UAT or production promotion.

## 🧱 Test Architecture

| Layer | Location | Harness | Description |
| --- | --- | --- | --- |
| Core Unit | `tests/TestCase/Core/Unit` | `BaseTestCase` | Fast PHP tests for table logic, services, policies, entities, commands, and other business rules that do not need a full browser journey. |
| Core Feature | `tests/TestCase/Core/Feature` | `HttpIntegrationTestCase` | HTTP-facing PHP tests for controllers, routes, authentication, authorization, form submissions, and response contracts. |
| Plugins | `tests/TestCase/Plugins` and `plugins/*/tests/TestCase` | `PluginIntegrationTestCase` | Plugin coverage using the shared seed dataset and plugin bootstrap helpers. |
| JavaScript Unit | `tests/js` | Jest + jsdom | Frontend unit tests for Stimulus controllers, browser utilities, DOM state changes, and client-side branching logic. |
| E2E / Workflow | `tests/ui/bdd` and `tests/ui` | Playwright-BDD | Full browser journeys that prove integrated user flows, permission boundaries, and release-critical regressions. |

Support helpers live in `tests/TestCase/Support`:

- `SeedManager` – centralizes loading `../dev_seed_clean.sql` to the `test` connection.
- `HttpIntegrationTestCase` – thin HTTP feature base (extends `BaseTestCase` + `IntegrationTestTrait`).
- `PluginIntegrationTestCase` – auto-loads the plugin under test before running assertions.

## 🌱 Guaranteed Seed Data

All suites load `/workspaces/KMP/dev_seed_clean.sql` during `tests/bootstrap.php`. `SeedManager` ensures the SQL file is applied once per run and exposes a `reset()` helper for heavy data mutation scenarios.

When extending `BaseTestCase`, call `$this->reseedDatabase()` if your test needs a full reset after destructive operations.

Playwright scenarios should prefer the canonical seed accounts documented in `/README.md` so browser flows remain deterministic across developer machines, PR validation, and UAT.

## 🚀 Running Tests

| Command | Purpose |
| --- | --- |
| `vendor/bin/phpunit --testsuite core-unit` | Run fast PHP unit/service coverage. |
| `vendor/bin/phpunit --testsuite core-feature` | Execute HTTP-centric PHP feature coverage. |
| `vendor/bin/phpunit --testsuite plugins` | Run plugin coverage. |
| `composer test` | Run the full PHPUnit suite. |
| `composer test:coverage:security` | Generate focused PHPUnit coverage for policies and the Awards recommendation workflow. |
| `composer test:coverage` | Generate full PHPUnit coverage reports for the complete PHP suite. |
| `npm run test:js` | Run Jest JavaScript tests. |
| `npm run test:js:coverage:security` | Generate focused Jest coverage for the security-critical Stimulus controllers. |
| `npm run test:mutate` | Run Stryker against the security-critical Stimulus controllers. |
| `npm run test:mutate:all` | Run Stryker against the broader JS source tree. |
| `composer mutate` | Run Infection against policies and the Awards recommendation state machine. |
| `composer mutate:policy` | Run Infection only for `src/Policy`. |
| `npm run test:ui:smoke` | Generate Playwright-BDD tests, reset the dev database, and run the fast CI smoke subset (`UserLogin` + `workflow-admin`). |
| `npm run test:ui:journey` | Generate Playwright-BDD tests, reset the dev database, and run the curated local whole-app journey across tenancy, member registration, activities authorization, officers/warrants, gatherings, and awards feedback. |
| `npm run test:ui` / `npm run test:ui:uat` | Generate Playwright-BDD tests, reset the dev database, and execute the full browser regression set used for UAT/nightly verification. |
| `bash bin/verify.sh` | Run the standard local verification bundle: PHPUnit, Jest, Vite build, PHPCS, and PHPStan. |
| `bash bin/verify.sh --with-coverage` | Run the standard verification bundle, then add focused PHP + JS coverage reports. |
| `bash bin/verify.sh --with-mutation` | Run the standard verification bundle, then add focused PHP + JS mutation analysis. |

> `bin/verify.sh` does **not** currently execute Playwright. If the testing contract for a change requires E2E coverage, run `npm run test:ui` in addition to `bash bin/verify.sh`.

### Playwright lanes and local workflow

Playwright-BDD specs are generated before every lane. The lane runner performs one database reset at the start unless `PLAYWRIGHT_RESET_DB=0` is set; after that, scenario fixtures must be additive and uniquely named.

| Lane | Command | Use |
| --- | --- | --- |
| Smoke | `npm run test:ui:smoke` | Fast login + workflow-admin browser gate. |
| Journey | `npm run test:ui:journey` | Trusted one-click local app journey. Current reset-backed baseline: 27 tests passing in about 8.5 minutes. |
| UAT/full | `npm run test:ui` or `npm run test:ui:uat` | Full generated Playwright-BDD regression set for UAT/nightly evidence. |

Run only **one Playwright lane at a time** against the shared local Docker stack. The app, DB, worker, scheduler, and Mailpit containers are shared resources, so concurrent lanes can race database resets, queue delivery, scheduled workflows, and mailbox assertions.

For targeted reruns after a lane reset, use:

```bash
PLAYWRIGHT_RESET_DB=0 npx playwright test path/to/generated.spec.js --reporter=line
```

Do not add a second reset inside a scenario. If a feature needs setup data, create it through `runPhpJson()` with fixture-unique tokens and STDIN JSON payloads.

### Workflow, queue, and Mailpit assertions

Services and controllers must dispatch triggers; workflows react to those triggers and enqueue side effects such as email. E2E tests should prove that chain without directly invoking workflow actions from services.

Use these shared helpers from `tests/ui/support/ui-helpers.cjs`:

- `runPhpJson()` for Docker-aware fixture setup. PHP snippets must read payloads with `stream_get_contents(STDIN)`.
- `flushWorkflowsAndQueue()` before workflow/email assertions. It runs scheduled workflows only when called with `{ forceScheduler: true }`.
- `waitForQueueSettled()` after flushing so due queue jobs have drained before assertions.
- `waitForStableMailpitSearchTotal()` for negative email assertions; it requires the Mailpit count to remain stable across a quiet window to avoid racing just-delivered mail.
- `assertNoQueuedEmailFor()` when proving a specific fixture token has no due or delayed queued mail.

Prefer Mailpit API assertions scoped by recipient, subject, and fixture token instead of scraping the Mailpit UI. UI checks are acceptable for email rendering assertions, but mailbox presence/absence should use API-backed helpers.

The background scheduler is paused during reset and restarted afterward by the reset script. During tests, avoid broad unscoped scheduled-workflow assertions: `workflow_scheduler --force` scans global state, so assertions must use fixture-scoped Mailpit and queue tokens.

### Multi-tenant browser coverage

Tenant E2E coverage uses host-bound browser contexts (`kmp.localhost` and `kmp2.localhost`) through `tests/ui/support/tenant-context.cjs`. Tenant assertions should verify both positive visibility in the active tenant and negative isolation for records that belong only to another tenant.

## 🧬 Mutation and Coverage Workflow

Mutation and coverage checks are intentionally split out from the default `bin/verify.sh` path because they are slower, but they are now part of the normal local hardening workflow for security-sensitive code.

### Focused local workflow

Use this sequence when changing authentication, authorization, or recommendation-state logic:

1. `bash bin/verify.sh`
2. `bash bin/verify.sh --with-coverage`
3. `bash bin/verify.sh --with-mutation`

This keeps the fast feedback loop intact while still making it easy to add deeper checks before review, UAT, or CI adoption.

### Current focus areas

- **PHP mutation + focused coverage:** `src/Policy`, `plugins/Awards/src/Model/Entity`, `plugins/Awards/src/Model/Table`
- **JS mutation + focused coverage:** `mobile-pin-gate`, `face-photo-validator`, `login-device-auth`, `member-mobile-card-pwa`, `member-mobile-card-profile`

### Report locations

| Report | Path |
| --- | --- |
| Focused PHPUnit HTML coverage | `tests/coverage/php-security-html/index.html` |
| Focused PHPUnit Clover coverage | `tests/coverage/php-security-clover.xml` |
| Full PHPUnit HTML coverage | `tests/coverage/php-all-html/index.html` |
| Focused Jest HTML coverage | `tests/coverage/js-security/index.html` |
| Full Jest HTML coverage | `tests/coverage/js-all/index.html` |
| Infection HTML report | `tests/mutation-reports/infection.html` |
| Stryker HTML report | `tests/mutation-reports/stryker-security-report.html` |

When mutation output reports a **survived** or **no coverage** result, add or strengthen the targeted PHPUnit/Jest coverage before broadening the mutation scope.

## 📋 Repo-Level Feature Testing Contract

Every feature, bug fix, refactor, or risky config change must declare its test contract before review. Capture the contract in the work item, implementation notes, or PR description using this matrix:

| Layer | Required? | Planned coverage | Critical path? |
| --- | --- | --- | --- |
| PHP Unit | Yes / No | Name the rule, class, or branch being covered. | Yes / No |
| PHP Feature | Yes / No | Name the route, action, policy, or response contract being covered. | Yes / No |
| JS Unit | Yes / No | Name the Stimulus controller, DOM behavior, or client-side branch being covered. | Yes / No |
| E2E | Yes / No | Name the user journey, persona, and assertion focus. | Yes / No |

Rules for using the matrix:

1. Every row must be explicitly marked `Yes` or `No`; do not leave layers implied.
2. If a row is `No`, record the reason (`server-side only`, `no browser behavior changed`, `copy-only change`, etc.).
3. If a change crosses layers, declare all affected layers. A single feature commonly needs both lower-level tests and E2E coverage.
4. Review is not complete until the declared layers exist in code and have been run at the appropriate verification stage.

## ✅ Minimum Expectations by Change Type

| Change shape | Required coverage |
| --- | --- |
| Business rules, validation, date math, table/service logic, policies, helper methods, command internals | Add or update PHP unit coverage for the happy path and at least one edge, validation, or failure branch. |
| Controller actions, routing, authorization, form handling, serialization, redirects, flash/error behavior | Add or update PHP feature coverage for the changed endpoint, including authn/authz and response assertions where relevant. |
| Stimulus controllers, frontend utilities, DOM state transitions, browser-side validation, async UI behavior | Add or update Jest coverage for events, rendering/state transitions, and error handling when applicable. |
| Multi-step user journeys, cross-page flows, regressions spanning PHP + JS, seed-account permission checks, or anything relied on for release signoff | Add or update Playwright-BDD coverage for the end-to-end workflow. |

Apply every row that matches the change. For example, a new Stimulus-powered form usually needs PHPUnit feature coverage, Jest coverage, and Playwright coverage.

## 🚨 Critical-Path Expectations

A path is **critical** when failure would block normal use, UAT signoff, or safe promotion. Treat a flow as critical if it affects any of the following:

- Authentication, session establishment, logout, or password/account recovery.
- Authorization boundaries or role-based access to sensitive actions.
- Primary create/update/delete, approval, issuance, revocation, or submission flows for the feature being changed.
- Data integrity transitions that could silently corrupt records or leave the app in an unusable state.
- Deployment, startup, migration, console, or background flows required for a release to function.
- Any workflow the release manager intends to use as UAT or post-deploy smoke coverage.

Critical-path coverage expectations:

1. Cover the **happy path** end to end.
2. Cover at least one **negative path** at the most relevant layer (validation failure, authorization denial, failed transition, or user-visible recovery path).
3. Prove the underlying state transition at the **lowest practical layer** (usually PHP unit or feature tests, plus Jest when frontend logic branches).
4. If a user-facing browser journey is involved, add or update a **Playwright-BDD** scenario using a known seed persona.

Use this priority language when discussing scope:

| Priority | Meaning | Minimum expectation |
| --- | --- | --- |
| `P0` | Deploy-blocking critical path | Applicable lower-layer tests **plus** Playwright when the path is browser-visible. Must be part of UAT/prod smoke. |
| `P1` | High-value path with moderate regression risk | Applicable lower-layer tests and Playwright when the change spans multiple pages or permissions. |
| `P2` | Localized/non-critical path | Targeted tests at affected layers; E2E is optional unless the path becomes part of release smoke. |

## 🗺️ Domain Risk Matrix

Use [`domain-risk-matrix.md`](./domain-risk-matrix.md) to decide which product domains default to `P0`, `P1`, or `P2`, which lower-layer suites must exist, and which Playwright journeys should be carried into PR, UAT, and production smoke validation.

If a change touches multiple domains, apply the highest gate from the matrix and keep the coverage expectations from every touched row.

## 🔎 Verification Stages

The same declared contract scales through each promotion step:

| Stage | Required evidence |
| --- | --- |
| Local before commit | Run all declared layers for the change. For normal code changes, finish with `bash bin/verify.sh`. If E2E is declared, run the narrowest relevant Playwright lane or spec; use `npm run test:ui:journey` for whole-app workflow confidence and `npm run test:ui` for full UAT evidence. |
| Pull request gate | Keep the gate fast, but do not drop declared coverage. The `Quality Gates` workflow now runs PHPUnit, Jest, and the Playwright smoke lane. Changes that declare broader E2E coverage should still run the relevant local/full Playwright lane before review. |
| UAT / release candidate | Run the full regression set for declared layers, including `bash bin/verify.sh` and `npm run test:ui:uat` on the exact candidate ref. The nightly/UAT verification workflow is the CI lane for that evidence. UAT signoff must reference the same contract declared during implementation. |
| Promotion from UAT to production | Promote only the same verified SHA/config/schema combination that passed UAT. No open failures, waivers, or untested critical-path changes are allowed. Re-run post-deploy smoke checks for declared `P0` paths. |

## 🧪 Starter Tests

- `Core/Unit/Model/MembersTableSeedTest` – verifies the super-user seed and duplicate email guardrails.
- `Core/Feature/Members/MembersLoginPageTest` – confirms anonymous access to `/members/login` remains healthy.
- `Plugins/Awards/Feature/RecommendationsSeedTest` – proves Awards plugin tables are reachable using the shared seed.

These examples demonstrate how to:

1. Depend on canonical seed accounts defined in `/README.md`.
2. Share HTTP helpers and plugin loaders.
3. Keep new tests isolated with automatic transactions from `BaseTestCase`.

## 📈 Extending the Suite

1. **Start with the contract** – decide which layers are required before writing code.
2. **Pick the matching harness** – `BaseTestCase`, `HttpIntegrationTestCase`, `PluginIntegrationTestCase`, Jest, or Playwright-BDD.
3. **Leverage the seed** – use canonical seed members and `$this->reseedDatabase()` when needed.
4. **Name tests around behavior** – make the covered rule, route, or workflow obvious from the test name.
5. **Promote critical paths upward** – when a path becomes part of UAT/prod smoke, add the missing Playwright coverage instead of relying on unit/feature tests alone.

## 🔄 Legacy Tests

Existing suites under `tests/TestCase/Controller`, `Model`, `Services`, etc., remain runnable and are included in `core-unit`, `core-feature`, or `all`. Migrate legacy coverage into the new layout over time for the cleanest experience, but apply the contract immediately to all new work.

Happy testing! 🧪
