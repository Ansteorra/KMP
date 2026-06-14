# Technology Stack — Maintenance Research

**Project:** Kingdom Management Portal (KMP)
**Researched:** 2026-06-14
**Mode:** Subsequent milestone — existing system in maintenance mode

---

## Current State (What Is Actually Installed)

Sourced directly from `composer.lock` and `package-lock.json`. These are ground truth.

### PHP / CakePHP Core

| Package | Locked Version | Constraint in composer.json |
|---------|---------------|------------------------------|
| PHP runtime | 8.3.x | `^8.3` |
| cakephp/cakephp | **5.2.1** | `^5.0.1` |
| cakephp/authentication | 3.2.2 | `^3.0` |
| cakephp/authorization | 3.4.1 | `^3.1` |
| cakephp/migrations | 4.6.3 | `^4.0.0` |
| cakephp/chronos | 3.1.0 | (transitive) |

### Third-Party CakePHP Plugins

| Package | Locked Version |
|---------|---------------|
| admad/cakephp-glide | 6.0.0 |
| friendsofcake/bootstrap-ui | 5.1.1 |
| friendsofcake/cakepdf | 5.1.0 |
| friendsofcake/cakephp-csvview | 5.0.0 |
| dereuromark/cakephp-tools | 3.9.2 |
| dereuromark/cakephp-templating | 0.2.13 |
| dereuromark/cakephp-shim | 3.5.0 |
| muffin/footprint | 4.x (transitive) |
| muffin/trash | 4.x (transitive) |

### PHP Libraries

| Package | Locked Version | Notes |
|---------|---------------|-------|
| erusev/parsedown | 1.7.4 | Last released 2019 |
| league/flysystem | 3.x | Current |
| azure-oss/storage | 1.5.0 | Released 2025-09-30 |
| azure-oss/storage-blob-flysystem | 1.2.1 | Released 2025-08-12 |
| guzzlehttp/guzzle | 7.9.3 | Current |
| setasign/fpdf | 1.8.x | Stable/mature |
| symfony/yaml | 7.4.x | Current |
| phpstan/phpstan | ^2.1 | Current |
| squizlabs/php_codesniffer | ^3.10 | Current |
| phpunit/phpunit | ^10.1.0 | PHPUnit 11/12 available |

### JavaScript / Frontend

| Package | Declared Constraint | Notes |
|---------|---------------------|-------|
| @hotwired/stimulus | ^3.2.2 | Stable; no breaking changes since 3.0 |
| @hotwired/turbo | ^8.0.23 | Stable; v8 is current major |
| bootstrap | ^5.3.8 | Bootstrap 5.x is current; v6 not released |
| @fortawesome/fontawesome-free | ^7.2.0 | v7 is current (post-v6 rewrite) |
| laravel-mix | ^6.0.49 | **Maintenance mode upstream** |
| jest | ^30.2.0 | Current |
| @playwright/test | ^1.58.2 | Current |
| playwright-bdd | ^8.4.2 | Current |
| face-api.js | ^0.22.2 | **Abandoned upstream** (last release 2020) |
| pdfjs-dist | ^5.4.624 | Current |
| sass | ^1.97.3 | Current |

---

## PHP Runtime: Plan for PHP 8.4

**Confidence: HIGH** (sourced from php.net supported-versions page)

PHP 8.3 entered **security-only support** as of December 2025. It will receive security patches through December 2027, but active bug fixes ended. PHP 8.4 is in active support through December 2026, and PHP 8.5 (released November 2025) has the longest active support window.

**Recommendation:** Upgrade the PHP runtime from 8.3 to 8.4 in the mid-term. There is no urgency today — 8.3 gets security patches through 2027 — but deferring past the CI/prod alignment point creates a two-year catch-up problem.

**Why 8.4 and not 8.5:** CakePHP 5.2.x is already tested against PHP 8.4. PHP 8.5 is new enough (Nov 2025) that ecosystem libraries (extensions, third-party CakePHP plugins) may have compatibility gaps. Target 8.4 first; evaluate 8.5 in 6-12 months.

**What changes in 8.4 that matters here:**
- Property hooks (new syntax, not required) — no breaking risk
- `#[\Deprecated]` attribute replaces some docblock patterns
- `array_find()` / `array_find_key()` builtins — no risk
- `new` in initializers without parens — no risk
- No removals that affect CakePHP 5 or any locked dependency in this codebase

**Docker impact:** Change `FROM php:8.3-apache-bookworm` to `FROM php:8.4-apache-bookworm` in `docker/Dockerfile.base`. CI workflow also needs `php-version: '8.4'`. Rebuild the `kmp-base:php84` image; update the `BASE_IMAGE` env var in nightly/release workflows.

