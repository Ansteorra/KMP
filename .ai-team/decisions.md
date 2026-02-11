# Team Decisions

> Shared brain — all agents read this. Scribe merges new decisions from the inbox.

### 2026-02-10: KMP Architecture Overview (existing)
**By:** Mal
**What:** Documented existing architecture after initial codebase exploration
**Why:** Team is new to a 2-year-old codebase — need shared understanding before making changes

---

#### 1. Plugin System

KMP uses CakePHP's plugin architecture with custom interfaces layered on top.

**Registration flow:**
1. Plugins listed in `app/config/plugins.php` with optional `migrationOrder`
2. Plugin class implements `KMPPluginInterface` (requires `getMigrationOrder()`)
3. In `bootstrap()`, plugins register: navigation (`NavigationRegistry`), view cells (`ViewCellRegistry`), AppSettings (`StaticHelpers`), and optionally API routes (`KMPApiPluginInterface`)
4. Plugins register DI services in their own `services()` method
5. Enable/disable at runtime via `Plugin.{Name}.Active` AppSetting

**Active domain plugins:** Activities (auth/activities), Officers (warrants/rosters), Awards (recommendations/state machine), Waivers (gathering waivers)
**Infrastructure plugins:** Queue (async jobs), GitHubIssueSubmitter (bug reports)

**Team rule:** Follow the existing plugin pattern exactly. New plugins should model after the Template plugin (reference implementation). Never bypass the registry pattern for navigation or view cells.

#### 2. Service Layer

**DI-registered services** (in `Application::services()`):
- `ActiveWindowManagerInterface` → `DefaultActiveWindowManager` — time-bounded entities
- `WarrantManagerInterface` → `DefaultWarrantManager` — warrant lifecycle (depends on ActiveWindowManager)
- `CsvExportService`, `ICalendarService`, `ImpersonationService`

**Static registries** (not DI):
- `NavigationRegistry` — menu items, session-cached
- `ViewCellRegistry` — plugin UI components, route-matched
- `ApiDataRegistry` — API response enrichment

**Critical convention:** All service methods return `ServiceResult(success, reason, data)`. Never throw exceptions from services. ActiveWindowManager does NOT manage transactions (caller must). WarrantManager manages its own transactions (do NOT wrap).

#### 3. Auth & Authorization

**Authentication:** Dual-mode — session+form for web, Bearer token for API. Brute-force protection. Legacy MD5→bcrypt password migration. Mobile card token auth for PWA.

**Authorization:** Policy-based with ORM + Controller resolvers. 37 policy classes. Super-users auto-pass via `BasePolicy::before()`. Permission chain: Members → MemberRoles (temporal) → Roles → Permissions → Policies. Three scoping levels: Global, Branch Only, Branch+Children. Cached via `PermissionsLoader`.

**Team rule:** Every controller action must be authorized or explicitly skipped. Use `$this->Authorization->authorizeModel()` in `beforeFilter()`. All new policies must extend `BasePolicy`.

#### 4. Database Patterns

**Entity hierarchy:** `BaseEntity` (has `getBranchId()`) or `ActiveWindowBaseEntity` (time-bounded). **Table hierarchy:** All extend `BaseTable` (cache hooks, audit logging).

**Custom behaviors:** `ActiveWindowBehavior` (temporal), `JsonFieldBehavior` (JSON queries), `PublicIdBehavior` (anti-enumeration), `SortableBehavior` (ordering).

**Conventions:** JSON columns declared in `getSchema()`, queried via `JsonFieldBehavior`. AppSettings in DB via `StaticHelpers`. Soft deletes via `Muffin/Trash`. Audit via `Muffin/Footprint`.

**Team rule:** Never extend `Table` directly — always extend `BaseTable`. Never extend `Entity` directly — extend `BaseEntity` or `ActiveWindowBaseEntity`. Always implement `getBranchId()` on new entities.

#### 5. Frontend Architecture

**Stack:** Stimulus.JS (60 controllers) + Turbo Frames (Drive disabled) + Bootstrap 5.3.6. Build via Laravel Mix (`app/webpack.mix.js`).

**Controller pattern:** Register via `window.Controllers["name"] = ControllerClass`. Auto-discovered by webpack from `assets/js/controllers/` and `plugins/*/assets/js/controllers/`.

**Template system:** Block-based (`$this->KMP->startBlock()`/`endBlock()`). 6 layouts. Plugin content injected via ViewCellRegistry. Tab ordering via CSS flexbox (`data-tab-order` + `style="order: N;"`).

**Team rule:** Follow the `window.Controllers` registration pattern exactly. Use Turbo Frames, not Turbo Drive. Use Bootstrap utilities before writing custom CSS. New plugin CSS must be manually added to `webpack.mix.js`.

#### 6. Email System

All email sent asynchronously via Queue plugin. Use `QueuedMailerAwareTrait::queueMail()` — never send synchronously. Format dates with `TimezoneHelper::formatDate()` before passing to mailers. Templates receive pre-formatted strings.

#### 7. API Layer

Versioned at `/api/v1/`. Bearer token auth (ServicePrincipals). CSRF skipped. Plugins extend via `KMPApiPluginInterface::registerApiRoutes()`. Response enrichment via `ApiDataRegistry`. OpenAPI spec merged from core + plugins.

#### 8. Configuration

- `.env` for secrets (DB, SMTP, Azure)
- `app.php`/`app_local.php` for framework config
- `plugins.php` for plugin registration
- AppSettings in DB for runtime config (version-gated defaults)
- Docker env vars override `.env` when `APP_NAME=KMP_DOCKER`

#### 9. What's Dangerous to Change

1. **`BaseEntity`/`BaseTable` hierarchy** — Everything depends on these. Changing them breaks authorization, auditing, and cache invalidation across the entire app.
2. **`PermissionsLoader` and the permission chain** — This is the security backbone. Changes here can silently break access control.
3. **`ServiceResult` pattern** — Every service consumer expects this interface. Breaking it cascades everywhere.
4. **`NavigationRegistry`/`ViewCellRegistry` static registration** — All plugins depend on this. Changing the registration API breaks every plugin.
5. **Middleware order in `Application::middleware()`** — Order matters for security. CSRF must come before auth. Auth must come before authz.
6. **`ActiveWindowBehavior` temporal logic** — Warrants, roles, authorizations all depend on this. Changes affect officer eligibility, security permissions, and member status.
7. **Transaction ownership** — ActiveWindowManager (caller owns) vs WarrantManager (self-owns). Mixing this up causes data corruption.
8. **`window.Controllers` registration pattern** — All 60 Stimulus controllers use this. Changing it breaks the entire frontend.

### 2026-02-10: Backend patterns and conventions (existing)
**By:** Kaylee
**What:** Documented existing backend patterns after codebase exploration
**Why:** New team needs to understand service layer, workflow engine, and DI patterns before making changes

#### Critical Patterns Any Developer Must Follow

1. **Service Result Pattern:** All service methods MUST return `ServiceResult(bool $success, ?string $reason, $data)`. Check `$result->success` before proceeding. Never throw exceptions from service methods — return failure results instead.

2. **DI Registration:** Core services registered in `Application::services()`. Plugin services registered in their own `Plugin::services()` method. Use interface→implementation bindings. Dependencies are injected via `->addArgument()`.

3. **Transaction Management:**
   - `ActiveWindowManager` does NOT manage transactions. Callers MUST wrap in their own begin/commit/rollback.
   - `WarrantManager` manages its own transactions internally. Do NOT wrap WarrantManager calls in another transaction.
   - Pattern: `$table->getConnection()->begin()` → operations → `commit()` or `rollback()`. No use of CakePHP's `transactional()` closure.

4. **Entity Hierarchy:** New entities should extend `BaseEntity` (for auth support) or `ActiveWindowBaseEntity` (for time-bounded entities). All entities need `getBranchId()` for authorization scoping.

5. **Table Hierarchy:** All tables MUST extend `BaseTable` (not `Table` directly). This provides cache invalidation hooks and impersonation audit logging.

6. **Policy Pattern:** All policies extend `BasePolicy`. Super users auto-pass via `before()`. Use `_hasPolicy()` for permission checks. `_getBranchIdsForPolicy()` for data scoping.

7. **Plugin Architecture:** Plugins implement `KMPPluginInterface` (requires `getMigrationOrder()`). Register navigation via `NavigationRegistry::register()` and view cells via `ViewCellRegistry::register()` in `bootstrap()`. Plugin enable/disable via `Plugin.{Name}.Active` AppSetting.

8. **Email Sending:** ALWAYS use `QueuedMailerAwareTrait::queueMail()` for async delivery via Queue plugin. NEVER send email synchronously. Format all dates with `TimezoneHelper::formatDate()` before passing to mailers.

9. **JSON Columns:** Declare via `getSchema()->setColumnType('field', 'json')` in table's `getSchema()`. Use `JsonFieldBehavior::addJsonWhere()` for querying.

10. **Controller Authorization:** Call `$this->Authorization->authorizeModel('action1', 'action2')` in `beforeFilter()` for table-level actions. Call `$this->Authentication->allowUnauthenticated([...])` for public endpoints. Every action must be authorized or explicitly skipped.

11. **ActiveWindow Entities:** Use `ActiveWindowBehavior` on tables. Entity status lifecycle: Upcoming → Current → Expired/Deactivated/Cancelled/Replaced. The `termYears` parameter in `start()` is actually MONTHS (misleading name — do not assume years).

12. **AppSettings:** Use `StaticHelpers::getAppSetting(key, default, type, createIfMissing)` / `setAppSetting()`. Version-gated defaults in bootstrap. Complex values use YAML format.

13. **Permissions Flow:** Members → MemberRoles (temporal) → Roles → Permissions → PermissionPolicies → Policy classes. PermissionsLoader caches result. Three scoping levels: Global, Branch Only, Branch+Children.

14. **DataverseGrid:** Use `DataverseGridTrait` for index pages. Apply authorization scope to base query BEFORE passing to `processDataverseGrid()`.

### 2026-02-10: Frontend patterns and conventions (existing)
**By:** Wash
**What:** Documented existing frontend patterns after deep codebase exploration
**Why:** New team needs to understand Stimulus controller patterns, template system, and asset pipeline before making changes

#### Controller Registration Pattern
All Stimulus controllers follow the `window.Controllers` registration pattern:
```javascript
class MyController extends Controller {
    static targets = [...]
    static values = { ... }
    // ...
}
if (!window.Controllers) { window.Controllers = {}; }
window.Controllers["my-controller"] = MyController;
```
Registration happens in `index.js` via `stimulusApp.register(name, class)` loop. Controllers are NOT auto-loaded via Stimulus webpack helpers — they're manually discovered by `webpack.mix.js` scanning for `*-controller.js` files in `assets/js/controllers/` and `plugins/*/assets/js/controllers/`.

#### Turbo Configuration
Turbo Drive is **disabled** (`Turbo.session.drive = false`). Only Turbo Frames are used. This means standard link clicks cause full page loads — only elements within `<turbo-frame>` tags get partial updates. This is intentional.

#### Tab Ordering System
Tabs use the `detail-tabs` Stimulus controller with CSS flexbox ordering:
- Both tab buttons and content panels need `data-tab-order="N"` AND `style="order: N;"`
- Plugin tabs get order from `ViewCellRegistry` cell configuration
- Template tabs set order explicitly
- Guidelines: 1-10 plugins, 10-20 primary, 20-30 secondary, 30+ admin, 999 fallback
- Tab state is persisted in the URL

#### Inter-Controller Communication
The `outlet-btn` controller is the standard pattern for controller-to-controller communication. It dispatches custom events with data payloads. Connected controllers implement `outletBtnOutletConnected()` and `outletBtnOutletDisconnected()` handlers. This is used extensively in Officers, Activities, and revocation workflows.

#### Asset Build Pipeline
- `webpack.mix.js` auto-discovers controller files recursively from `assets/js/controllers/` and `plugins/*/assets/js/controllers/`
- All controllers bundled into single `controllers.js`
- Core libs (bootstrap, stimulus, turbo) extracted to `core.js`
- CSS compiled: `app.css` (main), `signin.css`, `cover.css`, `dashboard.css`, plus Waivers plugin CSS
- Plugin CSS outside Waivers is NOT automatically compiled — must be manually added to webpack.mix.js
- Build commands run from `app/` directory: `npm run dev` or `npm run prod`

#### CSS Conventions
- Bootstrap 5.3.6 is primary — use Bootstrap utility classes before writing custom CSS
- Icons: Bootstrap Icons (CDN) + FontAwesome Free (npm)
- `app.css` is the aggregator — imports bootstrap, icon sizes, dashboard, EasyMDE, FontAwesome
- Custom CSS is minimal and lives in `assets/css/`
- Plugin-specific CSS goes in `plugins/{Plugin}/assets/css/`

#### Template Architecture
- **6 layouts**: default (main), ajax (bare), turbo_frame (bare), mobile_app (PWA), public_event (standalone), error
- Default layout uses block-based architecture: `css`, `topscript`, `content`, `modals`, `script`, `tb_flash`, `tb_footer`
- Use `$this->KMP->startBlock()` / `$this->KMP->endBlock()` for block management (works across view cells)
- Plugin content injected via `pluginTabButtons.php`, `pluginTabBodies.php`, `pluginDetailBodies.php` elements

#### ViewCellRegistry
- Plugins register view cells in their Plugin.php via `ViewCellRegistry::register()`
- Cell types: `tab`, `detail`, `modal`, `json`, `mobile_menu`
- Cells are route-matched via `validRoutes` array and optional `authCallback`
- Replaces older event-based cell system

#### Offline/PWA Support
- Mobile views have full PWA support with service worker, offline detection, and IndexedDB caching
- `OfflineQueueService` queues actions when offline, auto-syncs when online
- `RsvpCacheService` caches RSVPs for offline access
- `mobile-offline-overlay` controller shows blocking overlay when offline
- `member-mobile-card-pwa` controller manages service worker lifecycle

#### Key Conventions
- Controller file naming: `{name}-controller.js` (kebab-case)
- Registration key matches kebab-case name, except `auto-complete` → `ac`
- Empty controller files exist: `gathering-public-controller.js`, `mobile-hub-controller.js`
- Base classes exist but aren't registered: `BaseGatheringFormController`
- Two duplicate `hello-world-controller.js` exist (Template and Waivers plugins)

### 2026-02-10: Test infrastructure assessment (consolidated)
**By:** Jayne
**What:** Audited and ran the full test suite; documented patterns, gaps, infrastructure blockers, and quality assessment
**Why:** New team needs reliable test infrastructure before writing new tests or features. Initial audit identified patterns and gaps; hands-on execution revealed deeper infrastructure problems.

---

#### Test Run Results

**Environment:** PHP 8.3.30, PHPUnit 10.5.45, MariaDB 10.11.14

| Suite | Status | Tests | Pass | Error | Fail | Incomplete | Skipped |
|-------|--------|-------|------|-------|------|------------|---------|
| core-unit | ✅ Runs | 183 | 160 | 2 | 7 | 14 | 0 |
| core-feature | ✅ Runs | 97 | 70 | 0 | 15 | 3 | 9 |
| plugins | ❌ FATAL | — | — | — | — | — | — |
| all | ❌ FATAL | — | — | — | — | — | — |

Plugins and all suites crash due to namespace collision: `Waivers/HelloWorldControllerTest.php` uses `Template\Test\TestCase\Controller` namespace. Results are deterministic on consecutive runs.

