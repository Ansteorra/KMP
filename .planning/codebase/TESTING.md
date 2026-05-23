# Testing

## Test Strategy

KMP uses a layered testing approach:
- **PHPUnit** — unit + integration tests for PHP (controllers, models, services, policies)
- **Jest** — unit tests for Stimulus JS controllers
- **Playwright + playwright-bdd** — E2E / BDD browser tests (not in CI by default)
- **PHP Infection** — mutation testing for PHP (policies + awards models)
- **StrykerJS** — mutation testing for security-critical JS controllers

Real database (MySQL) is used for PHP integration tests — no mocking the DB.

## Test Organization

```
app/tests/
├── bootstrap.php                      # PHPUnit bootstrap; loads seed data via SeedManager
├── TestCase/
│   ├── BaseTestCase.php               # All PHP tests extend this; wraps each test in DB transaction (begin/rollback)
│   ├── TestAuthenticationHelperTrait.php  # authenticateAsSuperUser(), authenticateAsMember(id), etc.
│   ├── Support/
│   │   └── HttpIntegrationTestCase.php    # HTTP controller tests; extends BaseTestCase + IntegrationTestTrait
│   ├── Controller/
│   │   └── AuthenticatedTrait.php     # DEPRECATED — writes to DB, incompatible with transaction rollback
│   ├── Model/                         # Table/entity tests
│   ├── Services/                      # Service layer tests
│   └── Policy/                        # Authorization policy tests
├── js/
│   ├── __mocks__/
│   │   └── stimulus.js                # Stimulus mock for controller tests
│   └── controllers/                   # ~94 *.test.js files mirroring assets/js/controllers/
└── ui/
    ├── bdd/                           # BDD feature files (Gherkin)
    │   ├── @auth/
    │   ├── @members/
    │   ├── @awards/
    │   └── @activities/
    └── gen/                           # Generated Playwright specs (from npx bddgen)

plugins/*/tests/TestCase/              # Plugin-specific test cases (~52 files)
```

## Running Tests

All commands run from `app/`:

```bash
# PHP tests
composer test                          # All PHPUnit tests (~1018 tests) — USE THIS, not --testsuite all
vendor/bin/phpunit tests/TestCase/Path/ToTest.php  # Single test file

# JS tests
npm run test:js                        # Jest unit tests (27+ tests)

# Mutation testing
composer mutate                        # PHP Infection (policies + awards)
composer mutate:policy                 # PHP Infection (policies only)
npm run test:mutate                    # StrykerJS (security-critical JS controllers)
npm run test:mutate:all                # StrykerJS (all JS controllers)

# Full pre-commit verification
bash bin/verify.sh                     # PHPUnit + Jest + Webpack build + PHPCS + PHPStan

# E2E (manual setup required)
npx bddgen                             # Generate specs from BDD features
sudo bash ../reset_dev_database.sh     # Reset DB to seed state
PLAYWRIGHT_BASE_URL=http://127.0.0.1:8080 npx playwright test
```

> **Do NOT use `vendor/bin/phpunit --testsuite all`** — it only runs ~half the tests due to an incomplete suite definition.

## Test Coverage

**PHP:** ~1018 PHPUnit tests across 83 files in `tests/TestCase/` + 52 in plugin test dirs.

**JS:** ~94 `*.test.js` files in `tests/js/controllers/`.

**Mutation scope (PHP Infection):**
- `src/Policy/`
- `plugins/Awards/src/Model/Entity/`
- `plugins/Awards/src/Model/Table/`

**Mutation scope (StrykerJS):** 5 security-critical controllers:
- `mobile-pin-gate`
- `face-photo-validator`
- `login-device-auth`
- `member-mobile-card-pwa`
- `member-mobile-card-profile`
- Thresholds: high=80, low=60, break=null

## Base Test Classes & Patterns

**`BaseTestCase`** (`tests/TestCase/BaseTestCase.php`):
- All PHP tests extend this
- Wraps each test in a DB transaction — `setUp` begins, `tearDown` rolls back (no persistent side effects)
- Defines seed data ID constants for referencing fixture records

**`HttpIntegrationTestCase`** (`tests/TestCase/Support/HttpIntegrationTestCase.php`):
- For HTTP controller tests
- Extends `BaseTestCase` + adds `IntegrationTestTrait` + `TestAuthenticationHelperTrait`

**`TestAuthenticationHelperTrait`**:
- Provides `authenticateAsSuperUser()`, `authenticateAsMember($id)`, etc.

**Deprecated:** `AuthenticatedTrait` at `tests/TestCase/Controller/AuthenticatedTrait.php` — writes to DB, incompatible with transaction rollback pattern.

## JS Test Pattern

Stimulus is mocked at `tests/js/__mocks__/stimulus.js`. Controllers are tested by:
1. Importing the controller file directly
2. Reading `window.Controllers['key']`
3. Manually wiring targets and values
4. Calling `initialize()` / `connect()` manually

## Seed Data

- MySQL: dump loaded once via `SeedManager::bootstrap()` in `tests/bootstrap.php`
- PostgreSQL: uses migrations only (no seed dump)
- Tests call `$this->skipIfPostgres()` when MySQL-specific seed data is required

## Static Analysis

### PHPStan
- Level 5
- Baseline suppresses **1947 pre-existing errors** (`app/phpstan-baseline.neon`)
- 1 known unbaselinable HtmlHelper type covariance error — treated as pass in `verify.sh`

### PHPCS
- CakePHP coding standard ruleset
- ~3400 pre-existing global violations
- `verify.sh` and `cs-check` only check files changed in the current branch
- **Never run `phpcbf` on the entire codebase** — it adds type hints from docblocks that violate PHP's LSP

## E2E / BDD Tests

- Framework: Playwright + playwright-bdd
- Feature files (Gherkin) in `tests/ui/bdd/` organized by domain (`@auth/`, `@members/`, `@awards/`, `@activities/`)
- Generated specs in `tests/ui/gen/` (run `npx bddgen` to regenerate)
- Not included in CI by default — require manual setup and a running app