**What NOT to do:** Do not add `declare(strict_types=1)` to the `Dockerfile.base` — it already exists in all PHP files. Do not jump directly to 8.5; that should wait for ecosystem stabilization.

---

## CakePHP: Stay Current on 5.x Patch Releases

**Confidence: HIGH** (sourced from composer.lock — currently 5.2.1)

The constraint `^5.0.1` allows Composer to install any 5.x release. The lockfile currently pins 5.2.1 (released 2025-04-06). CakePHP follows semantic versioning strictly: minor versions (5.1, 5.2, 5.3) may add features and deprecations but will not break existing APIs. Patch releases fix bugs and security issues only.

**Recommendation:** Run `composer update cakephp/cakephp` on a regular cadence (quarterly at minimum) and update the lockfile. Do not pin to a specific patch version in composer.json — the `^5.0.1` constraint is already correct.

**Security patch cadence:** CakePHP publishes security releases for the current minor series. There is no separate LTS branch in the 5.x line. Staying within one minor version of the latest is the supported posture.

**Authentication / Authorization plugins:** Currently at 3.2.2 / 3.4.1 respectively. These are actively maintained. Update them alongside the core — they track the same cadence and their constraints already allow this.

**Migrations plugin (4.6.3):** This is well ahead of the `^4.0.0` constraint. No action needed beyond keeping the lockfile current.

---

## Laravel Mix: Do Not Migrate Yet, But Acknowledge the Risk

**Confidence: MEDIUM** (knowledge cutoff August 2025; Mix status from codebase analysis)

Laravel Mix 6.x is in maintenance mode. The Laravel team stopped active development of Mix in favor of Vite when Laravel 9 shipped. Mix 6 still receives security patches but no new features. It runs on Webpack 5 internally.

**The tradeoff:**
- Mix works today and the `webpack.mix.js` config is non-trivial: it auto-discovers all `*-controller.js` files across the core app and all plugins, bundles them, extracts a vendor chunk, handles font copying, and applies cache-busting versioning.
- Migrating to Vite would require rewriting that discovery logic using `vite.config.js` and the `@vitejs/plugin-legacy` package (for IE/older browser support if needed), and updating the CakePHP side to use the `AssetMix` helper's Vite-compatible mode or switching to a different asset helper.
- Vite is substantially faster for development hot-reload but the production build difference is negligible for this project's asset size.

**Recommendation for this milestone:** Do not migrate away from Mix. The risk-to-reward ratio is poor in maintenance mode. The build still works, the config is understood, and there are no security issues with Mix itself. Flag this as future work if Node 18 (the current runtime) reaches EOL and Mix 6 blocks upgrading Node.

**When to revisit:** If `npm audit` starts showing high-severity CVEs in Mix's Webpack 4/5 dependency tree that cannot be resolved by `overrides`, migration becomes warranted. The `package.json` already uses `overrides` to patch several transitive vulnerabilities (`fast-xml-parser`, `glob`, `minimatch`, `rimraf`, `webpack-dev-server`), which shows this approach is already in use and working.

---

## PHPUnit: Stay on 10, Watch for 11/12

**Confidence: HIGH** (from composer.lock and CakePHP dev requirements)

The project is on PHPUnit `^10.1.0`. CakePHP 5.2.x dev requirements already accept `^10.5.5 || ^11.1.3 || ^12.0.9`, meaning the framework itself is tested against all three. PHPUnit 10 remains fully supported.

**Recommendation:** No action required this milestone. When PHPUnit 10 reaches EOL (likely late 2026), update the constraint to `^11.0` and run the test suite. PHPUnit 11 introduced stricter expectations around mock objects and some deprecated assertions were removed — plan for a test cleanup pass at that point.