#### Test Patterns to Follow

1. **Always extend `BaseTestCase`** for any test that touches the database. It provides transaction wrapping (begin/rollback) so tests don't mutate shared seed data. Do NOT use CakePHP fixture classes — this project uses a seeded database approach with `dev_seed_clean.sql`.
2. **For HTTP/controller tests**, extend `HttpIntegrationTestCase` (or `PluginIntegrationTestCase` for plugin controllers). Use `SuperUserAuthenticatedTrait` for tests that need full admin access. Use `TestAuthenticationHelper` trait when you need lighter-weight auth without DB lookups.
3. **Reference seed data via `BaseTestCase` constants** — `ADMIN_MEMBER_ID`, `TEST_MEMBER_AGATHA_ID`, `TEST_MEMBER_BRYCE_ID`, `TEST_MEMBER_DEVON_ID`, `TEST_MEMBER_EIRIK_ID`, `KINGDOM_BRANCH_ID`, etc. Do NOT hardcode IDs.
4. **Prefer `SuperUserAuthenticatedTrait`** for new tests (newer, cleaner than `AuthenticatedTrait`).
5. **PHPUnit 10.x** with 4 test suites: `core-unit`, `core-feature`, `plugins`, `all`. Run with: `cd app && composer test` or `cd app && vendor/bin/phpunit --testsuite <suite-name>`.

#### Infrastructure Blockers

1. **Namespace collision kills plugins + all suites** — `plugins/Waivers/tests/TestCase/Controller/HelloWorldControllerTest.php` is an exact copy of Template's test with namespace `Template\Test\TestCase\Controller`. Fatal error.
2. **73% of test files lack transaction wrapping** — 64 of 88 test files extend raw `TestCase` instead of `BaseTestCase`. 17 of those write data without cleanup. State leakage is real but currently masked by idempotent writes.
3. **Duplicate test file** — `ImageToPdfConversionServiceTest` exists in both core and Waivers plugin.
4. **Wrong constants in BaseTestCase** — `KINGDOM_BRANCH_ID = 1` (no branch with ID 1; Ansteorra is ID 2) and `TEST_BRANCH_LOCAL_ID = 1073` (max branch ID is 42).
5. **Bootstrap warnings** — `session_id()`, file permission warnings, `apcu` module warning. Non-fatal but noisy.
6. **7 authorization tests fail consistently** — revoked roles still granting permissions, expired membership not losing permissions, warrantable flag wrong, view-other-member too permissive. Could be code bugs or test bugs — needs investigation.

#### Database Cleanup Assessment

- `BaseTestCase` transaction wrapping (BEGIN/ROLLBACK) works correctly but only covers 24% of test files (24 of 88).
- 17 test files extend raw `TestCase` AND write data to the database without cleanup.
- Idempotency passes on consecutive runs — but only because failing tests don't persist writes, and passing tests mostly don't write data.
- Seed database (`SeedManager::bootstrap('test')`) properly loaded with 17 members including all synthetic test members.
- Authentication traits (`AuthenticatedTrait`, `SuperUserAuthenticatedTrait`) write to DB in setUp without transaction wrapping. Safe by accident (same data each time).

#### Test Quality Assessment

- **54 markTestIncomplete tests total:** 37 are auto-generated cop-out stubs (ViewCell tests), 17 are genuine (need Imagick, service mocking, policy implementation).
- **Assertion quality mixed:** Authorization tests have strong assertions; many controller tests only check `assertResponseOk()`. Some empty test methods pass trivially with zero assertions.
- **Misplaced file:** `tests/ViewCellRegistryTest.php` is a standalone script, not a PHPUnit test.

#### Coverage Gaps

**Critical (security/data integrity):** Authorization policies (37 classes, 1 trivial test), warrant workflow (0 tests), service principals (0 tests), impersonation (0 tests).

**Major (core functionality):** 20 of 26 core controllers untested, 26 of 32 core tables untested, 29 of 34 core entities untested, 6 of 7 commands untested, all mailers untested, no API endpoint tests. Activities plugin has 7 stub-only test files. Awards plugin has 2 stubs, 0 real tests.

**Estimated real coverage: ~15-20% of application code.**

#### Recommendations

1. Fix namespace collision (unblocks plugins/all suites)
2. Fix BaseTestCase constants (KINGDOM_BRANCH_ID → 2, TEST_BRANCH_LOCAL_ID → 14)
3. Migrate 17 data-writing test files to BaseTestCase
4. Consolidate authentication traits
5. Triage 7 authorization failures
6. Delete dead stubs or implement them
7. Add PHPUnit to CI pipeline

**Key insight:** The infrastructure DESIGN (BaseTestCase + transactions + SeedManager) is solid. The problem is ADOPTION — most tests predate the infrastructure and were never migrated.

### 2026-02-10: User directive
**By:** Josh Handel (via Copilot)
**What:** Before starting any new feature work, get the test suite into a solid state. Testing infrastructure is the priority.
**Why:** User request — captured for team memory

### 2026-02-10: Test Infrastructure Attack Plan
**By:** Mal
**What:** Prioritized work plan to make the test suite reliable before new feature work
**Why:** Josh directive — testing must be solid before any new features. Jayne's deep dive exposed infrastructure-level blockers that make test results unreliable and two suites completely unrunnable.

**Guiding Principles:**
1. Runnability first, reliability second, coverage third
2. Don't touch Queue plugin's 31 tests — they have their own fixture system and work fine
3. The 17 files that write data without transaction wrapping are the #1 database-cleanup risk
4. Auth failures need investigation before fix — don't assume test vs code bug
5. Smallest changes possible — no rewrites, no framework upgrades

---

#### Phase 1: Make All Suites Runnable
**Goal:** `phpunit --testsuite all` completes without FATAL errors
**Parallel?** Yes — all three tasks are independent

| Task | Owner | Size | Deps | Details |
|------|-------|------|------|---------|
| 1.1 Delete Waivers HelloWorld copy | Jayne | S | None | Delete `app/plugins/Waivers/tests/TestCase/Controller/HelloWorldControllerTest.php`. This is an exact copy of Template's test with same namespace `Template\Test\TestCase\Controller` — causes fatal namespace collision that kills `plugins` and `all` suites. The Template plugin version stays (it's the reference implementation). |
| 1.2 Delete duplicate ImageToPdfConversionServiceTest | Jayne | S | None | Delete `app/plugins/Waivers/tests/TestCase/Services/ImageToPdfConversionServiceTest.php`. Near-duplicate of `app/tests/TestCase/Services/ImageToPdfConversionServiceTest.php`. The core version tests `convertImageToPdf()`, the Waivers copy tests `validateImage()` — but `validateImage()` is tested by the core version already. If there's Waivers-specific PDF behavior, write a dedicated test later; don't duplicate the entire class. |
| 1.3 Fix BaseTestCase constants | Jayne | S | None | In `app/tests/TestCase/BaseTestCase.php`: change `KINGDOM_BRANCH_ID = 1` → `KINGDOM_BRANCH_ID = 2` (Ansteorra is ID 2 in seed data, no branch with ID 1 exists). Change `TEST_BRANCH_LOCAL_ID = 1073` → `TEST_BRANCH_LOCAL_ID = 14` (Shire of Adlersruhe, a real local group in the seed data; max branch ID is 42). Update docblocks to match. |

**Risks:**
- Deleting HelloWorld test could mask a gap if someone actually relies on it in Waivers — but it's identical to Template's test, so no unique coverage is lost.
- Changing constants could break tests that depend on the old (wrong) values — but those tests are already wrong. Run full suite after to catch cascading failures.

**Done when:** `vendor/bin/phpunit --testsuite plugins` and `vendor/bin/phpunit --testsuite all` complete without FATAL errors. May still have failures, but no crashes.

---

#### Phase 2: Make Database Tests Reliable (State Leakage Fix)
**Goal:** Tests clean up after themselves — no test can corrupt seed data for subsequent tests
**Parallel?** Partially — 2.1 and 2.2 can be parallelized, then 2.3 depends on both

| Task | Owner | Size | Deps | Details |
|------|-------|------|------|---------|
| 2.1 Migrate high-risk write tests to BaseTestCase | Jayne | M | Phase 1 | Migrate the 17 test files that actually write data to extend `BaseTestCase` (or `HttpIntegrationTestCase`/`PluginIntegrationTestCase` for controller tests). These are the files causing state leakage. **Priority order — controllers first** (they POST data): `MembersControllerTest`, `AppSettingsControllerTest`, `GatheringsControllerTest`, `GatheringTypesControllerTest`, `GatheringActivitiesControllerTest`, `WaiverTypesControllerTest`, `GatheringWaiversControllerTest`. **Then tables**: `AppSettingsTableTest`, `BranchesTableTest`, `MembersTableTest`, `GatheringStaffTableTest`, `WaiverTypesTableTest`. **Do NOT touch Queue plugin tests** — they have their own isolation. For controller tests that currently use `AuthenticatedTrait` or `SuperUserAuthenticatedTrait`, switch to extending `HttpIntegrationTestCase` (which provides transaction wrapping via BaseTestCase) and use `TestAuthenticationHelper` for auth. |
| 2.2 Migrate read-only tests (batch) | Jayne | M | Phase 1 | Migrate remaining ~47 non-Queue test files from `TestCase` to `BaseTestCase`. Even read-only tests should use transaction wrapping for safety — if someone adds a write later, it'll be protected. This is mechanical: change `extends TestCase` to `extends BaseTestCase`, update imports, verify `setUp()`/`tearDown()` call `parent::`. Can be done in batches of 10-15 files. **Skip Queue plugin tests entirely** (31 files, own system). |
| 2.3 Verify no state leakage | Jayne | S | 2.1, 2.2 | Run `vendor/bin/phpunit --testsuite all` twice in sequence. Compare results. If the same tests pass/fail both times, state leakage is fixed. Run individual suites in different orders to confirm no cross-suite contamination. |

**Risks:**
- Some tests may have implicit dependencies on prior test writes (test A writes data, test B reads it). The transaction rollback will expose these. Fix them by adding proper test setup within each test.
- CakePHP's `IntegrationTestTrait` is known to have transaction isolation conflicts. Tests that POST data and then read it back may see different behavior with transaction wrapping. `HttpIntegrationTestCase` already handles this, but watch for new failures.
- Controller tests using `AuthenticatedTrait`/`SuperUserAuthenticatedTrait` write admin permissions to DB during setup. After migration to BaseTestCase, those writes roll back — which is correct, but verify auth still works within the transaction scope.

**Done when:** All 4 suites produce identical results on consecutive runs. No test depends on side effects from another test.

---

#### Phase 3: Consolidate Auth Test Patterns
**Goal:** One clear auth pattern for tests, documented, used everywhere
**Parallel?** No — architectural decision first, then implementation

| Task | Owner | Size | Deps | Details |
|------|-------|------|------|---------|
| 3.1 Decide auth trait strategy | Mal | S | Phase 2 | **Decision:** Standardize on `TestAuthenticationHelper` (array-based, no DB writes) for new tests. `HttpIntegrationTestCase` already uses it. **Deprecate** `AuthenticatedTrait` and `SuperUserAuthenticatedTrait` — they write admin permissions to DB which conflicts with transaction wrapping. Don't delete the old traits yet (existing tests use them), but mark them `@deprecated` and document the migration path. The entity-based traits exist because some tests need actual permission records in DB — those tests should set up their own data within the transaction. |
| 3.2 Migrate tests off deprecated traits | Jayne | M | 3.1 | Update tests currently using `AuthenticatedTrait` (MembersControllerTest, AppSettingsControllerTest) and `SuperUserAuthenticatedTrait` (6 controller tests) to use `TestAuthenticationHelper` via `HttpIntegrationTestCase`. This may require adjusting how tests set up auth context. Add `@deprecated` annotation to both old traits. |

**Risks:**
- Tests using `AuthenticatedTrait` may depend on having real permission records in the DB for authorization checks to pass. Switching to array-based auth could cause authorization failures if the controller actions check permissions through `PermissionsLoader`. **Mitigation:** Test each migration individually. If a test needs real DB permissions, keep using the old trait but document why.

