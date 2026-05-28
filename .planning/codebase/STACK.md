# Technology Stack

**Analysis Date:** 2026-05-23

## Languages & Runtimes

**Primary:**
- PHP 8.3 — Backend application server (`app/src/`, `app/plugins/`)
- JavaScript ES6+ — Frontend controllers and asset pipeline (`app/assets/js/`)
- CSS — Application styling (`app/assets/css/`)

**Secondary:**
- SQL — Database migrations and seed files (`app/config/Migrations/`, `dev_seed_clean.sql`)
- YAML — PHP extension config, PHPStan (`app/phpstan.neon`, `app/phpstan-baseline.neon`)
- TOML — Parsed at runtime via `smol-toml` npm package

**Runtime:**
- PHP 8.3.x (CLI and Apache mod_php)
- Node.js 18.20.4 (asset build pipeline only)

**Package Manager:**
- Composer (PHP) — lockfile: `app/composer.lock`
- npm (JS) — lockfile: `app/package-lock.json`

## Backend Framework

**Core:**
- CakePHP `^5.0.1` — Full-stack MVC framework

**CakePHP First-Party Plugins:**
- `cakephp/authentication ^3.0` — Session + form + API Bearer token auth
- `cakephp/authorization ^3.1` — Policy-based authorization
- `cakephp/migrations ^4.0.0` — Database migrations (Phinx-based)
- `cakephp/bake ^3.0.0` (dev) — Code generation
- `cakephp/debug_kit ^5.0.0` (dev) — Debug toolbar

**Third-Party CakePHP Plugins:**
- `admad/cakephp-glide ^6.0` — On-the-fly image manipulation (profile photos)
- `friendsofcake/bootstrap-ui ^5.0` — Bootstrap-aware CakePHP form helpers
- `friendsofcake/cakepdf ^5.0` — PDF generation
- `friendsofcake/cakephp-csvview ^5.0` — CSV export responses
- `jeremyharris/cakephp-lazyload ^5.0` — Lazy association loading
- `muffin/footprint ^4.0` — Auto-populate `created_by`/`modified_by` fields
- `muffin/trash ^4.0` — Soft delete support
- `ishanvyas22/asset-mix ^2.0` — Laravel Mix integration with CakePHP
- `dereuromark/cakephp-templating ^0.2.1` — Bootstrap icon rendering
- `dereuromark/cakephp-tools ^3.9` — Utility helpers

**PHP Libraries:**
- `erusev/parsedown ^1.7` — Markdown rendering
- `setasign/fpdf ^1.8` + `setasign/fpdi ^2.6.4` — Low-level PDF creation/manipulation
- `smalot/pdfparser ^2.12.3` — PDF parsing/reading
- `league/flysystem ^3.0` — Filesystem abstraction (local, Azure, S3)
- `azure-oss/storage-blob-flysystem ^1.2` — Azure Blob Storage Flysystem adapter
- `mobiledetect/mobiledetectlib ^4.8` — Mobile browser detection
- `symfony/yaml ^7.4` — YAML parsing

## Frontend

**JS Framework:**
- `@hotwired/stimulus ^3.2.2` — Lightweight controller-per-element JS framework
- `@hotwired/turbo ^8.0.23` — SPA-like navigation without a full SPA

**CSS Framework:**
- Bootstrap `^5.3.8` — UI component library
- `@fortawesome/fontawesome-free ^7.2.0` — Icon font

**Build Tool:**
- Laravel Mix `^6.0.49` (Webpack wrapper) — config at `app/webpack.mix.js`
  - Compiles `assets/js/` → `webroot/js/`
  - Compiles `assets/css/` → `webroot/css/`
  - Bundles all `*-controller.js` files (core app + all plugins) into `webroot/js/controllers.js`
  - Extracts vendor bundle (Bootstrap, Turbo, Stimulus) into `webroot/js/core.js`
  - Copies FontAwesome webfonts to `webroot/fonts/`
  - Asset versioning enabled (cache-busting manifest)

**Sass/SCSS:** `sass ^1.97.3` + `sass-loader ^16.0.7` — compiled via webpack

**Key Frontend Libraries:**
- `face-api.js ^0.22.2` — Face detection for profile photo validation (pinned — must not downgrade)
- `@techstark/opencv-js ^4.12.0` — Computer vision (used with face-api)
- `pdfjs-dist ^5.4.624` — Client-side PDF rendering
- `easymde ^2.20.0` — Markdown editor
- `qrcode ^1.5.4` — QR code generation (member mobile cards)
- `guifier ^1.0.33` — JSON/config editor UI
- `smol-toml ^1.6.1` — TOML parsing in browser
- `undici ^7.24.6` — HTTP client

## Database

