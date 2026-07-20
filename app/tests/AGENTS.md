# Test suite guide

## Purpose

Own application test coverage across PHPUnit, Jest/jsdom, and Playwright BDD lanes, including fixtures, support helpers, generated test artifacts, and verification workflows.

## Ownership

- PHPUnit tests live under `tests/TestCase` and plugin `tests/TestCase` directories.
- JavaScript unit tests live under `tests/js`.
- Playwright BDD and browser support live under `tests/ui`.
- Fixtures, support classes, and test files are shared test infrastructure.

## Local Contracts

- PHPUnit suites are `core-unit`, `core-feature`, `plugins`, and `all`.
- Use project base test classes and seeded constants instead of raw magic IDs.
- Use `reseedDatabase()` only for destructive tests that need a full reset.
- Jest uses jsdom with setup in `tests/js/setup.js`; mock globals consistently.
- Playwright reports, generated Playwright files, coverage output, and UI results are generated artifacts and should not be hand-edited.

## Work Guidance

1. Put tests near the suite that owns the behavior: model/service/KMP in `core-unit`, controllers/commands/views in `core-feature`, plugin behavior in plugin tests or plugin harness tests.
2. Mirror frontend source structure when adding Jest controller or utility tests.
3. For workflow side effects, test the trigger-driven chain rather than calling workflow actions directly.
4. Keep negative email and queue assertions fixture-scoped and stable.

## Verification

| Need | Command |
| --- | --- |
| PHP unit/model/service coverage | `vendor/bin/phpunit --testsuite core-unit` |
| Controller/command/view feature coverage | `vendor/bin/phpunit --testsuite core-feature` |
| Plugin coverage | `vendor/bin/phpunit --testsuite plugins` |
| JavaScript unit coverage | `npm run test:js` |
| Playwright UAT lane | `npm run test:ui` |
| Curated journey lane | `npm run test:ui:journey` |

## Child AGENTS index

| Path | Purpose |
| --- | --- |
| `app/tests/ui/AGENTS.md` | Playwright BDD lanes, support helpers, tenancy contexts, generated Playwright files, and UI reports |