**Done when:** No test uses `AuthenticatedTrait` or `SuperUserAuthenticatedTrait` (or they're documented exceptions with clear rationale). All controller tests extend `HttpIntegrationTestCase` or `PluginIntegrationTestCase`.

---

#### Phase 4: Investigate Authorization Test Failures
**Goal:** Determine whether 7 auth failures are test bugs or production code bugs, then fix whichever is wrong
**Parallel?** 4.1 is serial (investigation), then 4.2a/4.2b can parallelize

| Task | Owner | Size | Deps | Details |
|------|-------|------|------|---------|
| 4.1 Triage auth failures | Jayne + Mal | M | Phase 2 | Investigate each of the 7 authorization test failures. For each one, determine: (a) is the test expectation correct? (b) is the production code behaving correctly? **Specific failures to investigate:** (1) `AuthorizationEdgeCasesTest::testRevokedRoleNoLongerGrants...` — revoked roles still granting permissions. Check if `PermissionsLoader` filters on role status. (2) `testExpiredMembershipLosesAuth` — Eirik's membership expires 2029, test assumes current year matters. Check if test uses hardcoded dates. (3) `testNonWarrantableMemberMarked...` — warrantable flag not cleared. Check `SyncMemberWarrantableStatusesCommand`. (4) `AuthorizationServiceTest::testViewOtherMember...` — view-other-member too permissive. Check `MemberPolicy`. (5) `BranchScopedAuthorizationTest::testDevonMultiRegional...` — regional scoping wrong. Check `_getBranchIdsForPolicy()`. (6-7) `PermissionsLoaderTest` — branch scopes not loaded. Check cache logic. Jayne investigates and classifies each as TEST_BUG or CODE_BUG. Mal reviews the classifications. |
| 4.2a Fix test bugs | Jayne | S-M | 4.1 | For failures classified as TEST_BUG: fix test expectations, hardcoded dates, or incorrect assumptions. |
| 4.2b Fix code bugs | Kaylee | S-M | 4.1 | For failures classified as CODE_BUG: fix production code. These are likely in `PermissionsLoader`, `BasePolicy._getBranchIdsForPolicy()`, or entity status checks. **These are security-relevant** — authorization bugs mean incorrect access control. Kaylee fixes with test verification. Each fix needs Mal review before merge. |

**Risks:**
- Auth failures could cascade — fixing one bug in `PermissionsLoader` might change behavior for many tests. Run full suite after each fix.
- Some failures might be both: test has wrong expectation AND code has a bug. Investigate without assumptions.
- Production code fixes in authorization are high-risk changes. Apply the "Dangerous to Change" guidance from decisions.md — changes to `PermissionsLoader` and the permission chain require extra scrutiny.

**Done when:** All 7 authorization tests pass. Each failure has a documented root cause (test bug or code bug). Any code fixes have been reviewed by Mal.

---

#### Phase 5: Remove Dead Weight
**Goal:** Delete tests that provide no value — reduce noise, make test counts meaningful
**Parallel?** Yes — all tasks are independent

| Task | Owner | Size | Deps | Details |
|------|-------|------|------|---------|
| 5.1 Delete or implement ViewCell stubs | Jayne | M | Phase 2 | 12 ViewCell test stubs across Activities (7), Officers (3), Awards (2) are all `markTestIncomplete("Not implemented yet")`. **Decision:** Delete them. They were auto-generated by `bake` and never implemented. They inflate test counts (14 "incomplete" results) while testing nothing. If we need ViewCell tests later, we'll write them from scratch with actual assertions. Generate a tracking issue or note for future coverage. |
| 5.2 Move ViewCellRegistryTest.php | Jayne | S | None | `app/tests/ViewCellRegistryTest.php` is a standalone PHP script (not a PHPUnit test) that checks if `ViewCellRegistry` class can be autoloaded. Move it to `app/tests/scripts/` or delete it entirely. It's not integrated into any test suite and provides no CI value. **Decision:** Delete it — the autoloader either works or it doesn't; a manual script adds nothing. |
| 5.3 Fix bootstrap warnings | Jayne | S | None | Address bootstrap warnings: (1) `session_id` warning — the bootstrap already sets `session_id('cli')` but may fire after session auto-start. Ensure `session_id()` is called before any session operations. (2) File permissions — ensure `app/tmp/` and `app/logs/` are writable in test context (add to bootstrap or Docker setup). (3) `apcu` module warning — add `apc.enable_cli=1` to test php.ini or suppress the warning in bootstrap. These are noise that obscure real failures. |

**Risks:**
- Deleting ViewCell stubs loses the "reminder" that these cells are untested. Mitigate by documenting the gap in the test audit (already in Jayne's assessment in decisions.md).
- Bootstrap warning fixes might affect Docker container configuration — coordinate with deployment setup.

**Done when:** `vendor/bin/phpunit --testsuite all` output has zero warnings, zero incomplete tests (except genuine WIP), and every reported test is either passing or failing for a real reason.

---

#### Phase 6: Establish CI Pipeline
**Goal:** Tests run automatically on every PR — no more manual test runs
**Parallel?** No — sequential build

| Task | Owner | Size | Deps | Details |
|------|-------|------|------|---------|
| 6.1 Create GitHub Actions test workflow | Jayne | M | Phases 1-5 | Create `.github/workflows/test.yml` that: (1) Sets up PHP 8.2+ with required extensions (apcu, pdo_mysql, etc.), (2) Starts MariaDB service container, (3) Loads `dev_seed_clean.sql` into test DB, (4) Runs `composer install`, (5) Runs `vendor/bin/phpunit --testsuite all`. Should run on push to main and on all PRs. Use the existing `docker/Dockerfile.app` as reference for PHP extensions needed. |
| 6.2 Validate CI in a test PR | Jayne | S | 6.1 | Create a test PR that adds a trivial passing test. Verify CI runs, passes, and reports results. Fix any environment differences between Docker dev and CI. |

**Risks:**
- CI environment may differ from Docker dev environment (different MariaDB version, missing PHP extensions, file paths). Budget time for debugging.
- Seed data load time could make CI slow. Consider caching the seeded database state if test runtime exceeds 5 minutes.

**Done when:** CI runs on every PR, tests pass, and a failing test blocks merge.

---

### Execution Timeline

```
Phase 1 (Day 1)     ████  Make suites runnable
Phase 2 (Days 2-3)  ████████  Fix state leakage (biggest effort)
Phase 3 (Days 3-4)  ██████  Consolidate auth patterns
Phase 4 (Days 4-6)  ████████████  Investigate + fix auth failures
Phase 5 (Day 6-7)   ████  Remove dead weight
Phase 6 (Day 7-8)   ██████  CI pipeline
```

**Total estimate:** 6-8 working days across team members

### Resource Allocation
- **Jayne** (primary): Phases 1-3, 4.1, 4.2a, 5, 6 — this is test infrastructure work
- **Kaylee** (secondary): Phase 4.2b only — production code bug fixes
- **Mal** (review): Phase 3.1 decision, Phase 4.1 triage review, Phase 4.2b code review

### Open Questions
1. Should we add test coverage reporting to CI (Phase 6)? I say not yet — get green first, measure later.
2. The 31 Queue plugin tests with their own fixture system — do we eventually want to migrate them to BaseTestCase? I say no unless they start causing problems. Different plugin, different isolation strategy, and it works.
3. Do we want to add policy tests (37 untested policy classes) as Phase 7? Yes, but not in this plan. This plan is about infrastructure; coverage expansion is separate work.

### 2026-02-10: Auth Test Failure Triage Results
**By:** Jayne
**What:** Triaged 17 auth-related test failures — classified each as TEST_BUG or CODE_BUG with root cause analysis
**Why:** Phase 4.1 of the test infrastructure attack plan required investigation before fixes

**Summary:** 15 TEST_BUG, 2 CODE_BUG, 0 OTHER

**Root Cause Taxonomy:**

| Category | Count | Description |
|----------|-------|-------------|
| Infrastructure: Wrong DB connection | 10 | Tests query `KMP_DEV` instead of `KMP_DEV_test` |
| Stale seed data assumptions | 3 | Test hardcodes dates/flags that don't match seed data |
| Test code errors | 2 | Tests call non-existent policy methods or pass wrong types |
| Deprecated test trait | 2 | `SuperUserAuthenticatedTrait` incompatible with auth system |
| Missing revoker_id filter | 1 | `validPermissionClauses` security gap (CODE_BUG) |
| API inconsistency | 1 | `AuthorizationService::checkCan` doesn't convert strings (CODE_BUG) |

**Critical Finding — Database Connection:** All auth tests queried the PRODUCTION database (`KMP_DEV`) instead of the test database (`KMP_DEV_test`). Fix: `ConnectionManager::alias('test', 'default')` in `tests/bootstrap.php`.

**CODE_BUG #1 — PermissionsLoader revoker_id:** `validPermissionClauses` did not filter `MemberRoles.revoker_id IS NULL`. Revoked roles with future expiration could grant permissions. Fix: Added the filter clause. Security-relevant.

**CODE_BUG #2 — AuthorizationService string resources:** `checkCan()` passed raw strings to `performCheck()` which requires objects. Fix: Added string-to-entity conversion consistent with `Member::checkCan()`.

**Priority Recommendations:**
1. P0 — Fix test DB connection aliasing (resolves 10 of 17)
2. P1 — Update seed membership dates to far-future (prevents time-dependent failures)
3. P1 — Add revoker_id filter (defense-in-depth) ✅ DONE
4. P2 — Fix SecurityDebugTest (use real policy actions/objects)
5. P2 — Migrate ExampleSuperUserTest off deprecated trait
6. P2 — Fix stale test assertions
7. P3 — Renew production admin membership

### 2026-02-10: Auth Strategy Decision
**By:** Mal
**What:** Standardize on `TestAuthenticationHelper` for all test authentication; deprecate `AuthenticatedTrait` and `SuperUserAuthenticatedTrait`
**Why:** Array-based identity (no DB writes) works with transaction rollback and is already integrated into `HttpIntegrationTestCase`

**Migration path:** Tests using deprecated traits should switch to extending `HttpIntegrationTestCase` and call `$this->authenticateAsSuperUser()`.

**Files using deprecated traits (must migrate):**
- `AuthenticatedTrait`: AppSettingsControllerTest, ExampleSuperUserTest, BranchesControllerTest, GatheringActivitiesControllerTest, GatheringTypesControllerTest, GatheringsControllerTest, MembersControllerTest, GatheringWaiversControllerTest, WaiverTypesControllerTest, OfficesControllerTest
- `SuperUserAuthenticatedTrait`: ExampleSuperUserTest, BranchesControllerTest, GatheringActivitiesControllerTest, GatheringTypesControllerTest, GatheringsControllerTest, MembersControllerTest, GatheringWaiversControllerTest, WaiverTypesControllerTest

**⚠️ Gap: `authenticateAsSuperUser()` does NOT set permissions.** The current helper sets a plain array in the session without a `permissions` key. The authorization layer requires `KmpIdentityInterface::getPermissions()` to return Permission entities with `is_super_user = true`. Tests requiring authorization checks will fail when migrated unless this gap is addressed.

**Recommended fix options:**
1. Include permission data in the session array
2. Store a mock/stub `Member` entity implementing `KmpIdentityInterface`
3. Load the real member entity from DB within the test transaction (recommended — rolls back cleanly while providing proper identity)

### 2026-02-10: User directive — Own the Queue plugin
**By:** Josh Handel (via Copilot)
**What:** The Queue plugin is 3rd party code but since KMP hosts the source, the team should "own it" — review it, identify issues, and plan tweaks/updates to bring it into KMP's conventions and fix any oversights.
**Why:** User request — the Queue plugin has been treated as untouchable 3rd party code, but since the source is in-repo, it should be maintained to KMP standards.

### 2026-02-10: Queue plugin ownership review (consolidated)
**By:** Mal, Kaylee, Jayne
**What:** Full review of Queue plugin (forked from dereuromark/cakephp-queue). Architecture review, deep code review (22 issues), and test triage (81/119 failures) completed.
**Why:** Josh directed team to own the plugin. Three-pronged review to assess architecture, code quality, and test health.

**Architecture (Mal):** Own it, slim it down. Plugin is infrastructure-critical — all email flows through it (8 callsites, all via QueuedMailerAwareTrait). Already heavily diverged from upstream (BaseEntity/BaseTable, KMPPluginInterface, authorization). 47 source files, 7,628 lines. Recommend removing ExecuteTask (arbitrary shell execution), example tasks (8 demo files), unused transport classes, and stale vendor directory.

**Security (Kaylee — P0):**
- Command injection in `terminateProcess()` — unsanitized PID passed to `exec('kill')` (QueueProcessesTable.php:319)
- Open redirect in `refererRedirect()` — incomplete URL validation (QueueController.php:232)
- ExecuteTask runs arbitrary shell commands via `exec()` — must be disabled/removed

**Code Quality (Kaylee — P1/P2):** 10 P1 issues: broken `getFailedStatus()` task name lookup, deprecated `loadComponent()`/`TableRegistry`, timestamp-vs-DateTime comparison in `cleanOldJobs()`, missing `workerkey` index, wrong authorization context in QueueProcessesController, silent `markJobDone()`/`markJobFailed()` failures, missing Shim dependency, configVersion never written back, SQL string interpolation in `requestJob()`. 10 P2 cleanup items (coding style, entity overrides, dead code).

**Tests (Jayne):** 119 total, 81 failures, 38 pass. Zero code bugs found — all failures are infrastructure damage from dropping Queue into KMP without adapting test harness. 5 root causes:
1. "Plugin already loaded" — 16 errors (loadPlugins in setUp, trivial fix)
2. Missing Admin prefix routes — 29 errors (test URLs reference removed Admin namespace, medium fix)
3. TestApp/Foo autoload not registered — 15 errors (KMP composer.json missing dev paths, small fix)
4. Fixtures removed without replacement — 16 failures (commit 6e25eea4 bulk-deleted fixtures, medium fix)
5. Email transport config mismatch — 3 failures (KMP uses Smtp, tests expect Debug, small fix)
Silver bullets: fixing #1 + #2 resolves 45 of 68 errors (66%).

**Correction to Attack Plan:** The Test Infrastructure Attack Plan stated "Don't touch Queue plugin's 31 tests — they have their own fixture system and work fine." Queue tests do NOT work fine — 81/119 fail. However, the directive to not migrate them to BaseTestCase remains valid; they should keep their fixture-based isolation strategy.

**Decisions:**
- Own the plugin permanently; do not re-sync with upstream
- Remove ExecuteTask and example tasks from production immediately
- Fix P0 security issues (command injection, open redirect) before other work
- Fix Queue test infrastructure in phases: silver bullets → autoloading → fixtures → config
- Do NOT migrate Queue tests to BaseTestCase pattern
- Periodically review upstream releases for critical fixes only

### 2026-02-10: Documentation accuracy review (consolidated)
**By:** Jayne, Kaylee, Mal, Wash
**What:** Full team review of 96 docs against codebase — 4 reviewers covering backend, plugins, frontend, and testing/misc
**Why:** Ensuring published documentation matches current application state

**Summary:** ~30 of 96 docs have substantive inaccuracies. Worst offenders: 10.4-asset-management.md (10 issues), 7/8-development-workflow.md (duplicates with wrong test patterns), 5.7-waivers-plugin.md (severely outdated), 7.3-testing-infrastructure.md (wrong constants).

---

#### Testing/setup/misc documentation accuracy review
**By:** Jayne
**What:** Reviewed 20 testing, setup, deployment, and misc docs against codebase
**Why:** Ensuring published documentation matches current application state

---

### 1-introduction.md

- **Issue:** Section 1.3 says "PHP: Version 8.1 or higher" → **Actual:** The CI workflow (`.github/workflows/tests.yml`) uses PHP 8.3, and `docker-compose.yml` comment says "PHP 8.3 + Apache". The doc's minimum is likely outdated — should say 8.2+ or 8.3 to match what's actually tested/deployed.
- **Issue:** Node.js 14+ recommended → **Actual:** Node.js 14 is EOL. The project uses Laravel Mix and modern Stimulus.JS; the actual devcontainer/Docker likely runs a much newer Node. This recommendation is stale.
- **Issue:** "PHPStan: For static analysis" listed in dev requirements → **Actual:** `composer stan` exists in `composer.json` scripts, so PHPStan is indeed available. ✅ Correct.
- **Issue:** "PHP_CodeSniffer: For code style checking" → **Actual:** `composer cs-check` maps to `phpcs --colors -p`. ✅ Correct.

### 2-configuration.md

- **Issue:** Section 2.3 says `bin/cake security generate_salt` → **Actual:** No `generate_salt` command found anywhere in `app/src/` via grep. This command does not exist in the KMP codebase. It may be a standard CakePHP command, but it's not verified to be present.
- **Issue:** The `App` config example shows `"title" => "AMS"` → **Actual:** Confirmed in `app/config/app.php` line 39. ✅ Correct.
- **Issue:** The `App` config table says `version` is "loaded at runtime" from `config/version.txt` → **Actual:** Confirmed at `app/config/app.php` line 92: `"version" => file_get_contents(CONFIG . "version.txt")`. ✅ Correct.

### 2-getting-started.md

- **Issue:** Section 2.3 mentions `./update_seed_data.sh` as a container-specific tool → **Actual:** No file `update_seed_data.sh` exists in the repo. The script does not exist.
- **Issue:** `./reset_dev_database.sh` described as dropping/recreating DB, applying migrations, loading seed data → **Actual:** The root `reset_dev_database.sh` does exist and does reset + seed + migrate, but its behavior doesn't match the doc's description exactly. The doc says "Drop and recreate the development database / Apply all migrations / Load seed data for testing" as if all happen unconditionally. The actual script has two modes: without `--seed` (runs `resetDatabase` command + migrations) and with `--seed` (drops DB + loads `dev_seed_clean.sql` + migrations). The Docker version (`dev-reset-db.sh`) has the `--seed` flag too. The doc oversimplifies.
- **Issue:** "The Dev Container will automatically... Initialize and seed the development database / Run database migrations" → **Actual:** This describes the devcontainer setup, which is plausible but unverified without inspecting `.devcontainer/`. Claims are reasonable.

### 3.5-er-diagrams.md

- **Issue:** "Session Storage: Database-backed session management" listed under Database Technologies → **Actual:** `app/config/app.php` line 422 shows `"defaults" => "php"` (PHP file-based sessions), NOT database-backed. The doc is wrong about the default session storage.
- Otherwise, the ER diagrams are mermaid-based and the schema structure claims align with the known table hierarchy (members, branches, member_roles, permissions, roles, etc.). The doc is a large reference document (1267 lines) and the core schema claims appear accurate.

### 7-development-workflow.md

- **Issue:** Section 7.2 test structure shows `Integration/` directory → **Actual:** No `app/tests/TestCase/Integration/` directory exists. The actual test directories are `Controller/`, `Model/`, `Services/`, `View/`, `Core/`, `Plugins/`, `Queue/`, `KMP/`, `Command/`, `Middleware/`, `Support/`.
- **Issue:** Test suite names shown as `--testsuite unit` and `--testsuite integration` → **Actual:** `phpunit.xml.dist` defines suites named `core-unit`, `core-feature`, `plugins`, and `all`. There is NO suite named `unit` or `integration`. The doc uses wrong suite names.
- **Issue:** Test writing example shows `class MembersControllerTest extends TestCase` with `use IntegrationTestTrait` → **Actual:** KMP tests should extend `BaseTestCase` (for transaction isolation), not bare `Cake\TestSuite\TestCase`. The doc teaches the wrong base class pattern.
- **Issue:** Test writing example shows `protected $fixtures = ['app.Members', ...]` → **Actual:** KMP does NOT use CakePHP fixtures for most tests. It uses `dev_seed_clean.sql` via `SeedManager`. This fixture pattern is misleading — only the Queue plugin still uses fixtures.
- **Issue:** Fixture data section describes creating `MembersFixture` with `$fields` and `$records` → **Actual:** This is the wrong pattern for KMP. The `app/tests/Fixture/` directory contains only `TEST_SUPER_USER_README.md` — no actual fixture PHP files. The doc is completely wrong about the test data strategy.
- **Issue:** `StaticHelpers::logVar($variable, 'Label')` listed as a debug function → **Actual:** No `logVar` method exists on `StaticHelpers` (grep returned no matches). This method doesn't exist.
- **Issue:** Section 7.3 mentions `vendor/bin/psalm` → **Actual:** Psalm is not in the composer.json scripts or dependencies. Only PHPStan is configured (`composer stan`).
- **Issue:** `npm run lint` and `npm run lint:fix` mentioned → **Actual:** These scripts are not present in `app/package.json`. The npm scripts are `dev`, `development`, `watch`, `prod`, `production`, `docs:js`, `test`, `test:js`, etc. No `lint` script exists.

### 7.1-security-best-practices.md

- **Issue:** CSRF middleware shown at "Lines 406-414" of `Application.php` → **Actual:** The CSRF middleware is at lines 423-427 of `Application.php`. Line numbers are wrong.
- **Issue:** Session config shown in `app_local.php` at "Lines 36-49" → **Actual:** `app_local.php` has NO Session configuration at all (only 123 lines, contains debug, DebugKit, Security, Datasources, EmailTransport, Documents sections). The session settings with debug-conditional logic described in the doc do not exist in `app_local.php`. The actual session config is in `app/config/app.php` lines 420-444, and it's NOT conditional on DEBUG — it hardcodes `session.cookie_secure => true` and `session.cookie_samesite => "Strict"`.
- **Issue:** Doc says Session timeout is 30 minutes with `cookieTimeout => 240` (4 hours) and `gc_maxlifetime => 14400` → **Actual:** `app/config/app.php` has `"timeout" => 30` (30 minutes). There is NO `cookieTimeout` or `gc_maxlifetime` setting anywhere in the config. The doc contradicts itself — first says 30 minutes, then in a later section says 4 hours (240 minutes). The actual value is 30 minutes.
- **Issue:** "Session Configuration" section shows `'timeout' => 240` (4 hours) → **Actual:** The config shows `'timeout' => 30` (30 minutes). The 4-hour claim appears in the doc's session security section and contradicts both the actual config AND the doc's own Session Security Features table which says "30 minutes".
- **Issue:** `password_hash` with `'salt' => $securitySalt` shown → **Actual:** PHP's `password_hash` with `PASSWORD_BCRYPT` ignores user-supplied salt since PHP 7.0 (deprecated). This code example is misleading/wrong for modern PHP.
- **Issue:** Doc says CSP is at "Lines 340-390" of `Application.php` → **Actual:** The CSP construction is elsewhere in the file; line numbers are approximate and may be off. Not a critical issue but worth noting.

### 7.3-testing-infrastructure.md

- **Issue:** Bootstrap code shown as `(new SchemaLoader())->loadSqlFiles('../dev_seed_clean.sql', 'test')` → **Actual:** `tests/bootstrap.php` uses `SeedManager::bootstrap('test')` which internally calls `$loader->loadSqlFiles([$seedPath], $connection)` (array of paths, not a single string). The doc shows simplified/wrong bootstrap code.
- **Issue:** Test Data Reference table shows `Branch ID 1 = Kingdom of Ansteorra` → **Actual:** `BaseTestCase` constant `KINGDOM_BRANCH_ID = 2` (not 1). The doc says Branch 1, the code says Branch 2. This is wrong.
- **Issue:** Test Data Reference table shows `Role ID 1 = Admin` → **Actual:** `BaseTestCase::ADMIN_ROLE_ID = 1`. ✅ Correct.
- **Issue:** Test Data Reference table shows `Permission ID 1 = Is Super User` → **Actual:** `BaseTestCase::SUPER_USER_PERMISSION_ID = 1`. ✅ Correct.
- **Issue:** `TEST_BRANCH_LOCAL_ID` shown as `1073` → **Actual:** `BaseTestCase::TEST_BRANCH_LOCAL_ID = 14`. The doc says 1073, the code says 14. This is wrong.
- **Issue:** Doc says `SuperUserAuthenticatedTrait` is the recommended auth approach → **Actual:** `SuperUserAuthenticatedTrait` is marked `@deprecated` in its own source code. The doc should recommend `TestAuthenticationHelper` (via `HttpIntegrationTestCase`) instead.
- **Issue:** `TestAuthenticationHelper` methods listed include `authenticateAsAdmin()`, `authenticateAsMember($memberId)`, `logout()`, `assertAuthenticated()`, `assertNotAuthenticated()`, `assertAuthenticatedAs($memberId)` → **Actual:** All confirmed present in the trait source. ✅ Correct.
- **Issue:** Test suite names shown as `--testsuite app`, `--testsuite waivers`, `--testsuite queue` → **Actual:** `phpunit.xml.dist` defines `core-unit`, `core-feature`, `plugins`, `all`. There is NO suite named `app`, `waivers`, or `queue`. All wrong suite names.
- **Issue:** Test organization tree shows `Util/TestDatabaseTrait.php` → **Actual:** The file `TestDatabaseTrait.php` exists at `tests/TestCase/TestDatabaseTrait.php`, NOT in a `Util/` directory. Additionally, there is no `Util/` directory under `tests/`.
- **Issue:** Test organization tree shows `js/KMP_utils.test.js` and `js/example_controller.test.js` → **Actual:** Files exist as `tests/js/utils/kmp-utils.test.js` and `tests/js/controllers/example-controller.test.js`. The doc paths are wrong (missing `utils/` and `controllers/` subdirectories).
- **Issue:** Test organization tree shows `ui/bdd/` and `ui/gen/` → **Actual:** Confirmed present. ✅ Correct.
- **Issue:** Test organization tree shows `ui/global-setup.js` but no `ui/global-teardown.js` → **Actual:** Both `global-setup.js` and `global-teardown.js` exist. Minor omission.

### 7.4-security-debug-information.md

- **Issue:** Component file paths listed → **Actual:** All verified present:
  - `src/Services/AuthorizationService.php` ✅
  - `src/View/Helper/SecurityDebugHelper.php` ✅
  - `assets/js/controllers/security-debug-controller.js` ✅
  - `templates/element/copyrightFooter.php` ✅ (has security-debug controller)
  - `assets/js/index.js` ✅ (imports security-debug-controller)
  - `src/View/AppView.php` ✅ (registers SecurityDebug helper)
- Doc is accurate. ✅

### 7.6-testing-suite.md

- **Issue:** Test architecture table shows `Core Unit` at `tests/TestCase/Core/Unit` → **Actual:** Confirmed, both `Core/Unit` and `Core/Feature` directories exist with test files. ✅ Correct.
- **Issue:** Suite names `core-unit`, `core-feature`, `plugins`, `all` → **Actual:** Match `phpunit.xml.dist` exactly. ✅ Correct.
- **Issue:** "Legacy Tests" section says existing suites under `Controller`, `Model`, `Services` are "included in `core-unit`, `core-feature`, or `all`" → **Actual:** Confirmed — `phpunit.xml.dist` includes `tests/TestCase/Model` and `tests/TestCase/Services` in `core-unit`, and `tests/TestCase/Controller`, `tests/TestCase/Command`, `tests/TestCase/Middleware`, `tests/TestCase/View` in `core-feature`. ✅ Correct.
- **Issue:** `SeedManager` described as loading `../dev_seed_clean.sql` → **Actual:** `SeedManager::SEED_FILENAME = 'dev_seed_clean.sql'` with `dirname(__DIR__, 4)` to resolve path (resolves to repo root). ✅ Correct.
- **Issue:** Starter test `Plugins/Awards/Feature/RecommendationsSeedTest` mentioned → **Actual:** Confirmed at `tests/TestCase/Plugins/Awards/Feature/RecommendationsSeedTest.php`. ✅ Correct.
- This doc is the most accurate of all the testing docs. ✅

### 8-deployment.md

- **Issue:** Directory structure shows `/etc/php/8.0/` → **Actual:** Project uses PHP 8.3 (per CI and Docker). Should be 8.3.
- **Issue:** Nginx config shows `fastcgi_pass unix:/var/run/php/php8.0-fpm.sock` → **Actual:** Should reference `php8.3-fpm.sock` to match actual PHP version.
- **Issue:** `bin/cake security generate_salt` mentioned → **Actual:** As noted above, this command may not exist in the KMP codebase (no grep match). CakePHP may provide it as a built-in, but it's unverified.
- **Issue:** Azure Blob Storage section is comprehensive and config matches `app_local.php`. ✅ Correct.

### 8-development-workflow.md

- **Issue:** This file is nearly identical to `7-development-workflow.md` — same section numbering (8.1 = 7.1, 8.2 = 7.2, etc.), same content, same wrong claims. It's a DUPLICATE document.
- **Issue:** Same wrong test suite names (`unit`, `integration` instead of `core-unit`, `core-feature`).
- **Issue:** Same wrong test patterns (bare `TestCase` instead of `BaseTestCase`, CakePHP fixtures instead of seed SQL).
- **Issue:** Same wrong debug tool (`StaticHelpers::logVar` doesn't exist, Psalm not configured).
- **Issue:** Same wrong npm scripts (`npm run lint`).
- **Recommendation:** This is a duplicate file that should either be removed or merged/redirected to `7-development-workflow.md`.

### 8.1-environment-setup.md

- **Issue:** Configuration hierarchy listed as: 1. `.env`, 2. `config/app.php`, 3. `config/app_local.php`, 4. Runtime → **Actual:** This ordering is backward from what `2-configuration.md` says (which lists `app.php` first, then `app_local.php`, then env vars). The CakePHP convention is `app.php` → `app_local.php` → env overrides. The 8.1 doc incorrectly puts `.env` first.
- **Issue:** `bin/cake security generate_salt` mentioned again → **Actual:** Unverified command (same issue as above).
- **Issue:** Document storage variables `DOCUMENTS_STORAGE_ADAPTER` mentioned → **Actual:** The actual config in `app_local.php` uses `'adapter' => 'local'` as a hardcoded config key, not an env var. There's no `env('DOCUMENTS_STORAGE_ADAPTER')` call found.

### docker-development.md

- **Issue:** Shows `docker compose exec app bin/cake migrations status` → **Actual:** The working directory in the container is `/var/www/html` (the app directory), so CakePHP commands should work. ✅ Reasonable.
- **Issue:** Docker Compose services (db, mailpit, app) match `docker-compose.yml` exactly. Ports (8080, 8025, 1025, 3306) match. ✅ Correct.
- **Issue:** Database credentials (KMPSQLDEV / P@ssw0rd) match defaults in `docker-compose.yml`. ✅ Correct.
- **Issue:** File structure shows `docker/.env.example` → **Actual:** Confirmed present. ✅ Correct.
- **Issue:** `dev-up.sh`, `dev-down.sh`, `dev-reset-db.sh` behavior descriptions match actual scripts. ✅ Correct.
- **Issue:** `dev-down.sh --volumes` behavior matches actual script. ✅ Correct.
- **Issue:** `dev-reset-db.sh --seed` described → **Actual:** Matches actual script behavior. ✅ Correct.
- This doc is accurate. ✅

### 11-extending-kmp.md

- Spot-checked the first 100 lines. Template plugin reference, plugin structure, and registration pattern descriptions are reasonable.
- The doc is 48KB — too large for full line-by-line verification. The structural claims (plugin directory layout, Plugin.php class, config/plugins.php registration) align with known architecture.

### appendices.md

- **Issue:** "Font Awesome Icons" listed under Tools and Libraries → **Actual:** KMP uses Bootstrap Icons (version 1.13.1, confirmed in `DOCUMENTATION_MIGRATION_SUMMARY.md` and `app/config/app.php` which imports `BootstrapIcon`). Font Awesome is NOT the primary icon library. Grep shows Font Awesome referenced in only a couple of specific controllers (markdown-editor, guifier) but the main icon system is Bootstrap Icons. The doc is misleading.
- **Issue:** "Bootstrap 5 Documentation" links to `https://getbootstrap.com/docs/5.0/` → **Actual:** The project uses Bootstrap 5.3.6 (per decisions.md). The link should be `docs/5.3/` not `docs/5.0/`.
- Otherwise, troubleshooting and glossary content is generic and reasonable. ✅

### index.md

- **Issue:** System Requirements listed as "PHP 8.0+" → **Actual:** The project uses PHP 8.3 (CI and Docker). Minimum should be at least 8.1 per `1-introduction.md`, and 8.0 is EOL.
- **Issue:** Section numbering has a conflict: "8.2 Development Workflow (Alternative)" links to `8-development-workflow.md` which is a duplicate of `7-development-workflow.md`. This is confusing.
- **Issue:** "Documentation Status" claims "Each section has been fact-checked against the actual source code" and "Verified Recent Updates" → **Actual:** Multiple documentation errors found in this review contradict this claim. Several docs have wrong file paths, wrong test suite names, wrong constants, and stale patterns.

### ACTIVITY_GROUPS_CONTROLLER_MIGRATION.md

- This is a migration summary/changelog document, not a reference doc.
- **Issue:** File link `[docs/5.6.3-activity-groups-controller-reference.md](docs/5.6.3-activity-groups-controller-reference.md)` uses a relative path that won't work from the `docs/` directory itself (should be just the filename or `./5.6.3-...`).
- The content describes actual code changes and is a historical record. Claims about the controller code are plausible.

### DOCUMENTATION_MIGRATION_SUMMARY.md

- This is a changelog document describing the December 2025 doc migration.
- **Issue:** "Session timeout confirmed: 30 minutes with secure settings" → **Actual:** Confirmed at 30 minutes. ✅ Correct. But note the 7.1 doc contradicts this with 4-hour claims in some sections.
- **Issue:** Claims `app.php` was reduced from ~1000 to ~550 lines → **Actual:** Not verified but plausible.
- Content is reasonable as a historical record.

### office-reporting-structure.md

- This is a domain-specific organizational chart document with mermaid diagrams.
- Content is domain knowledge (SCA office hierarchy), not code-verifiable.
- Format and structure are clean. ✅

### system-views-refactor-task-list.md

- This is a project tracking/task list document.
- File references (GridColumns classes, controllers) are relative links that resolve correctly from `docs/`.
- Claims about `getSystemViews()` pattern match the described architecture.
- This is a living task tracker, not a reference doc. ✅

---

### Summary of Critical Issues

#### HIGH PRIORITY (Wrong information that will mislead developers):

1. **7-development-workflow.md & 8-development-workflow.md**: Wrong test suite names (`unit`/`integration` vs actual `core-unit`/`core-feature`), wrong base class pattern (bare `TestCase` vs `BaseTestCase`), wrong test data strategy (CakePHP fixtures vs seed SQL), non-existent methods (`StaticHelpers::logVar`), non-existent npm scripts (`npm run lint`).

2. **7.3-testing-infrastructure.md**: Wrong `KINGDOM_BRANCH_ID` (says 1, actual is 2), wrong `TEST_BRANCH_LOCAL_ID` (says 1073, actual is 14), wrong test suite names (`app`/`waivers`/`queue`), recommends deprecated `SuperUserAuthenticatedTrait`.

3. **7.1-security-best-practices.md**: Session config in `app_local.php` doesn't exist (no conditional debug/production session switching). Session timeout contradicts itself (30 min vs 4 hours). Wrong line number references.

4. **8-development-workflow.md**: Complete duplicate of 7-development-workflow.md — should be removed or merged.

#### MEDIUM PRIORITY:

5. **appendices.md**: Font Awesome listed as icon library but KMP uses Bootstrap Icons.
6. **index.md**: PHP 8.0+ is stale (should be 8.1+).
7. **8-deployment.md**: PHP 8.0 references should be 8.3.
8. **2-getting-started.md**: Non-existent `update_seed_data.sh` script referenced.
9. **3.5-er-diagrams.md**: Claims database-backed sessions, but config uses PHP file sessions.
10. **8.1-environment-setup.md**: Configuration hierarchy order is backward from 2-configuration.md.

#### LOW PRIORITY:

11. **2-configuration.md**: `bin/cake security generate_salt` may not exist in KMP.
12. **1-introduction.md**: Node.js 14+ recommendation is stale.
13. **7.3-testing-infrastructure.md**: Test organization tree has wrong file paths for JS tests and misplaces TestDatabaseTrait.


#### Backend documentation accuracy review
**By:** Kaylee
**What:** Reviewed 25 architecture, core module, and services docs against codebase
**Why:** Ensuring published documentation matches current application state

---

### 3-architecture.md

- **Issue:** Directory structure lists only 6 plugins (Activities, Awards, Bootstrap, GitHubIssueSubmitter, Officers, Queue) → **Actual:** There are 8 plugins. Missing `Waivers/` (active, migrationOrder 4) and `Template/` (commented out in plugins.php but directory exists).
- **Issue:** Entity hierarchy shows `WarrantRoster` under `ActiveWindowBaseEntity` → **Actual:** `WarrantRoster extends BaseEntity`, not `ActiveWindowBaseEntity`. `Warrant` extends `ActiveWindowBaseEntity` and should be listed instead.
- **Issue:** Entity hierarchy lists `ActivityAuthorization` → **Actual:** The entity class is `Authorization` (`Activities\Model\Entity\Authorization`), not `ActivityAuthorization`.
- **Issue:** Entity hierarchy under `ActiveWindowBaseEntity` is missing `Warrant`, `ServicePrincipalRole`, and `Officer` (Officers plugin) → **Actual:** All three extend `ActiveWindowBaseEntity`.
- **Issue:** Section 3.5 documents only 3 behaviors (ActiveWindow, JsonField, Sortable) → **Actual:** There are 4 behaviors. `PublicIdBehavior` (anti-enumeration, generates Base62 public IDs) is missing from the behaviors section.
- **Issue:** `findUpcoming()` generated SQL shown as `WHERE start_on > :effectiveDate OR (expires_on > :effectiveDate OR expires_on IS NULL)` → **Actual:** The CakePHP array-based where clause produces `AND` between the main conditions, not `OR`: `WHERE start_on > :effectiveDate AND (expires_on > :effectiveDate OR expires_on IS NULL)`.

### 3.1-core-foundation-architecture.md

- **Issue:** Service container code example shows `$container->add(NavigationRegistry::class)->addArgument($container)` and `$container->add(KmpAuthorizationService::class)->addArgument(Identity::class)` → **Actual:** Neither `NavigationRegistry` nor `KmpAuthorizationService` are registered in `Application::services()`. The actual method only registers `ActiveWindowManagerInterface`, `WarrantManagerInterface`, `CsvExportService`, `ICalendarService`, and `ImpersonationService`. `NavigationRegistry` is used as a static class. `KmpAuthorizationService` is instantiated directly in `getAuthorizationService()`.
- **Issue:** Session configuration example shows `'timeout' => 240`, `'cookie' => ['name' => 'KMP_SESSION', 'httponly' => true, 'secure' => true, 'samesite' => 'Strict']`, and `'regenerateId' => true` → **Actual:** Real config in `app/config/app.php` is `'timeout' => 30` (not 240), `'cookie' => 'PHPSESSID'` (not KMP_SESSION), security settings are in `'ini'` sub-array (`session.cookie_secure`, `session.cookie_httponly`, `session.cookie_samesite`), and there is no `regenerateId` key; instead `session.use_strict_mode` is set.
- **Issue:** PermissionsLoader code example uses `$permission->scope` with lowercase values `'global'`, `'branch'`, `'branch_and_descendants'` → **Actual:** The property is `$permission->scoping_rule` and uses `Permission::SCOPE_GLOBAL` (`'Global'`), `Permission::SCOPE_BRANCH_ONLY` (`'Branch Only'`), `Permission::SCOPE_BRANCH_AND_CHILDREN` (`'Branch and Children'`).
- **Issue:** `addBranchScopeQuery` method signature shown as `public function addBranchScopeQuery(Query $query, array $options = []): Query` → **Actual:** Signature is `public function addBranchScopeQuery($query, $branchIDs): SelectQuery`. Parameter is `$branchIDs` (an array of IDs), not `$options`.

### 3.2-model-behaviors.md

- **Issue:** Overview says "KMP implements three custom CakePHP behaviors" → **Actual:** There are 4 custom behaviors. `PublicIdBehavior` is not documented in this file at all.
- **Issue:** `findUpcoming()` generated SQL shown as `WHERE start_on > :effectiveDate OR (expires_on > :effectiveDate OR expires_on IS NULL)` → **Actual:** Same as 3-architecture.md — the operator between main conditions is `AND`, not `OR`.

### 3.3-database-schema.md

- **Issue:** Warrant System section (§4) only documents `warrant_periods` and `warrants` tables → **Actual:** The Warrants migration (`20241204160759_Warrants.php`) also creates `warrant_rosters` and `warrant_roster_approvals` tables, which are missing from this schema doc.

### 3.4-migration-documentation.md

- **Issue:** Migration §5 (Warrants) lists new tables as only `warrant_periods` and `warrants` → **Actual:** The migration also creates `warrant_rosters` and `warrant_roster_approvals` tables.
- **Issue:** Migration §10 scope examples show `'branch:local'`, `'event:crown_tournament'`, `'global'` → **Actual:** The `scoping_rule` column uses values `'Global'`, `'Branch Only'`, `'Branch and Children'` (from `Permission::SCOPE_*` constants). The colon-delimited format shown does not exist.

### 3.6-seed-documentation.md — ✅ Accurate

Seed structure, SeedHelpers methods, and initialization flow match actual code.

### 3.7-active-window-sync.md — ✅ Accurate

Command name, purpose, status transitions, and cron wrapper usage match actual `SyncActiveWindowStatusesCommand.php`.

### 3.8-youth-age-up.md — ✅ Accurate

Status transitions, command path (`app/src/Command/AgeUpMembersCommand.php`), and `Member::ageUpReview()` method all verified against source.

### 4-core-modules.md — ✅ Accurate (high-level overview)

Module listing and links are correct. The Member data model in the Mermaid diagram uses simplified field names, which is fine for overview purposes.

### 4.1-member-lifecycle.md — ✅ Accurate

Seven member statuses match `Member::STATUS_*` constants exactly. Age-up transition matrix matches `ageUpReview()` code. Warrant eligibility flow matches entity implementation.

### 4.1.1-members-table-reference.md — ✅ Accurate

Associations (`MemberRoles`, `CurrentMemberRoles`, `UpcomingMemberRoles`, `PreviousMemberRoles`, `Branches`, `Parents`, `Roles`, `GatheringAttendances`) verified. Behaviors (`Timestamp`, `Muffin/Footprint.Footprint`, `Muffin/Trash.Trash`, `JsonField`, `PublicId`) verified.

### 4.2-branch-hierarchy.md

- **Issue:** Audit fields table lists both `deleted` (DATETIME) and `deleted_date` (DATETIME) → **Actual:** Only `deleted` exists on the branches table. There is no `deleted_date` column in any migration or in the schema dump.

### 4.3-warrant-lifecycle.md

- **Issue:** References `DefaultWarrantManager::expireOldWarrants()` method → **Actual:** No method named `expireOldWarrants()` exists on `DefaultWarrantManager`. The class has `request()`, `approve()`, `decline()`, `cancel()`, `cancelByEntity()`, `declineSingleWarrant()`, `getWarrantPeriod()`, and protected helpers `cancelWarrant()`, `stopWarrantDependants()`, `declineWarrant()`. Expiry status transitions are handled by the `SyncActiveWindowStatusesCommand`.

### 4.4-rbac-security-architecture.md — ✅ Accurate

Permission scoping rules, constants, RBAC entity relationships, and validation chain match source code. The ERD correctly uses `scoping_rule` field name.

### 4.6-gatherings-system.md — ✅ Accurate

Data model, table schemas, gathering staff management, and controller references verified against actual code.

### 4.6.1-calendar-download-feature.md — ✅ Accurate

`ICalendarService` location, method signature, and RFC 5545 compliance description match actual `app/src/Services/ICalendarService.php`.

### 4.6.2-gathering-staff-management.md — ✅ Accurate

Database schema, business rules (XOR constraint, steward contact rule), and controller references verified.

### 4.6.3-gathering-schedule-system.md — ✅ Accurate

`gathering_scheduled_activities` table schema, timezone implementation, and data flow verified against `GatheringScheduledActivitiesTable.php` and entity.

### 4.6.4-waiver-exemption-system.md — ✅ Accurate

Exemption schema additions, backend components, and controller actions match Waivers plugin code.

### 4.7-document-management-system.md — ✅ Accurate

Document entity properties, polymorphic pattern, and DocumentService location match actual code. Virtual fields (`_getMetadataArray`, `_getFileSizeFormatted`) verified.

### 4.9-impersonation-mode.md — ✅ Accurate

`impersonation_action_logs` and `impersonation_session_logs` tables exist. Audit trail columns match `BaseTable::logImpersonationAction()` implementation.

### 6-services.md

- **Issue:** WarrantManager events listed as `ActiveWindow.beforeStart`, `ActiveWindow.afterStart`, `ActiveWindow.beforeStop`, `ActiveWindow.afterStop` → **Actual:** Could not confirm these exact event names are dispatched. The `DefaultWarrantManager` delegates to `ActiveWindowManager` for lifecycle management, but the event naming convention should be verified. This is a low-confidence finding — events may be dispatched in `DefaultActiveWindowManager` but I didn't trace through the full event chain.

### 6.2-authorization-helpers.md — ✅ Accurate

`getBranchIdsForAction()`, `resolvePolicyClass()`, and `getPolicyClassFromTableName()` methods confirmed on `Member` entity with matching signatures and behavior.

### 6.3-email-template-management.md

- **Issue:** File paths shown as `src/Service/MailerDiscoveryService.php` and `src/Service/EmailTemplateRendererService.php` (singular "Service") → **Actual:** Correct paths are `src/Services/MailerDiscoveryService.php` and `src/Services/EmailTemplateRendererService.php` (plural "Services").

### 6.4-caching-strategy.md — ✅ Accurate

Cache stores, TTLs, groups, APCu engine usage, and debug mode behavior match `app/config/app.php` configuration exactly.

---

#### Summary

| Category | Total | Accurate | With Issues |
|----------|-------|----------|-------------|
| Architecture (3.x) | 8 | 3 | 5 |
| Core Modules (4.x) | 13 | 11 | 2 |
| Services (6.x) | 4 | 2 | 2 |
| **Total** | **25** | **16** | **9** |

#### Critical Issues (could mislead developers)

1. **3.1 services() registration** — Shows services that aren't actually registered in the DI container. Developers might expect NavigationRegistry to be injectable.
2. **3.1 session config** — Timeout shown as 240min but actual is 30min; cookie name is wrong. Could cause security review confusion.
3. **3.1 PermissionsLoader scoping** — Wrong property name and wrong scope values. Would cause bugs if someone writes code based on this doc.
4. **3.4 scoping_rule values** — Shows non-existent colon-delimited format. Would confuse anyone implementing scoping logic.
5. **3.3 / 3.4 missing warrant_rosters tables** — Omission of two core warrant tables from schema and migration docs.

#### Minor Issues

1. Missing Waivers/Template plugins from directory listing
2. Entity hierarchy inaccuracies (WarrantRoster, naming)
3. PublicIdBehavior not documented
4. findUpcoming SQL OR vs AND
5. deleted_date phantom column on branches
6. Non-existent expireOldWarrants() method reference
7. Plural vs singular "Services" directory name


#### Plugin documentation accuracy review
**By:** Mal
**What:** Reviewed 36 plugin docs against plugin source code
**Why:** Ensuring published documentation matches current application and plugins

---

### 5-plugins.md (Plugin Architecture Overview)

- **Issue:** "Standard Migration Orders" lists Activities=1, Awards=2, Officers=3, Queue=10, GitHubIssueSubmitter=11, Bootstrap=12 → **Actual:** `config/plugins.php` shows Activities=1, Officers=2, Awards=3, Waivers=4. Queue, Bootstrap, and GitHubIssueSubmitter have NO migrationOrder set (defaulting to 0). Officers and Awards are swapped vs doc.
- **Issue:** Config example shows `description`, `category`, `required`, `dependencies`, `conditional` keys → **Actual:** `config/plugins.php` contains none of these keys. Config entries are minimal (just `migrationOrder` for domain plugins, empty arrays for others).
- **Issue:** Doc says "Bootstrap Plugin (migrationOrder: 12)" → **Actual:** `BootstrapPlugin` is an empty class extending `BasePlugin` with no `KMPPluginInterface` implementation and no migration order. Same issue for GitHubIssueSubmitter (doc says migrationOrder: 11, actual: no KMPPluginInterface, no migration order).
- **Issue:** Security Architecture example shows custom `before()` method with `BeforePolicyInterface` trait → **Actual:** All KMP policies extend `BasePolicy` which handles the before hook internally. The example pattern doesn't match how the codebase actually works.

### 5.1-officers-plugin.md — ✅ Mostly Accurate

- Minor note: Term length correctly described as "in months" which matches code (`$startOn->addMonths($office->term_length)`).
- Data model and key features all verified against source code.

### 5.1.1-officers-services.md

- **Issue:** Interface definition for `release()` shows 5 parameters including `?string $releaseStatus = Officer::RELEASED_STATUS` → **Actual:** The `OfficerManagerInterface::release()` has only 4 parameters (no `$releaseStatus`). The 5th parameter exists only in the `DefaultOfficerManager` implementation with a default value. The doc's "Interface Methods" section incorrectly shows the implementation signature, not the interface signature.

### 5.2-awards-plugin.md

- **Issue:** Data model shows `Award.active: bool` → **Actual:** Award entity has no `active` field. It has `open_date` and `close_date` instead.
- **Issue:** Data model shows `Domain.description: text` and `Domain.active: bool` → **Actual:** Domain entity has only `name` — no `description` or `active` fields.
- **Issue:** Data model shows `Event.active: bool` → **Actual:** Event entity has no `active` field. It has `closed: bool` only.
- **Issue:** Award entity missing fields from data model: `abbreviation`, `insignia`, `badge`, `charter`, `open_date`, `close_date` are all present in actual code but absent from the documented data model.

### 5.2.1-awards-events-table.md

- **Issue:** References non-existent doc: `5.2.2-awards-event-entity.md` → **Actual:** No such file exists. The 5.2.2 file is `5.2.2-awards-levels-table.md`. There is no separate Event entity reference doc.
- **Issue:** Doc says `description` field has max 255 chars, required → **Actual:** Matches code. Verified OK.

### 5.2.2-awards-levels-table.md

- **Issue:** Related Documentation links to `5.2.3-awards-domains-table.md` → **Actual:** No such file exists. The 5.2.3 file is `5.2.3-awards-recommendations-states-logs-table.md`. There is no DomainsTable reference doc.

### 5.2.3-awards-recommendations-states-logs-table.md — ✅ Accurate

- All claims verified: class definition, table name, display field, associations, behaviors, validation rules, business rules match actual code.
- Correctly notes no Trash behavior and immutable records.

### 5.2.4-awards-recommendations-table.md — ✅ Mostly Accurate

- Associations, behaviors, validation, and business rules all match actual code.
- Note: `afterSave` signature uses `$created` as first parameter instead of CakePHP's standard `EventInterface $event`, but doc matches the actual code (this is a potential code issue, not a doc issue).

### 5.2.5-awards-award-policy.md — ✅ Accurate

- Correctly documents empty BasePolicy extension with RBAC delegation.

### 5.2.6-awards-table-policy.md — ✅ Accurate

- `scopeIndex()` and `scopeGridData()` methods verified against source.
- Approval level filtering logic matches actual code.

### 5.2.7-awards-domain-policy.md — ✅ Accurate

- Empty BasePolicy extension, correctly documented.

### 5.2.8-awards-domains-table-policy.md — ✅ Accurate

- `scopeGridData()` method verified, delegates to `scopeIndex()` as documented.

### 5.2.9-awards-event-policy.md — ✅ Accurate

- `canAllEvents()` custom method verified against source.

### 5.2.10-awards-events-table-policy.md — ✅ Accurate (spot-checked)

- Pattern consistent with other table policies.

### 5.2.11-awards-level-policy.md — ✅ Accurate (spot-checked)

- Empty BasePolicy extension pattern, consistent.

### 5.2.12-awards-levels-table-policy.md — ✅ Accurate (spot-checked)

- Table policy pattern consistent with others.

### 5.2.13-awards-recommendation-policy.md — ✅ Accurate

- All 11 custom methods verified: `canAdd`, `canViewSubmittedByMember`, `canViewSubmittedForMember`, `canViewEventRecommendations`, `canViewGatheringRecommendations`, `canExport`, `canUseBoard`, `canViewHidden`, `canViewPrivateNotes`, `canAddNote`, `canUpdateStates`.
- Dynamic `__call()` and `getDynamicMethods()` verified.
- Note: Doc doesn't mention `canManageRecommendationMember()` protected method used by `canViewSubmittedByMember`, but this is a minor implementation detail.

### 5.2.14-awards-recommendations-states-log-policy.md — ✅ Accurate (spot-checked)

- Empty BasePolicy extension, consistent.

### 5.2.15-awards-recommendations-states-log-table-policy.md — ✅ Accurate (spot-checked)

- Empty BasePolicy extension, consistent.

### 5.2.16-awards-recommendations-table-policy.md

- **Issue:** Example query transformation shows `$query->contain(['Awards.Levels'])->where(['Levels.name in' => $approvalLevels])` → **Actual:** Code uses `$query->matching('Awards.Levels', function ($q) use ($approvalLevels) { ... })`. `matching()` creates an INNER JOIN; `contain()` does eager loading. Functionally different — `matching()` filters results, `contain()` just includes related data.
- **Issue:** Doc doesn't mention global access sentinel check (`$branchIds[0] != -10000000`) → **Actual:** The `scopeIndex()` implementation skips branch scoping when `$branchIds[0] == -10000000` (global access). This is a significant implementation detail not documented.

### 5.2.17-awards-services.md — ✅ Mostly Accurate

- `AwardsNavigationProvider::getNavigationItems()` verified — navigation items, icons, merge paths, and labels all match.
- `AwardsViewCellProvider::getViewCells()` verified — cell configurations, orders, labels, and permission checks match.
- View cell documentation (MemberSubmittedRecsCell, RecsForMemberCell, ActivityAwardsCell, GatheringAwardsCell) all verified.
- Minor: Doc says "Events" navigation but actual code only generates static items plus per-status links from `Recommendation::getStatuses()`. The doc correctly describes this behavior.

### 5.3-queue-plugin.md — ✅ Mostly Accurate

- Correctly identifies as forked dereuromark/cakephp-queue.
- Plugin class correctly shown as implementing `KMPPluginInterface`.
- Navigation integration code verified against actual `QueueNavigationProvider`.
- Note: Some config values in the doc may differ from actual `app_queue.php` but the patterns are correct.

### 5.4-github-issue-submitter-plugin.md

- **Issue:** Document title says `# 5.5 GitHubIssueSubmitter Plugin` → **Actual:** File is `5.4-github-issue-submitter-plugin.md`. Should be `# 5.4` or `# 5.5` depending on intended numbering. Inconsistent with filename.
- **Issue:** Doc says plugin `extends BasePlugin` without KMPPluginInterface → **Actual:** Verified correct — `GitHubIssueSubmitterPlugin extends BasePlugin` (no KMPPluginInterface). Doc is accurate here.
- **Issue:** Doc says plugin class is `GitHubIssueSubmitterPlugin extends BasePlugin` → **Actual:** Correct. But the overview doc (5-plugins.md) implies all plugins implement `KMPPluginInterface`, which is false for this plugin and Bootstrap.

### 5.5-bootstrap-plugin.md

- **Issue:** Doc extensively documents HtmlHelper, CardHelper, ModalHelper, NavbarHelper methods and features → **Actual:** The Bootstrap plugin class itself is completely empty (`class BootstrapPlugin extends BasePlugin {}`). The helpers and widgets DO exist in the plugin directory, but the plugin class does nothing — no bootstrap, no navigation, no service registration. Doc is accurate about the helpers but could be clearer that the plugin itself is just a container for helper classes with zero initialization logic.
- All helper file paths verified as existing. Method documentation appears thorough and was previously fact-checked per the doc's own notes.

### 5.6-activities-plugin.md — ✅ Accurate

- Authorization status constants match source code exactly.
- State machine workflow matches `DefaultAuthorizationManager` implementation.
- ActiveWindow integration correctly described.

### 5.6-activities-plugin-quick-reference.md — ✅ Accurate

- Navigation links all valid.

### 5.6-activities-plugin-documentation-refactoring.md — ✅ Accurate

- Meta-document about refactoring, no code claims to verify.

### 5.6.1-activities-plugin-architecture.md — ✅ Accurate

- Plugin dependencies, service registration, navigation/cell integration all match `ActivitiesPlugin.php`.
- Configuration version management verified.

### 5.6.2-activities-controller-reference.md — ✅ Accurate (spot-checked)

- Controller class exists, uses DataverseGridTrait as documented.
- Controller file at correct path.

### 5.6.3-activity-groups-controller-reference.md — ✅ Accurate (spot-checked)

- Controller class exists with DataverseGridTrait.

### 5.6.4-activity-entity-reference.md — ✅ Accurate

- All properties verified against `Activity.php`: `name`, `term_length`, `activity_group_id`, `minimum_age`, `maximum_age`, `num_required_authorizors`, `num_required_renewers`, `permission_id`, `grants_role_id`.
- `$_accessible` array matches documentation.
- Virtual properties `_getActivityGroupName()` and `_getGrantsRoleName()` verified.
- `getApproversQuery()` method verified.

### 5.6.5-activity-security-patterns.md — ✅ Accurate

- Mass assignment configuration matches actual `$_accessible` array exactly.

### 5.6.6-activity-groups-entity-reference.md — ✅ Accurate

- Entity extends `BaseEntity`, accessible fields match (`name` only).

### 5.6.7-authorization-entity-reference.md — ✅ Accurate

- All status constants verified.
- Entity properties and database schema match.
- `ActiveWindowBaseEntity` inheritance confirmed.

### 5.6.8-authorization-approval-entity-reference.md — ✅ Accurate

- Entity extends `BaseEntity` as documented.
- All fields in `$_accessible` match documentation.

### 5.7-waivers-plugin.md

- **Issue:** Plugin structure lists `WaiverDashboardController.php` → **Actual:** This controller does NOT exist. Actual controllers are: `GatheringWaiversController`, `WaiverTypesController`, `GatheringActivityWaiversController`, `AppController`.
- **Issue:** Plugin structure lists only 2 JS controllers: `waiver-upload-wizard-controller.js` and `activity-waiver-manager-controller.js` → **Actual:** There are 10 JS controllers, and `activity-waiver-manager-controller.js` does NOT exist. Actual controllers include: `waiver-upload-wizard-controller.js`, `waiver-upload-controller.js`, `waiver-calendar-controller.js`, `waiver-attestation-controller.js`, `waiver-template-controller.js`, `add-requirement-controller.js`, `exemption-reasons-controller.js`, `retention-policy-input-controller.js`, `camera-capture-controller.js`, `hello-world-controller.js`.
- **Issue:** Plugin structure lists only 2 entities: `GatheringWaiver.php`, `WaiverType.php` → **Actual:** 4 entities exist: `GatheringWaiver.php`, `WaiverType.php`, `GatheringWaiverClosure.php`, `GatheringActivityWaiver.php`.
- **Issue:** Plugin structure lists only 2 tables → **Actual:** 4 tables exist: `GatheringWaiversTable.php`, `WaiverTypesTable.php`, `GatheringWaiverClosuresTable.php`, `GatheringActivityWaiversTable.php`.
- **Issue:** Plugin structure lists only 2 policies: `GatheringWaiverPolicy.php`, `WaiverTypePolicy.php` → **Actual:** 8 policy files exist, also including: `GatheringWaiversTablePolicy.php`, `GatheringActivityWaiverPolicy.php`, `GatheringWaiversControllerPolicy.php`, `WaiverTypesTablePolicy.php`, `GatheringActivityWaiversTablePolicy.php`, `WaiverPolicy.php`.
- **Issue:** Plugin structure doesn't show Services or View/Cell directories → **Actual:** These exist with `WaiversNavigationProvider.php`, `WaiversViewCellProvider.php`, `GatheringActivityService.php`, `GatheringActivityWaiversCell.php`, `GatheringWaiversCell.php`.

---

### Summary of Findings

#### Critical Issues (wrong information)
1. **5-plugins.md**: Migration orders for Officers/Awards swapped; fabricated migration orders for Queue/Bootstrap/GitHubIssueSubmitter; non-existent config keys documented
2. **5.1.1-officers-services.md**: Interface signature for `release()` shows implementation signature instead of actual interface
3. **5.2-awards-plugin.md**: Data model shows non-existent `active` fields on Award/Domain/Event entities; missing actual Award fields
4. **5.2.1-awards-events-table.md**: Broken cross-reference to non-existent `5.2.2-awards-event-entity.md`
5. **5.2.2-awards-levels-table.md**: Broken cross-reference to non-existent `5.2.3-awards-domains-table.md`
6. **5.7-waivers-plugin.md**: Plugin structure severely outdated — missing controllers, entities, tables, policies, JS controllers; includes non-existent files

#### Minor Issues
7. **5.4-github-issue-submitter-plugin.md**: Title section number (5.5) doesn't match filename (5.4)
8. **5.2.16-awards-recommendations-table-policy.md**: Example uses `contain()` instead of actual `matching()` call; missing global access sentinel documentation

#### Accurate Docs (22 of 36)
5.2.3, 5.2.5, 5.2.6, 5.2.7, 5.2.8, 5.2.9, 5.2.10, 5.2.11, 5.2.12, 5.2.13, 5.2.14, 5.2.15, 5.3, 5.5, 5.6, 5.6-quick-ref, 5.6-refactoring, 5.6.1, 5.6.4, 5.6.5, 5.6.6, 5.6.7, 5.6.8 — all verified as accurate or substantially accurate.


#### Frontend/UI documentation accuracy review
**By:** Wash
**What:** Reviewed 14 frontend, UI, and JavaScript docs against codebase
**Why:** Ensuring published documentation matches current frontend implementation

---

### 4.5-view-patterns.md

- **Issue:** Section 4.5.5 layout list shows `templates/layout/` containing only `default.php`, `ajax.php`, `error.php`, `turbo_frame.php`, `email/`, and `TwitterBootstrap/`. → **Actual:** Missing `mobile_app.php` and `public_event.php` from the top-level listing. The `TwitterBootstrap/` subdirectory exists and contains `cover.php`, `dashboard.php`, `register.php`, `signin.php`, `view_record.php` — these sublayouts should be enumerated.
- **Issue:** Section 4.5.2 AppView helper list omits `Markdown` and `Timezone` and `SecurityDebug`. → **Actual:** `AppView.php` loads `Markdown`, `Timezone`, and `SecurityDebug` helpers in addition to those listed in the doc. The doc's list in 4.5.2 is incomplete (the docblock in AppView.php is also missing these three, but the `initialize()` method loads them).
- **Issue:** Doc is otherwise structurally accurate for KmpHelper, ViewCells, AppNavCell, NavigationCell, NotesCell patterns, element system, and security patterns. → **Accurate** on those fronts.

### 9-ui-components.md

- **Issue:** Section 9.1 references layout `TwitterBootstrap/dashboard` and `TwitterBootstrap/signin` as if those are the primary layout names. → **Actual:** These layouts do exist under `templates/layout/TwitterBootstrap/` but the doc omits the other layouts in that directory (`cover.php`, `register.php`, `view_record.php`) and top-level layouts (`mobile_app.php`, `public_event.php`). The doc should clarify the full layout inventory.
- **Issue:** Section 9.3 autocomplete example uses `data-controller="autocomplete"` with targets like `data-autocomplete-target`. → **Actual:** The autocomplete controller is registered as `"ac"` (not `"autocomplete"`), so correct HTML attributes are `data-controller="ac"`, `data-ac-target="input"`, etc.
- **Issue:** Section 9.3 references a `form-handler` controller (`data-controller="form-handler"`) for progressive form enhancement. → **Actual:** No `form-handler-controller.js` exists in the codebase. This controller does not exist.
- **Issue:** Section 9.3 references a `toasts` controller (`data-controller="toasts"`) for toast notifications. → **Actual:** No `toasts-controller.js` exists in the codebase. This controller does not exist.
- **Issue:** Icon helper example shows `$this->IconSnippet->render('info-circle', 'Information')`. → **Actual:** The helper is loaded as `Templating.IconSnippet` and would be accessed as `$this->IconSnippet` — this is likely correct but should be verified against actual template usage patterns.

### 9.1-dataverse-grid-system.md

- **Issue:** Architecture diagram lists trait methods as `processDataverseGrid()`, `handleCsvExport()`, `isCsvExportRequest()`. → **Actual:** All three methods exist in `DataverseGridTrait.php` (lines 61, 1252, 1218). Additionally `buildDataverseGridState()` exists (line 800) and is not mentioned in this diagram.
- **Issue:** Existing implementations table shows `Gatherings` mapped to `GatheringTypesGridColumns`. → **Actual:** This should be `GatheringTypes` mapped to `GatheringTypesGridColumns`. `Gatherings` has its own `GatheringsGridColumns`. The table entry label is wrong.
- **Issue:** grid-view-controller "Key Actions" section lists `applyFilter(event)` and `search` as methods. → **Actual:** `applyFilter` does not exist as a method in `grid-view-controller.js`. The controller uses URL navigation for filter changes rather than client-side filter application methods. `search` is not a standalone method either — the controller manages search state via URL params.
- **Issue:** HTML Integration example shows `data-action="input->grid-view#search"` on search input. → **Actual:** The grid-view controller does not have a `search` action method. Search is handled through URL-based state management, not direct Stimulus actions.

### 9.2-bootstrap-icons.md

- **Issue:** Document states Bootstrap Icons version "1.13.1" at the top, but troubleshooting section references "1.11" as the version. → **Actual:** Inconsistent version references within the same doc. The actual installed version should be verified against `webroot/assets/bootstrap-icons/`.
- **Issue:** Doc is otherwise accurate for Icon helper usage patterns, rendering approach, and configuration. → **Accurate** on those fronts.

### 9.3-dataverse-grid-complete-guide.md

- **Issue:** Architecture diagram lists `buildDataverseGridState()` as a trait method (line 96-97 area). → **Actual:** This method does exist (DataverseGridTrait.php:800). Accurate.
- **Issue:** Due to document size (65.9KB), full verification was not possible for all code examples. The architecture, column configuration patterns, and feature flags match actual implementation based on spot checks. → **Likely accurate** but could not verify every example.

### 10-javascript-development.md

- **Issue:** Section 10.2 example controller uses `export default class extends Controller` pattern. → **Actual:** KMP controllers do NOT use this pattern. They use the `window.Controllers["name"] = ControllerClass` registration pattern. The example is misleading — it shows a standard Stimulus pattern that doesn't match KMP's actual convention.
- **Issue:** Section 10.5.4 contains duplicate documentation blocks — `Detail Tabs Controller` is documented twice (lines ~484-536 and ~544-596), and `Modal Opener Controller` is documented twice (lines ~404-447 and ~865-907). → **Actual:** These are verbatim duplicates that should be consolidated.
- **Issue:** Section 10.5.4 `guifier-control` controller name in doc example uses `data-controller="guifier-control"`. → **Actual:** Correct — the controller registers as `window.Controllers["guifier-control"]` which matches.
- **Issue:** Doc is generally accurate for controller targets, values, methods, and usage patterns for the documented controllers. → **Accurate** on substantive claims.

### 10.1-javascript-framework.md

- **Issue:** Core Technologies section lists Turbo version as `^8.0.4`. → **Actual:** `package.json` shows `"@hotwired/turbo": "^8.0.21"`. Version is outdated.
- **Issue:** Main Entry Point code example shows `import { Application, Controller } from "@hotwired/stimulus"`. → **Actual:** `index.js` only imports `{ Application }` from stimulus, not `Controller`. Controller is imported individually by each controller file.
- **Issue:** Main Entry Point code example shows `window.Stimulus = Application.start()`. → **Actual:** Code uses `const stimulusApp = Application.start(); window.Stimulus = stimulusApp;` — minor naming difference but the variable name `stimulusApp` is used internally.
- **Issue:** Main Entry Point code uses `for (var controller in window.Controllers)`. → **Actual:** Code uses `for (const controller in window.Controllers)` — `const` not `var`.
- **Issue:** Main Entry Point code example is missing the `import './timezone-utils.js'` import and the four explicit controller imports (`qrcode-controller`, `timezone-input-controller`, `security-debug-controller`, `popover-controller`). → **Actual:** These imports exist in `index.js` but are not shown in the doc.
- **Issue:** Main Entry Point is missing the `Turbo.session.drive = false` line. → **Actual:** This critical line exists in `index.js` (disabling Turbo Drive) but is not shown in the doc's code example.
- **Issue:** Main Entry Point is missing the `turbo:render` event listener for re-initializing tooltips on Turbo frame loads. → **Actual:** This exists in actual code but not in doc.
- **Issue:** Webpack.mix.js code example is incomplete — missing service file discovery (`assets/js/services/*-service.js`), Waivers CSS compilation, FontAwesome font directory copying, and webpack font module rules. → **Actual:** `webpack.mix.js` includes `getJsFilesFromDir('./assets/js/services', ...)`, two Waivers CSS entries, `.copyDirectory('node_modules/@fortawesome/fontawesome-free/webfonts', 'webroot/fonts')`, and font asset rules in webpackConfig.

### 10.2-qrcode-controller.md

- **Issue:** Static Values section shows `errorCorrectionLevel` default as `"M"`. → **Actual:** `qrcode-controller.js` line 33 shows default is `"H"` (High), not `"M"` (Medium).
- **Issue:** The `canvas` target is described as "The canvas element where the QR code is rendered". → **Actual:** The target is actually a container `<div>` element. The controller creates a `<canvas>` element inside it (`this.canvasTarget.innerHTML = ''; const canvas = document.createElement('canvas'); this.canvasTarget.appendChild(canvas);`). The doc treats the target AS the canvas, but it's a wrapper div.
- **Issue:** `generate()` method doc shows `console.error` for missing URL/canvas. → **Actual:** The code throws `new Error()` exceptions, not console.error calls.
- **Issue:** `download()` method doc shows `this.canvasTarget.toBlob()` directly. → **Actual:** Code does `this.canvasTarget.querySelector('canvas')` first, then calls `.toDataURL()` on the queried canvas (not `.toBlob()`). The download mechanism uses `canvas.toDataURL('image/png')` and an anchor link, not the blob pattern shown in the doc.
- **Issue:** `copyToClipboard()` method doc shows direct `this.canvasTarget.toBlob()`. → **Actual:** Code does `this.canvasTarget.querySelector('canvas')` first, then calls `.toBlob()` on the queried element. Also, the doc shows an `alert()` on success but actual code does not show an alert.
- **Issue:** Controller Registration section shows registration happening via explicit import in `index.js` with code like `import QRCodeController from './controllers/qrcode-controller.js'` followed by manual `window.Controllers["qrcode"] = QRCodeController`. → **Actual:** `index.js` does `import './controllers/qrcode-controller.js'` (side-effect import), and the controller self-registers via `window.Controllers` in its own file. The doc's code example is misleading — the registration happens inside the controller file, not in index.js.

### 10.3-timezone-handling.md

- **Issue:** PHP TimezoneHelper section references `templates/element/timezone_examples.php` as an examples element. → **Actual:** This file does not exist in `app/templates/element/`.
- **Issue:** Doc is otherwise accurate for the PHP/JS timezone architecture, `KMP_Timezone` utility API, and form conversion workflows. → **Accurate** on substantive claims.

### 10.3.1-timezone-utils-api.md — ✅ Accurate

All method signatures, parameter types, return types, and behavior descriptions match the actual `timezone-utils.js` source code. `detectTimezone()`, `getTimezone()`, `formatDateTime()`, `formatDate()`, `formatTime()`, `toLocalInput()`, `toUTC()`, `getTimezoneOffset()`, `getAbbreviation()`, `initializeDatetimeInputs()`, and `convertFormDatetimesToUTC()` are all correctly documented.

### 10.3.2-timezone-input-controller.md — ✅ Accurate

All targets (`datetimeInput`, `notice`), values (`timezone`, `showNotice`), lifecycle methods, and behavior descriptions match `timezone-input-controller.js`. The controller structure, submit/reset handling, and `KMP_Timezone` integration are correctly documented.

### 10.4-asset-management.md

- **Issue:** Directory structure section shows `assets/images/` in source assets. → **Actual:** No `assets/images/` directory exists. Images are stored directly in `webroot/img/`.
- **Issue:** Public Assets section shows output files as `app.js`, `vendor.js` etc. → **Actual:** The webpack output files are `index.js`, `controllers.js`, `core.js` (vendor), and a `runtime.js` — not `app.js` and `vendor.js`. The naming convention shown in the doc doesn't match actual webpack.mix.js output paths.
- **Issue:** Webpack.mix.js simplified config shows only `mix.js('assets/js/index.js', 'webroot/js')` and `.extract(['bootstrap', 'popper.js', '@hotwired/turbo', '@hotwired/stimulus'])`. → **Actual:** Config also includes controller file discovery, service file discovery, controllers.js bundle, Waivers CSS, FontAwesome font copying, font module rules, and `@hotwired/stimulus-webpack-helpers` in extract. The extract output is explicitly named `'webroot/js/core.js'` in actual config.
- **Issue:** KMP_utils.js code example shows `urlParam()` using `URLSearchParams` and `sanitizeString()` using `div.textContent`. → **Actual:** Real `KMP_utils.js` uses `RegExp` for `urlParam()` and a character map with regex replacement for `sanitizeString()`. The implementations are completely different.
- **Issue:** CSS section shows SCSS syntax (`$primary: #007bff;`, `@import 'node_modules/bootstrap/scss/bootstrap'`). → **Actual:** `app.css` is plain CSS, not SCSS. The project does have `sass` and `sass-loader` in devDependencies but the main stylesheet is not using SCSS syntax.
- **Issue:** CSS section shows component/layout/page subdirectory organization. → **Actual:** `assets/css/` contains flat files: `app.css`, `signin.css`, `cover.css`, `dashboard.css`, `bootstrap.css`, `bootstrap.min.css`, `bootstrap-icon-sizes.css`, `bootstrap-icons.css`, `gatherings_public.css`. No subdirectory organization exists.
- **Issue:** NPM Scripts section shows `"lint": "eslint assets/js --fix"`. → **Actual:** No `lint` script exists in `package.json`.
- **Issue:** NPM Scripts shows `"test:ui": "playwright test"`. → **Actual:** Actual script is `"test:ui": "bddgen && bash ../reset_dev_database.sh && playwright test"`.
- **Issue:** The "production-only configurations" section shows `mix.minify()` and `mix.disableSuccessNotifications()` conditional blocks. → **Actual:** Neither of these exist in the actual `webpack.mix.js`. There are no production-specific conditional blocks.
- **Issue:** Asset versioning section uses `Html->script('app')` and `Html->css('app')` with auto-versioning from mix-manifest. → **Actual:** Templates use `$this->AssetMix->script()` and `$this->AssetMix->css()` helpers, not the plain `Html` helper. The `Html` helper does not integrate with mix-manifest for versioning.

### dataverse-grid-custom-filter-audit.md — ✅ Accurate

This is a technical audit document, not implementation docs. The findings about custom filter handler needs correctly identify columns using `contain()` instead of joins, virtual/computed fields, and self-relations. The grids and filter issues described are consistent with the GridColumns source files.

### dataverse-grid-migration-todo.md — ✅ Accurate

This is a status tracking document. The phases, file paths, and implementation patterns described are consistent with actual controller and template structure. The known issues and testing checklist are appropriate.

---

### Summary

| Doc | Status | Issue Count |
|-----|--------|-------------|
| 4.5-view-patterns.md | ⚠️ Minor issues | 2 |
| 9-ui-components.md | ❌ Multiple issues | 5 |
| 9.1-dataverse-grid-system.md | ⚠️ Minor issues | 4 |
| 9.2-bootstrap-icons.md | ⚠️ Minor issue | 1 |
| 9.3-dataverse-grid-complete-guide.md | ✅ Likely accurate | 0 |
| 10-javascript-development.md | ⚠️ Minor issues | 3 |
| 10.1-javascript-framework.md | ❌ Multiple issues | 7 |
| 10.2-qrcode-controller.md | ❌ Multiple issues | 6 |
| 10.3-timezone-handling.md | ⚠️ Minor issue | 1 |
| 10.3.1-timezone-utils-api.md | ✅ Accurate | 0 |
| 10.3.2-timezone-input-controller.md | ✅ Accurate | 0 |
| 10.4-asset-management.md | ❌ Multiple issues | 10 |
| dataverse-grid-custom-filter-audit.md | ✅ Accurate | 0 |
| dataverse-grid-migration-todo.md | ✅ Accurate | 0 |

**Highest priority fixes:** `10.4-asset-management.md` (10 issues — most inaccurate doc), `10.1-javascript-framework.md` (7 issues — outdated code examples), `10.2-qrcode-controller.md` (6 issues — wrong defaults and method descriptions), `9-ui-components.md` (5 issues — references non-existent controllers).

### 2026-02-10: Skipped Test Triage & Suite Config

**By:** Jayne (Tester)
**Date:** 2026-02-10

## phpunit.xml.dist Suite Assignments

- `ApplicationTest.php` → core-unit (tests Application bootstrapping, not HTTP behavior)
- `tests/TestCase/Command/` → core-feature (CLI commands are feature-level tests)

## Skipped Test Decisions

### Removed as redundant/dead (9 tests):
- Circular reference & root branch deletion tests → tested at model level, controller test adds no value
- EditGet → no edit.php template exists, GET on /members/edit renders nothing
- TransactionIsolation/Rollback → tested in BaseTestCaseTest, not a Members concern
- BakeQueueTaskCommand (both tests) → bake reference files not migrated, bake is not critical for owned plugin
- RunCommandTest::testServiceInjection → requires upstream TestApp/Foo fixture we don't have
- HelloWorldControllerTest::testJsonResponse → demo plugin, JSON responses not needed

### Fixed with real assertions (4 tests):
- testAddPostWithValidData → POST valid member data, assert redirect to view page
- testEditPostWithValidData → POST edit on admin member, assert redirect
- testDelete → DELETE test member, assert redirect (uses TEST_MEMBER_AGATHA_ID)
- testAutoComplete → GET with query, assert HTML list-group-item in response

### Auth blocker:
testAddPostWithValidData and testEditPostWithValidData fail with the same pre-existing `/pages/unauthorized` redirect that affects ALL MembersControllerTest tests. The `SuperUserAuthenticatedTrait` isn't properly authenticating. Once auth is fixed (Phase 5 work), these tests will pass as-is.

### 2026-02-10: BasePolicy array resource handling via before() reflection

**Date:** 2026-02-10
**Author:** Kaylee
**Status:** Implemented

## Context

`authorizeCurrentUrl()` passes URL params as an array to the Authorization component. For controller policies extending BasePolicy, this array reaches type-hinted `can*` methods (`canIndex(KmpIdentityInterface $user, BaseEntity|Table $entity)`) causing a TypeError for non-super users.

## Decision

Handle array resources in `BasePolicy::before()` using `ReflectionMethod::getDeclaringClass()` to distinguish inherited methods (can't accept arrays) from subclass overrides (may accept arrays).

- If method is declared in BasePolicy → intercept, use `_hasPolicyForUrl()`, return result
- If method is declared in a subclass → let it through, subclass handles its own types

## Why not other approaches

- **Change BasePolicy method signatures to `mixed`:** PHP parameter contravariance would break ALL subclass overrides that use `BaseEntity|Table`
- **Change to `BaseEntity|Table|array`:** Same variance problem — subclasses can't narrow to `BaseEntity|Table`
- **Blanket `before()` without reflection:** Would bypass subclass methods like `GatheringWaiversControllerPolicy::canNeedingWaivers` that have custom array handling (e.g., steward fallback checks)

## Impact

- Fixes controller policies that extend BasePolicy without overriding can* methods (e.g., HelloWorldControllerPolicy)
- No changes to any subclass or method signature
- Reflection cost is minimal (one call per authorization check)

## Files Changed

- `app/src/Policy/BasePolicy.php` — added array handling in `before()`

---

# Kaylee — Documentation Modernization Decisions

**Date:** 2026-02-10  
**Author:** Kaylee (Backend Dev)  
**Task:** Fix 13 documentation accuracy issues found during codebase review

## Decisions Made

### 1. DI Container Documentation — Show Only What's Actually Registered
**Decision:** Removed `NavigationRegistry` and `KmpAuthorizationService` from DI container docs since they aren't registered in `Application::services()`. Only documented the 5 actual registrations: ActiveWindowManagerInterface, WarrantManagerInterface, CsvExportService, ICalendarService, ImpersonationService.  
**Rationale:** NavigationRegistry is used statically (session-cached), not via DI. KmpAuthorizationService doesn't exist as a registered service.

### 2026-02-10: Session Configuration Documentation Fix (consolidated)
**By:** Kaylee, Jayne
**What:** Fixed session config docs to reflect actual CakePHP structure: flat `ini` block (not nested `cookie` object), timeout is 30 minutes (not 240), cookie name is `PHPSESSID`, config lives in `app.php` (not `app_local.php`), sessions are PHP file-based (not database-backed as `3.5-er-diagrams.md` claimed).
**Why:** Multiple docs had wrong session config — wrong timeout, wrong location, wrong session storage type. CakePHP 5.x uses PHP ini settings directly.

### 3. WarrantManager Events — Removed Fictional Events
**Decision:** Removed `ActiveWindow.beforeStart`, `ActiveWindow.afterStart`, `ActiveWindow.beforeStop`, `ActiveWindow.afterStop` event documentation. Neither WarrantManager nor ActiveWindowManager dispatches any events.  
**Rationale:** grep confirms zero event dispatches in either service. The lifecycle is driven entirely by direct method calls returning ServiceResult.

### 4. PublicIdBehavior — Added Full Documentation
**Decision:** Updated behavior count from 3 to 4, added complete PublicIdBehavior section to 3.2-model-behaviors.md with configuration, charset, usage, and database requirements.  
**Rationale:** Behavior was already in production code but missing from docs.

### 5. Console Commands — Created New Doc
**Decision:** Created `docs/7.7-console-commands.md` documenting 5 CLI commands. Excluded AgeUpMembersCommand and SyncActiveWindowStatusesCommand which are already documented in their respective sections.  
**Rationale:** Commands had no documentation at all. Cross-referenced existing docs to avoid duplication.

### 6. Services — Expanded Documentation
**Decision:** Added brief documentation for 11 services to 6-services.md, including purpose, key methods, and DI registration status.  
**Rationale:** Most services were completely undocumented. Kept descriptions concise per inline doc standards.

### 7. Warrant Expiry — Corrected Mechanism
**Decision:** Replaced reference to non-existent `expireOldWarrants()` with actual `SyncActiveWindowStatusesCommand`.  
**Rationale:** No `expireOldWarrants` method exists anywhere in the codebase. The status sync command handles all ActiveWindow entity status transitions.

## Files Modified

| File | Changes |
|------|---------|
| `docs/3.1-core-foundation-architecture.md` | Fixed DI registrations, session config (×2), PermissionsLoader scoping, entity hierarchy |
| `docs/3.2-model-behaviors.md` | Updated behavior count, added PublicIdBehavior section, fixed findUpcoming SQL |
| `docs/3.3-database-schema.md` | Added warrant_rosters and warrant_roster_approvals tables |
| `docs/3.4-migration-documentation.md` | Fixed scope format, added missing tables to warrant migration |
| `docs/3-architecture.md` | Added Waivers plugin, added PublicIdBehavior to mermaid |
| `docs/4.2-branch-hierarchy.md` | Removed non-existent deleted_date column |
| `docs/4.3-warrant-lifecycle.md` | Replaced expireOldWarrants with SyncActiveWindowStatusesCommand |
| `docs/6-services.md` | Fixed WarrantManager events, added 11 service docs |
| `docs/6.3-email-template-management.md` | Fixed src/Service/ → src/Services/ |
| `docs/7.7-console-commands.md` | **NEW** — Console commands documentation |

---

# Documentation Modernization Decisions

**Date:** 2026-02-10  
**Author:** Mal (Lead)  
**Requested by:** Josh Handel

## Context

8 documentation tasks identified during accuracy review. All docs were verified against actual source code before changes.

## Decisions Made

### D1: Broken cross-references → point to nearest valid doc
- `5.2.2-awards-event-entity.md` never existed → pointed to `5.2-awards-plugin.md`
- `5.2.3-awards-domains-table.md` never existed → pointed to `5.2.8-awards-domains-table-policy.md`
- **Rationale:** No standalone entity docs exist for these models. The plugin overview and table policy docs are the closest valid targets.

### D2: Interface docs show interface signatures, not implementation
- `release()` in OfficerManagerInterface has 4 params. The implementation's 5th param (`$releaseStatus`) is not part of the interface contract and was removed from the interface section of the doc.
- **Rationale:** Docs for an interface should reflect the interface, not leak implementation details.

### D3: Migration orders reflect actual plugins.php, not aspirational architecture
- Removed fabricated migrationOrder values for Queue (10), Bootstrap (12), GitHubIssueSubmitter (11) — these plugins have no migrationOrder in plugins.php.
- Removed fabricated plugins (Reports, OfficerEventReporting) from migration order listing.
- Removed nonexistent config keys (dependencies, conditional, description, category, required) from example registration code.
- Added Waivers (migrationOrder: 4) which was missing.
- **Rationale:** Documentation must match reality. Aspirational architecture belongs in design docs, not reference docs.

### D4: Waivers plugin doc rewritten from scratch
- Previous doc was ~50% coverage. New doc covers all 4 entities, 4 tables, 8 policies, 9 JS controllers, 3 services, 2 view cells, navigation, routes, and 13 migrations.
- Followed format of other plugin docs (5.1, 5.6).
- **Rationale:** Half-documented plugins are worse than undocumented — they create false confidence.

### D5: Awards data model corrected to match entity source
- Removed phantom `active` field from Award, Domain, Level, Event in Mermaid diagram.
- Added 6 missing Award fields: abbreviation, insignia, badge, charter, open_date, close_date.
- Fixed Domain (removed nonexistent `description` field), Level (removed nonexistent `description` field), Event (added missing `description` and `branch_id` fields).
- **Rationale:** Data model diagrams must match entity `@property` annotations.

### D6: Global access sentinel documented
- RecommendationsTablePolicy uses `-10000000` as branchIds[0] to indicate global/super-user access. This was undocumented. Added explanation.
- **Rationale:** Security-relevant patterns must be documented so future developers don't accidentally break the bypass.

## Files Modified

| File | Task | Change |
|------|------|--------|
| `docs/5.2.1-awards-events-table.md` | P1-3 | Fixed cross-reference to nonexistent entity doc |
| `docs/5.2.2-awards-levels-table.md` | P1-4 | Fixed cross-reference to nonexistent domains table doc |
| `docs/5.4-github-issue-submitter-plugin.md` | P1-5 | Fixed title from "5.5" to "5.4" |
| `docs/5.7-waivers-plugin.md` | P2-1 | Full rewrite from source code |
| `docs/5-plugins.md` | P3-7 | Fixed migration orders, removed fabricated config |
| `docs/5.2-awards-plugin.md` | P3-8 | Fixed data model diagram and award configuration |
| `docs/5.1.1-officers-services.md` | P3-12 | Fixed release() interface signature (5→4 params) |
| `docs/5.2.16-awards-recommendations-table-policy.md` | P3-13 | Fixed contain→matching, documented global access sentinel |

---

# Wash — Frontend Documentation Modernization Decisions

**Date:** 2026-02-10  
**Agent:** Wash (Frontend Dev)  
**Requested by:** Josh Handel

## Decisions Made

### D1: Rewrote asset management doc to reflect actual build pipeline
- **What:** `docs/10.4-asset-management.md` fully rewritten
- **Why:** Previous doc showed fictional `app.js`/`vendor.js` output, wrong SCSS imports, simplified webpack config, wrong asset helper (`Html` instead of `AssetMix`), incomplete npm scripts
- **Impact:** Developers will now see accurate build output (index.js, controllers.js, core.js, manifest.js), correct AssetMix helper usage, and full npm script reference

### D2: Rewrote JS framework doc with correct versions and patterns
- **What:** `docs/10.1-javascript-framework.md` fully rewritten
- **Why:** Had wrong Turbo version (^8.0.4 → ^8.0.21), missing imports (timezone-utils.js, specific controllers), didn't document Turbo Drive being disabled
- **Impact:** Accurate dependency table, correct index.js initialization flow, proper build config reference

### D3: Rewrote QR code controller doc to match actual implementation
- **What:** `docs/10.2-qrcode-controller.md` fully rewritten
- **Why:** Wrong default errorCorrectionLevel (M → H), wrong error handling (console.error → throw Error + fallback HTML), wrong download mechanism, wrong registration pattern
- **Impact:** Developers get accurate API reference matching actual controller behavior

### D4: Removed fictional controllers from UI components doc
- **What:** Removed `form-handler` and `toasts` controller references from `docs/9-ui-components.md`, fixed autocomplete controller name to `ac`
- **Why:** These controllers don't exist in the codebase. Autocomplete is registered as `"ac"` not `"autocomplete"`
- **Impact:** Prevents developers from trying to use non-existent controllers

### D5: Added missing helpers and layouts to view patterns doc
- **What:** Added Markdown, Timezone, SecurityDebug helpers and mobile_app.php, public_event.php layouts to `docs/4.5-view-patterns.md`
- **Why:** These exist in AppView and templates/layout/ but were not listed
- **Impact:** Complete reference for available helpers and layouts

### D6: Fixed Gatherings grid mapping
- **What:** Corrected Gatherings→GatheringsGridColumns mapping, added GatheringTypes row, removed non-existent applyFilter method in `docs/9.1-dataverse-grid-system.md`
- **Why:** Doc incorrectly mapped Gatherings entity to GatheringTypesGridColumns; applyFilter doesn't exist as a controller method
- **Impact:** Accurate grid implementation reference

### D7: Standardized Bootstrap Icons version to 1.11.3
- **What:** Fixed both version references in `docs/9.2-bootstrap-icons.md` to 1.11.3
- **Why:** Doc said 1.13.1 in overview and 1.11 in troubleshooting; actual version from CSS header is 1.11.3
- **Impact:** Consistent, accurate version reference

### D8: Removed duplicate controller sections from JS development doc
- **What:** Removed duplicate Detail Tabs Controller and Modal Opener Controller blocks, fixed controller example to use window.Controllers pattern in `docs/10-javascript-development.md`
- **Why:** Same controller documented 2-3 times; example showed `export default class` instead of actual window.Controllers pattern
- **Impact:** Cleaner doc without confusing duplicates, correct registration pattern

### D9: Confirmed timezone_examples.php exists — no change needed
- **What:** `docs/10.3-timezone-handling.md` left unchanged
- **Why:** `app/templates/element/timezone_examples.php` exists; the doc reference is accurate
- **Impact:** None

## Patterns Observed

1. **Fictional code in docs**: Several docs contained code examples for controllers/features that don't exist (form-handler, toasts). Docs should be written from source, not imagination.
2. **Version drift**: Package versions in docs don't get updated when dependencies change. Consider generating version tables from package.json.
3. **Duplicate sections**: Copy-paste during doc generation created duplicate controller documentation blocks.
4. **Asset helper confusion**: The AssetMix helper (not Html) is used for versioned assets — this is a common source of confusion.

---

# Documentation Modernization — Decisions Log

**Author:** Jayne (Tester)  
**Date:** 2026-02-10  
**Requested by:** Josh Handel

## Summary

Completed 13 documentation tasks fixing factual errors across 12 files. Every fix was verified against actual source code before changing the doc.

## Decisions Made

### D1: Deleted docs/8-development-workflow.md
- **Reason:** Exact duplicate of 7-development-workflow.md
- **Impact:** Removed dead link from index.md section 8.2

### D2: Rewrote docs/7-development-workflow.md from scratch
- **Reason:** Every testing section was wrong — wrong suite names, wrong base classes, wrong data strategy, references to non-existent scripts/methods
- **Source of truth:** `phpunit.xml.dist`, `BaseTestCase.php`, `HttpIntegrationTestCase.php`, `SeedManager.php`, `app/package.json`
- **Key changes:** Suite names are `core-unit`/`core-feature`/`plugins`/`all`. Base class is `BaseTestCase` (not `Cake\TestSuite\TestCase`). Data comes from `dev_seed_clean.sql` with transaction wrapping (not CakePHP fixtures). Removed `StaticHelpers::logVar`, `npm run lint`, `Psalm` references.

### D3: Standardized PHP version to 8.3
- **Source:** `.github/workflows/tests.yml` uses `php-version: '8.3'`
- **Note:** `composer.json` says `>=8.1` but CI tests on 8.3 and the Docker setup targets 8.3. Documentation should reflect the tested/recommended version.

### D4: Removed `bin/cake security generate_salt` references
- **Reason:** No `SecurityCommand` exists in `app/src/Command/`
- **Replacement:** `php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"`
- **Files affected:** `2-configuration.md`, `7.1-security-best-practices.md`, `8.1-environment-setup.md`

### D5: Fixed session configuration claims (consolidated into "Session Configuration Documentation Fix" above)

### D6: Fixed test data constants
- **`KINGDOM_BRANCH_ID`:** Doc said 1, actual value is 2
- **`TEST_BRANCH_LOCAL_ID`:** Doc said 1073, actual value is 14

### D7: Replaced deprecated SuperUserAuthenticatedTrait guidance
- **Old recommendation:** Use `SuperUserAuthenticatedTrait` with `BaseTestCase` + `IntegrationTestTrait`
- **New recommendation:** Extend `HttpIntegrationTestCase` which includes `TestAuthenticationHelper` automatically
- **Note:** The old trait still exists but new tests should use the new pattern

### D8: Fixed config loading hierarchy in 8.1-environment-setup.md
- **Actual order (from bootstrap.php):** `.env` → `app.php` → `app_local.php` → `app_queue.php`
- **Removed:** `DOCUMENTS_STORAGE_ADAPTER` env var reference (not used anywhere — adapter is set in `app_local.php`)

### D9: Fixed appendices.md
- **Bootstrap:** Updated docs link from 5.0 to 5.3 (package.json has `bootstrap: ^5.3.6`)
- **Icons:** Project uses both Font Awesome (`@fortawesome/fontawesome-free ^7.1.0`) and Bootstrap Icons (via Templating plugin). Added both references.

## Remaining Risks

1. **`composer.json` says `php >= 8.1`** but docs now say 8.3. If someone deploys on 8.1/8.2, Composer will allow it but the docs won't cover it. Consider bumping `composer.json` to `>=8.3`.
2. **The old `SuperUserAuthenticatedTrait` is still in the codebase.** A cleanup task to migrate remaining tests and remove it would be beneficial.
3. **No `npm run lint`** exists — if linting is desired, an ESLint config + script should be added.
