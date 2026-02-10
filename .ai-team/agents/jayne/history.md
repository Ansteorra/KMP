# Project Context

- **Owner:** Josh Handel (josh@liveoak.ws)
- **Project:** KMP — Membership management system for SCA Kingdoms. Handles members, officers, warrants, awards, activities, and workflow-driven approvals. ~2 years of active development.
- **Stack:** CakePHP 5.x, Stimulus.JS, MariaDB, Docker, Laravel Mix, Bootstrap, plugin architecture
- **Created:** 2026-02-10

## Learnings

<!-- Append new learnings below. Each entry is something lasting about the project. -->

### 2026-02-10: Test Suite — Summary (summarized from detailed audit)

**88 test files** (35 core + 53 plugin). ~536 test methods. Estimated real coverage: ~15-20%.

**Well-tested:** Authorization service (unit + edge cases + branch scoping), Members CRUD, App settings, Branches, Permissions/Roles, Gatherings, KMP helper, Officers plugin, Waivers plugin.

**Major gaps:** 20/26 core controllers untested, 26/32 tables, 29/34 entities, 36/37 policies, 6/7 commands, 0 mailer tests, 0 API tests. Activities plugin: all stubs. Awards: 0 real tests.

**Test infrastructure:**
- `BaseTestCase` → transaction wrapping + seed data constants (ADMIN_MEMBER_ID=1, TEST_MEMBER_AGATHA_ID=2871, BRYCE=2872, DEVON=2874, EIRIK=2875, KINGDOM_BRANCH_ID=2, TEST_BRANCH_LOCAL_ID=14)
- `HttpIntegrationTestCase` → HTTP tests with `TestAuthenticationHelper`
- `PluginIntegrationTestCase` → plugin HTTP tests (requires `PLUGIN_NAME` constant)
- Database: seeded via `SeedManager` + `dev_seed_clean.sql`, NOT CakePHP fixtures
- Run: `cd app && composer test` or `vendor/bin/phpunit --testsuite {core-unit|core-feature|plugins|all}`
- Queue plugin (31 tests) has own fixture system — leave alone

### 2026-02-10: KmpHelper Static State Bug (production code fix)

`KmpHelper::$mainView` is static — persists across PHPUnit test runs. `beforeRender()` stored first View and never updated, causing all subsequent tests to write blocks to a stale View. Fix: compare `$view->getRequest()` instead of just `isset()`. File: `app/src/View/Helper/KmpHelper.php` (4 lines). No production impact (static resets per PHP process).

### 2026-02-10: Template HelloWorldControllerTest Fix

- Must use `PluginIntegrationTestCase` (not `HttpIntegrationTestCase`) — Template plugin commented out in `config/plugins.php`
- Must call `enableCsrfToken()`, `enableSecurityToken()`, `authenticateAsSuperUser()` in setUp
- Flash assertions: use `$_SESSION['Flash']['flash']` not `assertFlashMessage()` (CakePHP trait reads wrong session key in KMP)

📌 Team update (2026-02-10): Architecture overview documented — 6 plugins, 37 policy classes, PermissionsLoader is security backbone, 8 dangerous-to-change areas — decided by Mal
📌 Team update (2026-02-10): Backend patterns documented — 14 critical conventions to follow, transaction ownership split (AWM=caller, WM=self), termYears is actually months — decided by Kaylee
📌 Team update (2026-02-10): Frontend patterns documented — 81 Stimulus controllers cataloged, asset pipeline via webpack.mix.js, no frontend test infrastructure exists — decided by Wash
📌 Team update (2026-02-10): Test infrastructure attack plan created — 6 phases, Jayne owns Phases 1-3, 4.1, 4.2a, 5, 6. No new features until testing is solid. — decided by Mal, Josh Handel
📌 Team update (2026-02-10): Auth triage complete — 15 TEST_BUGs, 2 CODE_BUGs classified. Kaylee fixed both CODE_BUGs (PermissionsLoader revoker_id, ControllerResolver string handling). All 370 project-owned tests now pass. — decided by Jayne, Kaylee
📌 Team update (2026-02-10): Auth strategy decided — standardize on TestAuthenticationHelper, deprecate old traits. ⚠️ Gap: authenticateAsSuperUser() does not set permissions — must be fixed before migrating tests. — decided by Mal