**Engine:** MariaDB 11 (Docker dev), MariaDB 10 tested in CI
**ORM:** CakePHP ORM (built into `cakephp/cakephp`)
**Driver:** `Cake\Database\Driver\Mysql` (primary), `Cake\Database\Driver\Postgres` tested in CI
**Migrations:** CakePHP Migrations plugin (`app/config/Migrations/`, `app/plugins/*/config/Migrations/`)
**Test DB:** Separate `kmp_test` database (CI), `{name}_test` convention (local)

## Infrastructure

**Containerization:** Docker Compose (`docker-compose.yml`)
- `kmp-app`: PHP 8.3 + Apache 2 (Debian Bookworm base), port 8080→80
- `kmp-db`: MariaDB 11, port 3306
- `kmp-mailpit`: Mailpit, ports 8025 (UI) and 1025 (SMTP)

**Web Server:** Apache 2 with `mod_rewrite` and `mod_headers`
- Document root: `app/webroot/` (served from `/var/www/html/webroot` in container)
- Config: `docker/apache-vhost.conf`

**Docker Images:**
- Dev: `docker/Dockerfile.app` (built locally)
- Prod base: `ghcr.io/ansteorra/kmp-base:php83` (pre-compiled PHP extensions)
- Prod app: `ghcr.io/ansteorra/kmp` (published on release)

**Container Registry:** GitHub Container Registry (`ghcr.io`)

**PHP Extensions Required:**
- `bcmath`, `bz2`, `gd`, `intl`, `mysqli`, `opcache`, `pdo_mysql`, `pdo_pgsql`, `redis`, `zip`, `apcu`, `yaml`

## Caching

**Primary:** APCu (default dev/prod) — in-process shared memory cache
**Optional:** Redis — activated via `CACHE_ENGINE=redis` env var + `REDIS_URL`
**File Cache:** Used for restore-status lock/progress (cross-process sharing)

**Named Caches:**
- `default` — general app data (1 hour TTL)
- `member_permissions` — per-member auth data (30 minutes)
- `permissions_structure` — role/permission hierarchy (999 days)
- `branch_structure` — org hierarchy (999 days)
- `restore_status` — file-based restore state (2 days)

## Development Tooling

**PHP Testing:**
- PHPUnit `^10.1.0` — unit + integration tests (`app/tests/TestCase/`)
- Config: `app/phpunit.xml.dist`
- Test suites: `core-unit`, `core-feature`, `plugins`, `all`
- Run: `composer test` from `app/` (do NOT use `--testsuite all` directly)

**PHP Mutation Testing:**
- Infection (via `app/bin/run-infection.sh`) — targets `src/Policy/`, Awards model/table
- Config: `app/infection.json5`
- Run: `composer mutate` or `composer mutate:policy`

**PHP Static Analysis:**
- PHPStan level 5 — `app/phpstan.neon` + baseline `app/phpstan-baseline.neon` (1947 suppressed)
- Run: `composer run-script stan`

**PHP Code Style:**
- PHP CodeSniffer `^3.10` — PSR-12 + CakePHP standard (`cakephp/cakephp-codesniffer ^5.0`)
- Only checks branch-changed files: `composer run-script cs-check`
- Do NOT run `phpcbf` globally (LSP breakage risk)

**JavaScript Testing:**
- Jest `^30.2.0` — unit tests in `app/tests/js/`
- Config: `app/jest.config.js`, environment: jsdom
- Run: `npm run test:js`

**JavaScript Mutation Testing:**
- StrykerJS — targets security-critical Stimulus controllers
- Config: `app/stryker.config.js`
- Run: `npm run test:mutate`

**E2E / Browser Testing:**
- Playwright `^1.58.2` — BDD-driven browser tests
- `playwright-bdd ^8.4.2` — Gherkin `.feature` files → Playwright specs
- BDD features: `app/tests/ui/bdd/`
- Run: `npx bddgen && npx playwright test`

**Documentation:**
- JSDoc `^4.0.5` — JS API docs (`npm run docs:js`)

**Pre-Commit Verification:**
- `app/bin/verify.sh` — runs PHPUnit + Jest + Webpack build + PHPCS + PHPStan

**Dependency Patching:**
- `patch-package ^8.0.1` — applies patches in `app/patches/` post npm install

## CI/CD

**Platform:** GitHub Actions (`.github/workflows/`)

**Workflows:**
- `tests.yml` — PHPUnit against MariaDB 10 and PostgreSQL 16 on every push/PR
- `nightly.yml` — Full nightly test suite
- `base.yml` — Builds/publishes the `kmp-base:php83` Docker image
- `dev_release_build.yml` — Dev release build
- `release.yml` — Production release pipeline (publishes `ghcr.io/ansteorra/kmp`)
- `installer.yml` — Installer pipeline
- `updater.yml` — Update pipeline
- `jekyll-gh-pages.yml` — Documentation site deployment

**PHP Version in CI:** 8.3 (via `shivammathur/setup-php@v2`)

---

*Stack analysis: 2026-05-23*