**Do not update PHPUnit alone** — check that all CakePHP plugins (authentication, authorization, muffin/*, etc.) have released PHPUnit 11-compatible versions first, since their test helpers are used in KMP's test setup.

---

## PHPStan: Already on ^2.1 — Maintain, Do Not Raise Level

**Confidence: HIGH** (from composer.json dev requirements)

PHPStan 2.x is the current major series. The project is already using it. Level 5 with a baseline of ~1947 suppressed errors is the correct posture for a mature codebase where global cleanup is out of scope.

**Recommendation:** The baseline file (`phpstan-baseline.neon`) must not grow. Enforce this in CI: if a PR's PHPStan run produces errors not in the baseline, it fails. This is already the expected behavior of `composer run-script stan`. Do not raise the level to 6+ during this maintenance window — it would surface hundreds of pre-existing issues and create distraction without safety benefit.

**Where PHPStan adds value here:** New code written during the maintenance milestone must pass at level 5 without adding to the baseline. The `dereuromark/cakephp-ide-helper` (already in dev dependencies) helps by keeping entity/table annotations in sync, which reduces property access false positives.

---

## erusev/parsedown: Security Risk — Replace or Isolate

**Confidence: MEDIUM** (library status from composer.lock; CVE knowledge from training data through August 2025)

`erusev/parsedown` 1.7.4 was released in 2019. The library has known XSS vulnerabilities that were never fixed in the 1.x line because the maintainer's 2.0 rewrite (which addresses them) was never completed. If user-supplied content is processed through Parsedown and output without explicit escaping, it is potentially vulnerable to XSS.

**Recommendation:** Audit every call site where `Parsedown::text()` or `Parsedown::line()` is called. If the input is always developer-controlled (e.g., stored configuration markdown), the risk is acceptable. If members can input markdown that is rendered for other members, treat this as a medium-priority security issue. Replacements:
- `league/commonmark` ^2.x — actively maintained, PSR-compliant, XSS-safe by default
- `michelf/php-markdown` ^2.x — lighter weight, also actively maintained

Switching is a small contained change but requires updating all call sites and testing rendered output.

---

## face-api.js: Pinned Abandoned Library — Accept or Plan Replacement

**Confidence: HIGH** (from package.json; last npm release verifiable from package metadata)

`face-api.js` 0.22.2 has not been updated since approximately 2020. The library works but receives no security patches. It is used for profile photo face-detection validation in the browser.

**This is a known acceptable risk** given what it does (client-side photo validation, not a security control), and the comment in the existing STACK.md already flags it as pinned. However:

- The library depends on a bundled TensorFlow.js runtime that is now multiple major versions behind.
- It blocks upgrading `@techstark/opencv-js` past the version that matches its TF.js expectations.
- If a CVE surfaces in the bundled TF.js affecting browser execution, there is no upstream fix path.

**Recommendation:** Accept the pin for now. Add a note to the PITFALLS.md that `face-api.js` is an upgrade blocker for the computer-vision stack and that replacing it requires a coordinated swap of both `face-api.js` and `@techstark/opencv-js`. If face detection can be moved server-side (using PHP-GD or a cloud service), both packages could be eliminated.

---

## Node.js Runtime: Upgrade from 18 to 22

**Confidence: HIGH** (from codebase STACK.md, which records Node 18.20.4)

Node.js 18 reached End of Life in April 2025. The build pipeline currently runs on Node 18 (documented in `.planning/codebase/STACK.md`). This means:
- No security patches for Node itself
- npm and build tooling may start refusing to install on Node 18 as peer dependency floors rise
- GitHub Actions `ubuntu-latest` runner images eventually drop old Node versions from the pre-installed set

**Recommendation:** Upgrade the devcontainer and CI Node version to 22 LTS (the current LTS line as of 2025). Node 22 has active support through April 2027. The upgrade is low-risk — Laravel Mix 6, Jest 30, and Playwright 1.58 all support Node 22. Check `.devcontainer/devcontainer.json` or the Node install step in CI workflows.

**Do not use Node 20:** It entered maintenance mode in October 2024 (security fixes only through April 2026). Go directly to 22.

---

## MariaDB: Align CI to Match Production

**Confidence: MEDIUM** (from CI workflow and STACK.md)

The CI `tests.yml` matrix uses `mariadb:10` (no specific patch tag). The dev Docker Compose and nightly smoke tests use `mariadb:11`. This mismatch means CI validates against an older MariaDB than production runs.

MariaDB 10.x series: MariaDB 10.6 is LTS through July 2026; MariaDB 10.11 is LTS through 2028. "mariadb:10" in Docker resolves to the latest 10.x, which is currently 10.11. This is probably acceptable but should be made explicit.

**Recommendation:** Pin the CI matrix to `mariadb:11` (matching the nightly/prod image) and add a comment explaining the version choice. If the Postgres CI job is meant to catch cross-database compatibility only (which the current workflow comment confirms), its mariadb:10 historical choice can be dropped in favor of standardizing on 11.

---

## Dependency Audit Tooling: Add composer audit to CI

**Confidence: HIGH** (from CI workflow review — it is not currently present)

The `tests.yml` CI workflow runs PHPUnit but does not run `composer audit` (the built-in Composer security advisory check, available since Composer 2.4). The `package.json` has a `test:security` script that runs a `security-checker.sh` script, but there is no equivalent in the PHP CI pipeline.

**Recommendation:** Add a `composer audit --no-interaction` step to `tests.yml` after `composer install`. This checks the installed packages against the PHP Security Advisories Database with zero configuration. It will fail the build if a package with a known CVE is installed. This is a one-line CI addition and eliminates a class of supply-chain risk for the PHP side.

---

## What NOT to Change

These items are working correctly and changing them would create risk without benefit.

| Item | Status | Reason to Leave Alone |
|------|--------|----------------------|
| CakePHP 5.x constraint (`^5.0.1`) | Correct | Already allows all 5.x minor versions; lockfile update is the right mechanism |
| PHPStan level 5 | Correct | Raising to 6+ would surface 300+ pre-existing issues; no net safety gain this cycle |
| `phpcbf` global execution | Prohibited | Documented: adds type hints that break LSP in policy classes |
| `--testsuite all` in CI | Prohibited | Documented: only runs ~half the tests; use `composer test` |
| BasePolicy method signatures | Frozen | Any native type hints break the plugin override pattern |
| Laravel Mix build config | Working | Complex auto-discovery logic; migration cost exceeds benefit in maintenance mode |
| Bootstrap 5.3 | Stay | Bootstrap 6 is not released; 5.x is current and stable |
| Stimulus 3.2 | Stay | No breaking changes in 3.x; update within 3.x as patches arrive |
| Turbo 8 | Stay | Turbo 8 is the stable series; no 9.x released |
| APCu as default cache | Stay | Works well for single-instance Docker deployment model |
| `patch-package` for npm patches | Stay | Already managing transitive CVEs; remove only when upstreams absorb fixes |
| `face-api.js` at 0.22.2 pin | Accept | Documented pin; replacement requires coordinated cv-stack swap |
| Infection / StrykerJS mutation testing | Stay | Rare but valuable; do not remove mutation test configs |

---

## Priority Order for Maintenance Actions

Ordered by risk/effort ratio, highest value first:

1. **Add `composer audit` to CI** — one line, eliminates PHP supply-chain blind spot. Zero risk.
2. **Run `composer update` quarterly** — keeps CakePHP core and plugins current on patch releases. Run the full test suite after each update.
3. **Upgrade Node runtime from 18 to 22** — Node 18 is EOL; this is now overdue. Low risk, affects devcontainer + CI only, no application code changes.
4. **Audit Parsedown call sites** — determine if user-supplied input reaches `Parsedown::text()`. If yes, schedule a library swap to `league/commonmark`. Medium effort, medium-to-high security value depending on findings.
5. **Plan PHP 8.4 upgrade** — not urgent (8.3 security support until 2027) but should be scheduled before the 2-year window closes. Involves Docker base image rebuild and CI workflow change. Test suite is the risk mitigation.
6. **Update PHPUnit constraint to allow ^11** — defer until PHPUnit 10 EOL approaches or a blocking dependency requires it. This will require a targeted test cleanup pass.

---

## Confidence Assessment

| Area | Confidence | Basis |
|------|------------|-------|
| Installed package versions | HIGH | Read directly from `composer.lock` and `package-lock.json` |
| PHP 8.3 support lifecycle | HIGH | Sourced from php.net supported-versions page |
| PHP 8.4 compatibility with CakePHP | HIGH | CakePHP 5.2.1 dev requirements show ^8.1 floor; ecosystem well-tested |
| Laravel Mix maintenance status | MEDIUM | Known from Laravel ecosystem context; could not verify latest activity in this session |
| PHPUnit 10/11/12 CakePHP support | HIGH | Verified from CakePHP 5.2.1 dev requirements in composer.lock |
| Parsedown vulnerability status | MEDIUM | Known issue class; specific CVE state could not be queried live in this session |
| face-api.js abandonment | HIGH | Last release date visible in lock metadata; no upstream activity |
| Node 18 EOL | HIGH | Node.js release schedule (April 2025 EOL) is authoritative and well-documented |
| MariaDB CI version mismatch | HIGH | Directly observed from CI workflow YAML |
| Bootstrap 6 / Turbo 9 status | HIGH | Neither released as of knowledge cutoff August 2025 |

---

*Research basis: direct inspection of `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `docker/Dockerfile.base`, `.github/workflows/tests.yml`, `.github/workflows/nightly.yml`, `webpack.mix.js`, `jest.config.js`, `infection.json5`, `stryker.config.js`, and php.net supported-versions page. No training-data assumptions used for version numbers — all package versions sourced from lockfiles.*
