# Project Context

- **Owner:** Josh Handel (josh@liveoak.ws)
- **Project:** KMP — Membership management system for SCA Kingdoms. Handles members, officers, warrants, awards, activities, and workflow-driven approvals. ~2 years of active development.
- **Stack:** CakePHP 5.x, Stimulus.JS, MariaDB, Docker, Laravel Mix, Bootstrap, plugin architecture
- **Created:** 2026-02-10

## Learnings

<!-- Append new learnings below. Each entry is something lasting about the project. -->

### 2026-02-10: Test Suite Audit — Initial Assessment

#### Test File Inventory

**Total test files:** 88 (35 core + 53 plugin)
**Total test methods:** ~536
**Incomplete/Skipped tests:** ~70+ across all files

**Core test files (35) in `app/tests/TestCase/`:**
- `Controller/` (7): AppSettingsControllerTest, BranchesControllerTest, ExampleSuperUserTest, GatheringActivitiesControllerTest, GatheringsControllerTest, GatheringTypesControllerTest, MembersControllerTest
- `Model/Entity/` (4): GatheringTest, MemberTest, PermissionTest, RoleTest
- `Model/Table/` (6): AppSettingsTableTest, BranchesTableTest, GatheringStaffTableTest, MembersTableTest, PermissionsTableTest, RolesTableTest
- `Services/` (8): AuthorizationEdgeCasesTest, AuthorizationServiceTest, BranchScopedAuthorizationTest, ICalendarServiceTest, ImageToPdfConversionServiceTest, NavigationRegistryTest, RetentionPolicyServiceTest, SecurityDebugTest
- `KMP/` (1): PermissionsLoaderTest
- `Command/` (1): SyncMemberWarrantableStatusesCommandTest
- `Core/Feature/Members/` (1): MembersLoginPageTest
- `Core/Unit/Model/` (1): MembersTableSeedTest
- `Middleware/` (1): TestAuthorizationMiddlewareTest (incomplete)
- `View/Helper/` (2): KmpHelperTest, KmpHelperUploadLimitsTest
- `Plugins/Awards/Feature/` (1): RecommendationsSeedTest
- Support files (not tests): BaseTestCase, HttpIntegrationTestCase, PluginIntegrationTestCase, SeedManager, AuthenticatedTrait, SuperUserAuthenticatedTrait, TestAuthenticationHelper, TestDatabaseTrait

