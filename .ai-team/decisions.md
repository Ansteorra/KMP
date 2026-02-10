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
### 2026-02-10: Test suite assessment (existing)
**By:** Jayne
**What:** Audited existing test suite and documented patterns and gaps
**Why:** New team needs to understand test conventions and coverage before adding tests

#### Test Patterns to Follow

1. **Always extend `BaseTestCase`** for any test that touches the database. It provides transaction wrapping (begin/rollback) so tests don't mutate shared seed data. Do NOT use CakePHP fixture classes — this project uses a seeded database approach with `dev_seed_clean.sql`.

2. **For HTTP/controller tests**, extend `HttpIntegrationTestCase` (or `PluginIntegrationTestCase` for plugin controllers). Use `SuperUserAuthenticatedTrait` for tests that need full admin access. Use `TestAuthenticationHelper` trait when you need lighter-weight auth without DB lookups.

3. **Reference seed data via `BaseTestCase` constants** — `ADMIN_MEMBER_ID`, `TEST_MEMBER_AGATHA_ID`, `TEST_MEMBER_BRYCE_ID`, `TEST_MEMBER_DEVON_ID`, `TEST_MEMBER_EIRIK_ID`, `KINGDOM_BRANCH_ID`, etc. Do NOT hardcode IDs.

4. **Two auth trait patterns exist** — `AuthenticatedTrait` (older, loads from DB) and `SuperUserAuthenticatedTrait` (newer, cleaner). Prefer `SuperUserAuthenticatedTrait` for new tests.

5. **PHPUnit 10.x** with 4 test suites: `core-unit`, `core-feature`, `plugins`, `all`. Place new tests in directories that match these suite definitions.

6. **Run tests with:** `cd app && composer test` or `cd app && vendor/bin/phpunit --testsuite <suite-name>`.

#### Gaps Identified

**Critical gaps (security/data integrity):**
- Authorization policies: 37 policy classes, 1 test (and it's trivial). Database-driven auth via `BasePolicy._hasPolicy()` needs integration testing.
- Warrant workflow: 0 tests for WarrantsController, WarrantRostersController, WarrantPeriodsController, or any warrant tables/entities.
- Service principals: 0 tests for ServicePrincipalsController or related tables — this is API key management.
- Impersonation: 0 tests for ImpersonationService or audit logs.

**Major gaps (core functionality):**
- 20 of 26 core controllers untested
- 26 of 32 core tables untested
- 29 of 34 core entities untested
- 6 of 7 commands untested
- All mailers untested
- Activities plugin: 7 test files exist but ALL are `markTestIncomplete` stubs
- Awards plugin: 2 test stubs, 0 real tests; missing controller/table/policy tests
- No API endpoint tests (`src/Controller/Api/`)

**Infrastructure gaps:**
- No CI pipeline runs tests — only JS build in GitHub Actions
- `app/tests/ViewCellRegistryTest.php` is a standalone script, not a proper PHPUnit test — should be moved or deleted
- Known issue: CakePHP `IntegrationTestTrait` has transaction isolation conflicts with the seed-based approach, causing some controller tests to skip record-creation scenarios

#### Recommendations

1. **Priority 1:** Add CI test runner to GitHub Actions. Tests are useless if they don't run on every PR.
2. **Priority 2:** Test warrant and authorization policy workflows — these are the security backbone.
3. **Priority 3:** Either implement or delete the `markTestIncomplete` stubs in plugin Cell tests. They inflate test counts while testing nothing.
4. **Priority 4:** Add controller integration tests for the remaining 20 untested controllers, starting with WarrantsController, RolesController, and PermissionsController.
5. Clean up `app/tests/ViewCellRegistryTest.php` — it's not a real test and shouldn't be in the test directory.
