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
