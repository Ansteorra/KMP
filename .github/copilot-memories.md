# KMP Project Memories

Verified facts about the KMP codebase for AI agent reference. Each entry includes the source citation.

## Build & Testing

- **Full verification**: `cd app && bash bin/verify.sh` runs PHPUnit (1018 tests), Jest (27 tests), Webpack, PHPCS (changed files only), PHPStan (level 5, 1 known error). All pass.
  _Source: app/bin/verify.sh; verified 2026-03-24_

- **Backend tests**: `cd app && composer test` runs all 1018 PHPUnit tests. Do NOT use `--testsuite all` — it only runs 509/1018 tests due to incomplete suite definition.
  _Source: app/phpunit.xml.dist; verified 2026-03-24_

- **Frontend tests**: `cd app && npm run test:js` (Jest, 27 tests) and `cd app && npm run dev` (Webpack build).
  _Source: app/package.json; verified 2026-03-24_

- **JS mutation testing**: StrykerJS v8.7.1. Run `npm run test:mutate` for security-critical controllers, `npm run test:mutate:all` for all JS. Config at `app/stryker.config.js`. Node 18 requires v8 (not v9). Baseline score: ~10% (thin test coverage).
  _Source: app/stryker.config.js, app/package.json; verified 2026-03-24_

- **PHP mutation testing**: Infection 0.32.6. Run `composer mutate` for policies + awards, `composer mutate:policy` for just policies. Uses wrapper script `bin/run-infection.sh` (pre-generates coverage, then runs Infection with --skip-initial-tests to work around XdebugHandler issue). Config at `app/infection.json5`. BasePolicy.php baseline: 97% MSI.
  _Source: app/infection.json5, app/bin/run-infection.sh; verified 2026-03-24_

- **E2E tests**: Playwright + playwright-bdd. Run: `npx bddgen` to generate specs, reset DB via `sudo bash reset_dev_database.sh`, then `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8080 npx playwright test`. Features in `tests/ui/bdd/`.
  _Source: app/playwright.config.js; app/tests/ui/bdd/_

## Static Analysis

- **PHPStan**: Level 5 configured in `app/phpstan.neon` with CakePHP bootstrap files. Baseline has 1947 pre-existing errors in `app/phpstan-baseline.neon`. Only 1 unbaselinable error (HtmlHelper property covariance). Run: `cd app && vendor/bin/phpstan analyse --no-progress`
  _Source: app/phpstan.neon; verified 2026-03-24_

- **PHPCS**: `app/phpcs.xml` uses CakePHP standards, checks only PHP files. ~3400 pre-existing violations exist globally. `verify.sh` checks only changed files. Run: `cd app && vendor/bin/phpcs`
  _Source: app/phpcs.xml; verified 2026-03-24_

## Dangerous Patterns

- **Never run `phpcbf` on the entire codebase** — it adds type hints from docblocks that break PHP's type system (LSP violations in policies and tables). Only fix PHPCS issues manually in files you modify.
  _Source: Discovered 2026-03-24 when phpcbf broke WarrantsTable::afterSave() and ServicePrincipalPolicy::canAdd()_

- **BasePolicy methods use untyped params** (`$query`, `$entity`) for LSP compatibility with plugins. Don't add native type hints to overridable methods in BasePolicy.
  _Source: app/src/Policy/BasePolicy.php; plugins/Awards/src/Policy/RecommendationsTablePolicy.php_

- **Keep face-api.js at ^0.22.2** — downgrading to ^0.20.0 weakened face detection quality for profile-photo validation.
  _Source: app/package.json; user reported regression_

## Architecture

- **AGENTS.md** contains comprehensive agent guide (~260 lines): project overview, verification runbook, directory map, DB access, test data IDs, test patterns, 9 dangerous patterns, plugin architecture, config hierarchy, CI/CD, services.
  _Source: AGENTS.md_

- **Quick-login PIN setup** intentionally upserts by device_id across members, reassigning device ownership to the currently authenticated member.
  _Source: app/src/Controller/MembersController.php:2243-2254; app/config/Migrations/20260305163000_EnforceUniqueDeviceIdOnMemberQuickLoginDevices.php_

- **Members/emailTaken** must remain usable anonymously because public registration validates unique email via member-unique-email Stimulus controller.
  _Source: app/templates/Members/register.php; app/assets/js/controllers/member-unique-email-controller.js_

- **Public recommendations UI** fetches `Members/PublicProfile` and renders `data.external_links` — keep this response key for compatibility.
  _Source: app/plugins/Awards/templates/Recommendations/submit_recommendation.php; app/plugins/Awards/Assets/js/controllers/rec-add-controller.js_

- **Recommendation states** outside Need to Schedule/Scheduled/Given automatically clear `gathering_id`; bulk `updateStates` also nulls gathering_id for unsupported states.
  _Source: app/plugins/Awards/src/Model/Entity/Recommendation.php; app/plugins/Awards/src/Controller/RecommendationsController.php_

## Dev Environment

- **Apache** runs HTTP (not HTTPS) on port 8080. SSL is commented out. Mailpit on port 8025.
  _Source: /etc/apache2/sites-enabled/*.conf_

- **DB credentials**: Host=localhost, User=KMPSQLDEV, Password=P@ssw0rd, DB=KMP_DEV (from `app/config/.env`)

- **Git workflow**: Ansteorra/KMP disallows squash merges; PRs must use merge commits.
  _Source: Discovered 2026-03-05 via gh pr merge failure_
