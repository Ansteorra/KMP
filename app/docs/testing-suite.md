# KMP Testing Overview

This document outlines the refreshed PHPUnit suite organization introduced in December 2025.

## 🧱 Test Architecture

| Layer | Location | Base class | Description |
| --- | --- | --- | --- |
| Core Unit | `tests/TestCase/Core/Unit` | `BaseTestCase` | Fast-running unit and table/service tests that operate purely on PHP objects or direct ORM calls. |
| Core Feature | `tests/TestCase/Core/Feature` | `HttpIntegrationTestCase` | HTTP-centric smoke tests that exercise controllers or routes without complex setup. |
| Plugins | `tests/TestCase/Plugins` | `PluginIntegrationTestCase` | Plugin-focused tests that access plugin tables or services while sharing the same seeded database. |

Support helpers live in `tests/TestCase/Support`:

- `SeedManager` – centralizes loading `../dev_seed_clean.sql` to the `test` connection.
- `HttpIntegrationTestCase` – thin HTTP feature base (extends `BaseTestCase` + `IntegrationTestTrait`).
- `PluginIntegrationTestCase` – auto-loads the plugin under test before running assertions.

## 🌱 Guaranteed Seed Data

All suites load `/workspaces/KMP/dev_seed_clean.sql` during `tests/bootstrap.php`. The new `SeedManager` ensures the SQL file is applied once per run and exposes a `reset()` helper for heavy data mutation scenarios.

When extending `BaseTestCase`, call `$this->reseedDatabase()` if your test needs a full reset after destructive operations.

## 🚀 Running Tests

| Command | Purpose |
| --- | --- |
| `vendor/bin/phpunit --testsuite core-unit` | Run fast unit/service coverage. |
| `vendor/bin/phpunit --testsuite core-feature` | Execute HTTP smoke tests. |
| `vendor/bin/phpunit --testsuite plugins` | Run cross-plugin coverage (includes `tests/TestCase/Plugins` and plugin-owned suites). |
| `vendor/bin/phpunit tests/TestCase/Core/Feature/Members/MembersLoginPageTest.php` | Target a single file. |
| `vendor/bin/phpunit --filter MembersTableSeedTest` | Target a single class/method. |

> 💡 Need the old “everything” run? Use `vendor/bin/phpunit --testsuite all`.

## 🧪 Starter Tests

- `Core/Unit/Model/MembersTableSeedTest` – verifies the super-user seed and duplicate email guardrails.
- `Core/Feature/Members/MembersLoginPageTest` – confirms anonymous access to `/members/login` remains healthy.
- `Plugins/Awards/Feature/RecommendationsSeedTest` – proves Awards plugin tables are reachable using the shared seed.

These examples demonstrate how to:

1. Depend on canonical seed accounts defined in `/README.md`.
2. Share HTTP helpers and plugin loaders.
3. Keep new tests isolated with automatic transactions from `BaseTestCase`.

## 📈 Extending the Suite

1. **Pick a layer** (unit vs feature vs plugin) and drop the file in the matching folder.
2. **Extend the correct base** (`BaseTestCase`, `HttpIntegrationTestCase`, or `PluginIntegrationTestCase`).
3. **Leverage the seed** using constants like `BaseTestCase::ADMIN_MEMBER_ID` or `$this->reseedDatabase()`.
4. **Name tests clearly** – the suite naming convention enables `--testsuite` filtering.

## 🔄 Legacy Tests

Existing suites under `tests/TestCase/Controller`, `Model`, `Services`, etc., remain runnable and are included in `core-unit`, `core-feature`, or `all`. Migrate legacy coverage into the new layout over time for the cleanest experience.

Happy testing! 🧪