### 2026-02-10: Queue Plugin Test Triage — Complete

**119 tests total: 38 pass, 81 fail (68 errors + 13 failures). 0 CODE_BUGs. 0 COMPAT issues.**

All 81 failures stem from 5 infrastructure/config root causes — the Queue plugin was ripped from its standalone test harness and dropped into KMP without adapting either side.

**Root causes (ordered by impact):**
1. **"Plugin already loaded"** (16 errors) — Queue tests call `$this->loadPlugins(['Queue'])` but KMP bootstrap already loads it. Fix: remove the calls.
2. **Missing Admin prefix routes** (29 errors) — Controller tests use `'prefix' => 'Admin'` but controllers were moved out of Admin namespace. Fix: remove `prefix` from URL arrays.
3. **TestApp/Foo autoload missing** (15 errors) — Queue's `composer.json` has `autoload-dev` for test stubs (`TestApp\`, `Foo\`) but KMP's doesn't include them. Fix: add to KMP's `autoload-dev`.
4. **No data isolation** (16 errors/failures) — Commit `6e25eea4` bulk-deleted `$fixtures` declarations. Queue tests need fixtures for table truncation. Fix: restore fixture declarations.
5. **Email transport config** (3 errors/failures) — Tests expect `Debug` transport, KMP configures `Smtp`. Fix: configure Debug transport in test setUp.

**Silver bullet:** Fixes #1 + #2 resolve 45 of 68 errors. All 5 fixes together resolve all 81 failures.

**Key insight:** Queue tests use CakePHP fixture-based isolation, NOT BaseTestCase transaction wrapping. The fixture removal was the biggest self-inflicted wound — it broke data isolation for every test that writes to `queued_jobs` or `queue_processes`. Do NOT migrate Queue to BaseTestCase yet — restore fixtures first, evaluate migration after all 119 pass.

**Controller auth warning:** Once routes are fixed, controller tests will likely hit KMP's auth middleware. That's a Phase 5 problem — tests don't authenticate as any user.

Full triage report: `.ai-team/decisions/inbox/jayne-queue-test-triage.md`

📌 Team update (2026-02-10): Queue plugin ownership review — decided to own the plugin, security issues found, test triage complete

### 2026-02-10: Queue Plugin Test Fixes — All 5+ Root Causes Fixed

**Result: 0 errors, 0 failures, 6 skips (from 81 failures)**

Fixed all root causes from the triage:
1. **loadPlugins**: Removed redundant `$this->loadPlugins(['Queue'])` from 9 files
2. **Admin prefix**: Removed `'prefix' => 'Admin'` from 3 controller test files (18 occurrences)
3. **Autoload**: Added TestApp/Foo PSR-4 entries to `composer.json` autoload-dev
4. **Fixtures**: Added `$fixtures` declarations to 14 test files
5. **Email transport**: Force Debug transport in setUp for EmailTaskTest, MailerTaskTest, SimpleQueueTransportTest

**Additional fixes discovered during execution:**
- `app_queue.php` had EmailTask in `ignoredTasks` — cleared the list (all other entries were deleted example tasks)
- Deleted 9 test files for Kaylee's deleted example/execute tasks
- Updated 22 test files to reference Queue.Email/Queue.Mailer instead of deleted tasks
- Added TestAuthenticationHelper + CSRF/security tokens to controller tests
- Created test fixture file and email template for missing test infrastructure
- Fixed TestMailer to produce correct debug output format
- 3 tests skipped: bake reference files not migrated, TestApp Foo task not scannable

**Auth update:** Controller auth works via `TestAuthenticationHelper::authenticateAsSuperUser()` — no issues with Queue controllers + authorization once authenticated.

Full report: `.ai-team/decisions/inbox/jayne-queue-test-fixes.md`

📌 Team update (2026-02-10): Documentation accuracy review completed — all 4 agents reviewed 96 docs against codebase
