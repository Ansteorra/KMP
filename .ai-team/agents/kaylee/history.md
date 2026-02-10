# Project Context

- **Owner:** Josh Handel (josh@liveoak.ws)
- **Project:** KMP — Membership management system for SCA Kingdoms. Handles members, officers, warrants, awards, activities, and workflow-driven approvals. ~2 years of active development.
- **Stack:** CakePHP 5.x, Stimulus.JS, MariaDB, Docker, Laravel Mix, Bootstrap, plugin architecture
- **Created:** 2026-02-10

## Learnings

<!-- Append new learnings below. Each entry is something lasting about the project. -->

### 2026-02-10: Backend Codebase Deep Dive

#### Service Layer Map

**Core Services (DI registered in `Application::services()`):**
- `ActiveWindowManagerInterface` → `DefaultActiveWindowManager` — manages date-bounded entities (start/stop lifecycle). No deps. Used by WarrantManager and OfficerManager.
- `WarrantManagerInterface` → `DefaultWarrantManager` — full warrant lifecycle (request→approve→decline→cancel). Depends on `ActiveWindowManagerInterface`. Uses manual transactions.
- `CsvExportService` — standalone CSV export. No deps.
- `ICalendarService` — standalone iCal generation. No deps.
- `ImpersonationService` — session-based user impersonation. No deps.

**Plugin Services (DI registered in plugin `services()` methods):**
- `OfficerManagerInterface` → `DefaultOfficerManager` (Officers plugin) — officer assign/release/recalculate. Depends on `ActiveWindowManagerInterface` + `WarrantManagerInterface`.
- `AuthorizationManagerInterface` → `DefaultAuthorizationManager` (Activities plugin) — member activity authorization workflow (request→approve→deny→revoke→retract).
- `ReadOnlyDepartmentServiceInterface`, `ReadOnlyOfficeServiceInterface`, `ReadOnlyOfficerRosterServiceInterface` (Officers plugin) — read-only API services.

**Static Registries (not DI-managed, use static methods):**
- `NavigationRegistry` — plugins register nav items during bootstrap via `NavigationRegistry::register()`. Session-cached.
- `ViewCellRegistry` — plugins register view cells (tabs, details, modals) during bootstrap. Route-matched at request time.
- `ApiDataRegistry` — plugins register API data providers for enriching responses.