**Plugin test files (53, excluding Queue's 31):**
- Activities (7): All View/Cell tests — all `markTestIncomplete`
- Awards (2): MemberSubmittedRecsCellTest, RecsForMemberCellTest — all `markTestIncomplete`
- Officers (5): OfficesControllerTest, DefaultOfficerManagerTest, 3 Cell tests (incomplete)
- Waivers (7): GatheringWaiversControllerTest, WaiverTypesControllerTest, HelloWorldControllerTest, WaiverTypeTest (entity), WaiverTypesTableTest, WaiverTypePolicyTest, ImageToPdfConversionServiceTest
- Template (1): HelloWorldControllerTest
- Queue (31): Third-party plugin, comprehensive own tests
- Bootstrap (0), GitHubIssueSubmitter (0)

#### Test Infrastructure & Patterns

**Base classes hierarchy:**
1. `BaseTestCase` extends `Cake\TestSuite\TestCase` — transaction wrapping (begin in setUp, rollback in tearDown), test data ID constants, helper assertions (`assertRecordExists`, `assertRecordNotExists`, `assertRecordCount`)
2. `HttpIntegrationTestCase` extends `BaseTestCase` — adds `IntegrationTestTrait` + `TestAuthenticationHelper` for HTTP integration tests
3. `PluginIntegrationTestCase` extends `HttpIntegrationTestCase` — adds plugin auto-loading via `PLUGIN_NAME` constant

**Authentication traits (two approaches):**
1. `AuthenticatedTrait` — loads admin from DB, manually sets permissions, saves entity to session
2. `SuperUserAuthenticatedTrait` — similar but cleaner, newer pattern
3. `TestAuthenticationHelper` — lightweight trait with `authenticateAsSuperUser()`, `authenticateAsAdmin()`, `authenticateAsMember()` using session arrays (no DB lookups)

**Database strategy: Seeded database + transaction isolation (NOT CakePHP fixtures)**
- `SeedManager` loads `dev_seed_clean.sql` once at bootstrap into the `test` connection
- Each test wraps in a transaction, rolls back in tearDown
- `BaseTestCase` has constants for known seed data IDs (ADMIN_MEMBER_ID=1, TEST_MEMBER_AGATHA_ID=2871, etc.)
- `TestDatabaseTrait` provides additional helpers (`insertTestData`, `cleanTable`, `resetTestDatabase`)
- No traditional CakePHP fixture files used in core (Waivers and Queue plugins have some fixture files)

**Assertion patterns:**
- Standard CakePHP: `assertResponseOk()`, `assertResponseContains()`, `assertRedirect()`
- Custom in BaseTestCase: `assertRecordExists()`, `assertRecordNotExists()`, `assertRecordCount()`
- Auth assertions in TestAuthenticationHelper: `assertAuthenticated()`, `assertNotAuthenticated()`, `assertAuthenticatedAs()`

**PHPUnit configuration (`app/phpunit.xml`):**
- PHPUnit 10.x with 4 test suites:
  - `core-unit`: Model/, Services/, KMP/, Core/Unit/
  - `core-feature`: Controller/, Middleware/, View/, Core/Feature/
  - `plugins`: Plugins/ + plugins/*/tests/TestCase/
  - `all`: everything
- Bootstrap: `tests/bootstrap.php`
- Memory limit: unlimited
- Coverage source: `src/` + `plugins/*/src/` (excludes `src/Console/Installer.php`)

**How to run tests:**
- `cd app && composer test` (alias for `phpunit --colors=always`)
- `cd app && vendor/bin/phpunit --testsuite core-unit`
- `cd app && vendor/bin/phpunit --testsuite core-feature`
- `cd app && vendor/bin/phpunit --testsuite plugins`
- Requires MariaDB test database (Docker via `docker-compose.yml`)

#### Coverage Assessment — BRUTAL HONESTY

**What IS tested (decent coverage):**
- Members CRUD (controller + table + entity)
- Authorization service (unit + edge cases + branch scoping) — this is the strongest area
- App settings, branches, permissions, roles (basic CRUD)
- Gatherings and gathering types (controller level)
- KMP helper (view helper)
- Permissions loader
- Navigation registry, retention policy, iCalendar service
- Officers plugin: OfficesController + DefaultOfficerManager (solid)
- Waivers plugin: controllers, table, entity, policy (reasonable)

**What is NOT tested (massive gaps):**
- **20 of 26 core controllers have zero tests** — Warrants, Roles, Permissions, Notes, MemberRoles, EmailTemplates, ServicePrincipals, Sessions, Reports, etc.
- **26 of 32 core table classes have zero tests**
- **29 of 34 core entity classes have zero tests**
- **14 of 19 core services have zero tests** — DocumentService, CsvExportService, ImpersonationService, EmailTemplateRendererService, etc.
- **36 of 37 core policy classes have zero tests** (only WaiverTypePolicy has a test, and it's just "does it instantiate")
- **6 of 7 commands have zero tests**
- **0 mailer tests** — KMPMailer, QueuedMailerAwareTrait, TemplateAwareMailerTrait are completely untested
- **Activities plugin**: 7 Cell test files exist but ALL are `markTestIncomplete` — effectively 0 real tests
- **Awards plugin**: 2 Cell test files, both `markTestIncomplete` — 0 real tests; 0 controller/table/policy tests
- **GitHubIssueSubmitter**: 0 tests
- **Bootstrap plugin**: 0 tests (UI helpers)
- **API controllers** (`src/Controller/Api/`): 0 tests

**Estimated real coverage: ~15-20% of application code**

The authorization subsystem is well-tested. Everything else is skeletal at best. Most plugin Cell tests are auto-generated stubs that were never implemented.

#### CI/CD

- **No CI test runner** — the only GitHub Actions workflow (`dev_release_build.yml`) does JS builds only
- Tests must be run manually or via Docker
- No test stage in Docker build process

#### Specs Directory

- `specs/001-build-out-waiver/` — full feature spec with data model, decisions, plan, tasks, user flows
- `specs/this-is-a-test-specification-of-a-cool-new-feature/` — appears to be a template/example

#### Key File Paths

- PHPUnit config: `app/phpunit.xml`
- Test bootstrap: `app/tests/bootstrap.php`
- Base test class: `app/tests/TestCase/BaseTestCase.php`
- HTTP integration base: `app/tests/TestCase/Support/HttpIntegrationTestCase.php`
- Plugin integration base: `app/tests/TestCase/Support/PluginIntegrationTestCase.php`
- Seed manager: `app/tests/TestCase/Support/SeedManager.php`
- Auth traits: `app/tests/TestCase/Controller/AuthenticatedTrait.php`, `SuperUserAuthenticatedTrait.php`
- Auth helper: `app/tests/TestCase/TestAuthenticationHelper.php`
- Database trait: `app/tests/TestCase/TestDatabaseTrait.php`
- Seed SQL: `app/dev_seed_clean.sql` (referenced from repo root)
- Misplaced test: `app/tests/ViewCellRegistryTest.php` (standalone script, not a proper PHPUnit test)

📌 Team update (2026-02-10): Architecture overview documented — 6 plugins, 37 policy classes, PermissionsLoader is security backbone, 8 dangerous-to-change areas — decided by Mal
📌 Team update (2026-02-10): Backend patterns documented — 14 critical conventions to follow, transaction ownership split (AWM=caller, WM=self), termYears is actually months — decided by Kaylee
📌 Team update (2026-02-10): Frontend patterns documented — 81 Stimulus controllers, asset pipeline via webpack.mix.js, no frontend test infrastructure exists — decided by Wash

### 2026-02-10: Test Infrastructure Deep Dive — Hands-On Execution

**Ran all 4 test suites. Here's what actually happened.**

#### Suite Execution Results
- **core-unit**: 183 tests — 160 pass, 2 errors, 7 failures, 14 incomplete. Deterministic on back-to-back runs.
- **core-feature**: 97 tests — 70 pass, 15 failures, 9 skipped, 3 incomplete. Deterministic.
- **plugins**: ❌ FATAL — namespace collision. `Waivers/HelloWorldControllerTest.php` uses `Template\Test\TestCase\Controller` namespace. Kills entire suite.
- **all**: ❌ FATAL — same collision.

#### Critical Findings

1. **73% of test files (64/88) lack transaction wrapping** — extend raw `TestCase` instead of `BaseTestCase`. 17 of those files write data to the DB without cleanup. State leakage is real but currently masked by idempotent writes and failing tests.

2. **BaseTestCase has 2 wrong constants** — `KINGDOM_BRANCH_ID=1` (no branch ID 1 exists; Ansteorra is ID 2) and `TEST_BRANCH_LOCAL_ID=1073` (max branch ID is 42). Any test using these silently gets wrong results.

3. **Authentication traits write to DB in setUp** — `AuthenticatedTrait` and `SuperUserAuthenticatedTrait` call `$membersTable->save()` without transaction wrapping when used with raw `TestCase`. Safe by accident (saves same data each time).

4. **37 of 54 markTestIncomplete stubs are cop-outs** — auto-generated View/Cell stubs that were never implemented. 17 are genuine (need Imagick, service mocking, policy implementation).

5. **7 authorization test failures are consistent** — revoked roles still grant permissions, expired membership check has wrong date, warrantable flag wrong. Need triage: bugs in code vs bugs in tests.

6. **Duplicate files**: `ImageToPdfConversionServiceTest` exists in both core and Waivers plugin. `HelloWorldControllerTest` copied from Template to Waivers with same namespace.

#### Priority Fix Order
1. Fix namespace collision (unblocks plugins/all suites)
2. Fix BaseTestCase constants (unblocks branch-related tests)
3. Migrate 17 data-writing test files to BaseTestCase (fixes cleanup)
4. Consolidate authentication traits (stop saving admin in setUp)
5. Triage 7 authorization failures (code bugs vs test bugs)
6. Delete dead stubs or implement them
7. Add PHPUnit to CI pipeline

#### Key Insight
The infrastructure DESIGN (BaseTestCase + transactions + SeedManager) is solid. The problem is ADOPTION — most tests predate the infrastructure and were never migrated. Decision logged to `.ai-team/decisions/inbox/jayne-test-infrastructure-deep-dive.md`.

📌 Team update (2026-02-10): Test infrastructure attack plan created — 6 phases, Jayne owns Phases 1-3, 4.1, 4.2a, 5, 6. No new features until testing is solid. — decided by Mal, Josh Handel

### 2026-02-10: Fixed 5 Controller Test Failures — KmpHelper Static State Bug

**Root Cause:** `KmpHelper::$mainView` is a `static` property that persists across all test runs in a single PHPUnit process. The `beforeRender()` method stored the first View instance and never updated it, so all subsequent HTTP requests in tests wrote their view blocks (pageTitle, recordDetails, recordActions, etc.) to the stale View from the first request. This caused response bodies to shrink from ~42KB to ~15KB after the first test, with all block content missing.

**Fix:** Changed the `beforeRender()` condition in `KmpHelper` (line 49) from checking `isset(self::$mainView)` to comparing `self::$mainView->getRequest() !== $view->getRequest()`. Cell views share the same request as their parent view, so they still won't overwrite the main view within a single request. But a new test/request gets a fresh View stored.

**Tests Fixed (all 5 had the same root cause):**
1. `GatheringActivitiesControllerTest::testViewShowsAssociatedWaivers` — 'Armored Combat' missing
2. `GatheringActivitiesControllerTest::testWaiverRequirementMarkingDisplayed` — 'Armored Combat' missing
3. `GatheringTypesControllerTest::testView` — 'Kingdom Calendar Event' missing
4. `MembersControllerTest::testViewShowsImpersonateButtonForSuperUsers` — 'Impersonate Member' missing
5. `WaiverTypesControllerTest::testView` — 'Participation Roster Waiver' missing

**File changed:** `app/src/View/Helper/KmpHelper.php` — 4 lines changed in `beforeRender()`

**No production impact** — in production, each HTTP request is a separate PHP process, so static state never persists between requests. This only affects multi-request scenarios like PHPUnit test suites.

### 2026-02-10: Template HelloWorldControllerTest — Fixed 14 Failures + 5 Risky

#### Root Causes (3 issues)

1. **MissingControllerException (14 failures):** Test extended `HttpIntegrationTestCase` instead of `PluginIntegrationTestCase`. The Template plugin is commented out in `config/plugins.php`, so its routes aren't loaded by default. Without explicit plugin loading, CakePHP interpreted `/template/hello-world` as controller=Template (which doesn't exist in the core app). Fix: Changed base class to `PluginIntegrationTestCase` with `PLUGIN_NAME = 'Template'`.

2. **Missing authentication/authorization:** The controller calls `$this->authorizeCurrentUrl()` on every action. Without `authenticateAsSuperUser()` and CSRF/security tokens in setUp, all requests would fail authorization. Fix: Added `enableCsrfToken()`, `enableSecurityToken()`, and `authenticateAsSuperUser()` to setUp.

3. **Flash message assertions (3 failures after routing fix):** CakePHP's `assertFlashMessage()` reads from `$this->_requestSession` which is empty in KMP's test environment. Flash messages are stored in `$_SESSION['Flash']['flash']` instead (same pattern as `OfficesControllerTest`). Fix: Replaced `assertFlashMessage()` with direct `$_SESSION` reads.

4. **Risky tests (5 empty test bodies):** Five stub methods had all code commented out and no assertions, triggering PHPUnit's "risky test" warning. Fix: Added `markTestIncomplete()` with descriptive messages explaining what each stub needs to become a real test.

#### Final Result
- 14 tests: 9 passing, 5 incomplete (stubs), 0 failures, 0 risky
- (28 total when counting the Waivers namespace-collision copy)

📌 Team update (2026-02-10): Auth triage complete — 15 TEST_BUGs, 2 CODE_BUGs classified. Kaylee fixed both CODE_BUGs (PermissionsLoader revoker_id, ControllerResolver string handling). All 370 project-owned tests now pass. — decided by Jayne, Kaylee
📌 Team update (2026-02-10): Auth strategy decided — standardize on TestAuthenticationHelper, deprecate old traits. ⚠️ Gap: authenticateAsSuperUser() does not set permissions — must be fixed before migrating tests. — decided by Mal