**Non-DI Services:**
- `AuthorizationService` (extends CakePHP's) — adds `checkCan()` with nested call state management and debug logging.
- `NavigationService` — thin wrapper around `NavigationRegistry`.
- `GridViewService` — supports the `DataverseGridTrait` for server-side grid processing.
- `ServiceResult` — standard return object: `{success: bool, reason: ?string, data: mixed}`. All service methods return this.

**DI Graph:**
```
ActiveWindowManagerInterface
    ↑
WarrantManagerInterface (depends on AWM)
    ↑
OfficerManagerInterface (depends on AWM + WM)
```

#### Key Model Relationships

**Core Tables (all extend `BaseTable`):**
- `Members` — central identity. BelongsTo `Branches`, `Parents` (self-ref). HasMany `MemberRoles`, `CurrentMemberRoles`/`UpcomingMemberRoles`/`PreviousMemberRoles` (temporal finders). BelongsToMany `Roles` through `MemberRoles`. HasMany `GatheringAttendances`.
- `Branches` — organizational structure. Uses `Tree` behavior for hierarchy. Has `links` JSON column.
- `MemberRoles` — time-bounded role assignments. Uses `ActiveWindow` behavior. Links `Members` to `Roles` with temporal context.
- `Warrants` — time-bounded authorization records. Uses `ActiveWindow` behavior. Lifecycle: Pending→Current→Expired/Deactivated/Cancelled/Declined/Replaced.
- `WarrantRosters` — batch containers for warrant approval. Multi-level approval with configurable `approvals_required`.
- `WarrantRosterApprovals` — individual approval records within rosters.
- `WarrantPeriods` — define valid warrant date ranges.
- `Roles` — permission groups. BelongsToMany `Permissions`.
- `Permissions` — individual permission definitions with scoping rules (Global/Branch Only/Branch+Children). HasMany `PermissionPolicies`.
- `PermissionPolicies` — map permissions to policy class+method combinations.
- `Notes` — polymorphic notes via `entity_type`/`entity_id`.
- `AppSettings` — key-value configuration store. Accessed via `StaticHelpers::getAppSetting()/setAppSetting()`.
- `Gatherings`, `GatheringTypes`, `GatheringActivities`, `GatheringAttendances` — event management.
- `ServicePrincipals`, `ServicePrincipalTokens`, `ServicePrincipalRoles` — API authentication (Bearer token).
- `ImpersonationSessionLogs`, `ImpersonationActionLogs` — audit trail for impersonation.

**Entity Hierarchy:**
```
Entity (CakePHP)
  └─ BaseEntity (adds getBranchId() for authorization)
       └─ ActiveWindowBaseEntity (adds start/expire lifecycle, status constants)
            ├─ Warrant
            ├─ MemberRole
            └─ (plugin entities: Officer, Authorization)
```

**Plugin Tables (also extend `BaseTable`):**
- Officers: `Departments`, `Offices`, `Officers` (ActiveWindow)
- Activities: `Authorizations` (ActiveWindow), `AuthorizationApprovals`
- Awards: `Awards` (JSON `specialties`), `Recommendations` (Sortable behavior)

#### Behaviors

- `ActiveWindowBehavior` — adds `findCurrent()`, `findUpcoming()`, `findPrevious()` temporal finders to tables.
- `JsonFieldBehavior` — adds `addJsonWhere()` for querying JSON columns via `JSON_EXTRACT`.
- `PublicIdBehavior` — generates non-sequential 8-char Base62 public IDs. Prevents ID enumeration. Auto-generates on save.
- `SortableBehavior` — position-based list ordering with group support. Used by Awards `Recommendations`.

#### Workflow Engine (Warrant System)

There is no generic "workflow engine" class. The warrant system IS the workflow engine, implemented in `DefaultWarrantManager`:

1. **Request Phase:** `WarrantManager::request()` creates a `WarrantRoster` (batch) with status=Pending, containing individual `Warrant` entities (status=Pending). Wraps in manual transaction.
2. **Approval Phase:** `WarrantManager::approve()` records `WarrantRosterApproval`, increments `approval_count`. When `approval_count >= approvals_required`, roster status→Approved, all pending warrants→Current. Sends email notifications via `QueuedMailerAwareTrait`.
3. **Decline Phase:** `WarrantManager::decline()` sets all pending warrants to Cancelled, roster to Declined. Adds a Note.
4. **Cancel Phase:** `WarrantManager::cancel()` / `cancelByEntity()` terminates individual warrants.
5. **Single Decline:** `declineSingleWarrant()` declines one warrant within a roster. If no pending warrants remain, auto-declines the entire roster.

**Warrant States:** Pending → Current → Expired/Deactivated/Cancelled/Declined/Replaced (defined as constants on `Warrant` and `ActiveWindowBaseEntity`).

**ActiveWindowManager** is the lower-level engine that handles start/stop of any date-bounded entity:
- `start()` — activates entity, optionally closes existing overlapping windows, optionally grants a role (creates `MemberRole`).
- `stop()` — terminates entity window, revokes granted roles.
- **Critical:** Callers MUST wrap calls in their own database transaction. AWM does NOT manage transactions internally.

#### Controller Patterns

- All controllers extend `AppController` → `Controller`.
- `AppController::beforeFilter()` handles: CSV detection, plugin validation, navigation history (page stack), view cell loading from `ViewCellRegistry`, Turbo Frame detection, current user + impersonation state setup.
- `AppController::initialize()` loads Authentication, Authorization, Flash components.
- Controllers use `$this->Authorization->authorizeModel('action1', 'action2')` for table-level auth.
- Controllers use `$this->Authentication->allowUnauthenticated([...])` for public endpoints.
- `DataverseGridTrait` — used for server-side filtered/sorted/paginated data grids. Controllers define grid config with `gridKey`, `gridColumnsClass`, `baseQuery`, etc.
- DI injection via `public static array $inject = [ServiceClass::class]` — CakePHP auto-injects into controller constructor.
- Manual transaction management in controllers: `$this->Table->getConnection()->begin()` / `commit()` / `rollback()`.

#### Database Patterns

**JSON Columns (declared via `getSchema()->setColumnType('field', 'json')`):**
- `members.additional_info` — extensible member metadata
- `branches.links` — branch URL links
- `email_templates.available_vars` — template variable definitions
- `awards.specialties` (Awards plugin) — award specialty types
- `queued_jobs.data` (Queue plugin) — serialized job payload

**MariaDB-Specific:**
- `JsonFieldBehavior` uses `JSON_EXTRACT()` for querying JSON columns.
- `BranchesTable` uses CakePHP `Tree` behavior (nested set / materialized path) for hierarchy.
- Manual transactions throughout (no CakePHP `Connection::transactional()` wrapper used — all manual begin/commit/rollback).

**Migrations:** 45 migrations in `app/config/Migrations/`. Plugins have their own migration dirs. Migration order controlled by `migrationOrder` in `config/plugins.php`.

**AppSettings:** Single-record key-value store. Version-based config updates in `Application::bootstrap()` — when `KMP.configVersion` changes, new defaults are applied. Uses `yaml` format for complex values (e.g., branch types).

#### Queue System

Uses the `Queue` plugin (fork/adaptation of dereuromark/cakephp-queue):
- **Queue Tasks:** `MailerTask` (main workhorse — sends emails async), `EmailTask`, `ExecuteTask`, plus example tasks.
- **Dispatching:** Via `QueuedMailerAwareTrait::queueMail($mailerClass, $action, $to, $vars)` — creates a `QueuedJob` with class/action/vars data, processed by `MailerTask`.
- **Worker:** CLI command `queue worker` / `queue run` processes jobs.
- **Job Storage:** `QueuedJobs` table with JSON `data` column for serialized payload.
- **Transport:** Custom mailer transport in `Queue\Mailer\Transport\`.

#### Authorization Flow

1. **Authentication:** Session-based (web) or Bearer token (API via `ServicePrincipal`). Form login uses `KMPBruteForcePassword` identifier with bcrypt + MD5 fallback.
2. **Authorization Middleware:** Requires authorization check on every request. Uses `ApiAwareRedirect` handler.
3. **Policy Resolution:** `ResolverCollection` tries `OrmResolver` first (entity policies), then `ControllerResolver` (controller policies).
4. **BasePolicy Pattern:** All policies extend `BasePolicy` which implements `BeforePolicyInterface`:
   - `before()` — super users auto-pass.
   - `_hasPolicy()` — checks user's loaded policies (from `PermissionsLoader`) against policy class + method. Handles branch scoping (Global/Branch Only/Branch+Children).
   - `_getBranchIdsForPolicy()` — returns authorized branch IDs for data scoping.
   - `scopeIndex()` — applies branch-based data filtering to queries.
5. **PermissionsLoader:** Loads and caches (`member_permissions{id}`) all permissions for a member by joining Members→MemberRoles→Roles→Permissions→PermissionPolicies. Validates temporal bounds (active warrants, non-expired roles). Merges branch scoping across multiple role assignments.
6. **Permission Scoping Rules:** `SCOPE_GLOBAL` (no branch filter), `SCOPE_BRANCH_ONLY` (specific branch), `SCOPE_BRANCH_AND_CHILDREN` (branch + descendants).
7. **Member Entity:** Implements `KmpIdentityInterface` which provides `can()`, `checkCan()`, `isSuperUser()`, `getPermissions()`, `getPolicies()`.
8. **ControllerActionHookPolicy:** Catch-all policy that allows all actions (used for public/non-restricted controllers).

#### Transaction Management Gotchas

- **ActiveWindowManager does NOT manage transactions** — callers must wrap in begin/commit/rollback. This is documented in the interface comments.
- **WarrantManager manages its own transactions** — uses manual begin/commit/rollback around warrant operations.
- **OfficerManager** likely manages its own transactions (delegates to AWM within its own transaction boundary).
- **Pattern:** `$table->getConnection()->begin()` → operations → `$table->getConnection()->commit()` on success, `rollback()` on any failure. No use of CakePHP's `transactional()` closure wrapper.
- **Mixing transaction managers:** If you call WarrantManager (which manages its own transactions) from within a controller transaction, you get nested transactions. Be careful.
- **Dead code in `cancel()`:** `DefaultWarrantManager::cancel()` has an unreachable `return new ServiceResult(true)` after the `return $this->cancelWarrant(...)` call (line 291).

#### Key Gotchas for New Developers

1. **Plugin enable/disable:** Controlled by `AppSetting` keys like `Plugin.Officers.Active`. Checked in `AppController::beforeFilter()` via `StaticHelpers::pluginEnabled()`.
2. **Navigation cache:** Session-cached. Changes to navigation won't appear until session regenerates.
3. **Permission cache:** Cached with key `member_permissions{id}`. Must be cleared when roles/permissions change.
4. **`BaseTable` auto-logs impersonation** — every save/delete during impersonation writes to `ImpersonationActionLogs`.
5. **`BaseTable::CACHES_TO_CLEAR`** — tables can define cache keys to auto-clear on save.
6. **Member entity uses `LazyLoadEntityTrait`** — related data loads on access, which can cause N+1 queries.
7. **`termYears` parameter is actually months** — `ActiveWindowBaseEntity::start()` calls `$startOn->addMonths($termYears)`. Misleading parameter name.
8. **Warrantable check:** `DefaultWarrantManager::request()` validates `$member->warrantable` before creating warrants. Also checks membership expiry vs warrant period.
9. **Email via queue:** Always use `QueuedMailerAwareTrait::queueMail()` for sending emails — never send synchronously. Goes through Queue plugin's `MailerTask`.
10. **TimezoneHelper for dates in emails:** All dates in email vars must be pre-formatted via `TimezoneHelper::formatDate()` before passing to mailers.

#### Key File Paths

- **Application bootstrap/DI:** `app/src/Application.php`
- **Base controller:** `app/src/Controller/AppController.php`
- **DataverseGrid trait:** `app/src/Controller/DataverseGridTrait.php`
- **Base table:** `app/src/Model/Table/BaseTable.php`
- **Base entity:** `app/src/Model/Entity/BaseEntity.php`
- **ActiveWindow entity:** `app/src/Model/Entity/ActiveWindowBaseEntity.php`
- **Base policy:** `app/src/Policy/BasePolicy.php`
- **Controller resolver:** `app/src/Policy/ControllerResolver.php`
- **Permissions loader:** `app/src/KMP/PermissionsLoader.php`
- **Identity interface:** `app/src/KMP/KmpIdentityInterface.php`
- **Static helpers:** `app/src/KMP/StaticHelpers.php`
- **Timezone helper:** `app/src/KMP/TimezoneHelper.php`
- **Service result:** `app/src/Services/ServiceResult.php`
- **Warrant manager:** `app/src/Services/WarrantManager/`
- **ActiveWindow manager:** `app/src/Services/ActiveWindowManager/`
- **Navigation registry:** `app/src/Services/NavigationRegistry.php`
- **ViewCell registry:** `app/src/Services/ViewCellRegistry.php`
- **Queue mailer trait:** `app/src/Mailer/QueuedMailerAwareTrait.php`
- **Plugin config:** `app/config/plugins.php`
- **Migrations:** `app/config/Migrations/`
- **Officers plugin services:** `app/plugins/Officers/src/Services/`
- **Activities plugin services:** `app/plugins/Activities/src/Services/`

📌 Team update (2026-02-10): Architecture overview documented — 6 plugins, service layer map, auth chain, 8 dangerous-to-change areas identified — decided by Mal
📌 Team update (2026-02-10): Frontend patterns documented — 81 Stimulus controllers cataloged, window.Controllers registration pattern, Turbo Drive disabled, plugin CSS must be manually added to webpack.mix.js — decided by Wash
📌 Team update (2026-02-10): Test suite audited — 20/26 controllers untested, 26/32 tables untested, 0 mailer tests, seed-based DB approach with transaction isolation — decided by Jayne
