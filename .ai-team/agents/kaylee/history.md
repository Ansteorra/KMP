# Project Context

- **Owner:** Josh Handel (josh@liveoak.ws)
- **Project:** KMP — Membership management system for SCA Kingdoms. Handles members, officers, warrants, awards, activities, and workflow-driven approvals. ~2 years of active development.
- **Stack:** CakePHP 5.x, Stimulus.JS, MariaDB, Docker, Laravel Mix, Bootstrap, plugin architecture
- **Created:** 2026-02-10

## Learnings

<!-- Append new learnings below. Each entry is something lasting about the project. -->

### 2026-02-10: Backend Architecture (summarized from deep dive)

#### Service Layer
**DI-registered (Application::services()):** ActiveWindowManagerInterface→DefaultActiveWindowManager (no txn mgmt), WarrantManagerInterface→DefaultWarrantManager (owns txns, depends on AWM), CsvExportService, ICalendarService, ImpersonationService.

**Plugin DI:** OfficerManagerInterface→DefaultOfficerManager (Officers, depends on AWM+WM), AuthorizationManagerInterface→DefaultAuthorizationManager (Activities).

**Static registries:** NavigationRegistry (session-cached), ViewCellRegistry (route-matched), ApiDataRegistry.

**DI graph:** AWM ← WM ← OfficerManager

**ServiceResult pattern:** All service methods return `ServiceResult(success, reason, data)`. Never throw from services.

#### Key Model Patterns
- Entities extend `BaseEntity` (provides `getBranchId()`) or `ActiveWindowBaseEntity` (time-bounded)
- Tables extend `BaseTable` (cache invalidation, impersonation audit). Never extend `Table` directly.
- 34 entity classes, 33 table classes, 45 core migrations
- Behaviors: ActiveWindowBehavior (temporal), JsonFieldBehavior (JSON queries), PublicIdBehavior (anti-enumeration), SortableBehavior
- JSON columns: declare via `getSchema()->setColumnType()`, query via JsonFieldBehavior
- AppSettings: `StaticHelpers::getAppSetting(key, default, type, createIfMissing)`

#### Authorization Flow
Permission chain: Members → MemberRoles (temporal) → Roles → Permissions → PermissionPolicies → Policy classes. Three scopes: Global, Branch Only, Branch+Children. Cached via PermissionsLoader (`member_permissions{id}`). All policies extend BasePolicy (super-user bypass in `before()`).

#### Transaction Management
- **AWM:** Callers MUST wrap in own transaction. Uses `$table->getConnection()->begin()/commit()/rollback()`.
- **WarrantManager:** Manages own transactions. Do NOT wrap calls.
- **termYears parameter is actually MONTHS** (misleading name).

#### Key Gotchas
1. Plugin enable/disable via `AppSetting` `Plugin.{Name}.Active`
2. Navigation/permission caches must be cleared on changes
3. `BaseTable` auto-logs impersonation on save/delete
4. Member entity uses `LazyLoadEntityTrait` — beware N+1 queries
5. Email: always use `QueuedMailerAwareTrait::queueMail()`, format dates with `TimezoneHelper::formatDate()` first

#### Key File Paths
- Application: `app/src/Application.php`
- Base table/entity: `app/src/Model/Table/BaseTable.php`, `app/src/Model/Entity/BaseEntity.php`
- Permissions: `app/src/KMP/PermissionsLoader.php`
- Services: `app/src/Services/` (ActiveWindowManager/, WarrantManager/, ViewCellRegistry, NavigationRegistry)
- Policies: `app/src/Policy/` (37 files, BasePolicy)
- Config: `app/config/` (.env, plugins.php, routes.php)
- Plugins: `app/plugins/` (Activities, Awards, Officers, Waivers, Queue, GitHubIssueSubmitter, Bootstrap, Template)

📌 Team update (2026-02-10): Architecture overview documented — 6 plugins, service layer map, auth chain, 8 dangerous-to-change areas identified — decided by Mal
📌 Team update (2026-02-10): Frontend patterns documented — 81 Stimulus controllers cataloged, window.Controllers registration pattern, Turbo Drive disabled, plugin CSS must be manually added to webpack.mix.js — decided by Wash
📌 Team update (2026-02-10): Test suite audited — 20/26 controllers untested, 26/32 tables untested, 0 mailer tests, seed-based DB approach with transaction isolation — decided by Jayne
📌 Team update (2026-02-10): Test infrastructure attack plan created — Kaylee owns Phase 4.2b (fix production auth code bugs found by Jayne). No new features until testing is solid. — decided by Mal, Josh Handel
📌 Team update (2026-02-10): Auth triage complete — Kaylee's 2 CODE_BUG fixes (PermissionsLoader revoker_id filter, ControllerResolver string resource handling) verified. All 370 project-owned tests pass. — decided by Jayne, Kaylee
📌 Team update (2026-02-10): Auth strategy decided — standardize on TestAuthenticationHelper, deprecate old traits. ⚠️ Gap: authenticateAsSuperUser() does not set permissions — needs fix (Option 3: load real member entity in test transaction recommended). — decided by Mal

### 2026-02-10: Queue Plugin Deep Code Review

#### Plugin Origin & Status
Queue plugin is a fork of `dereuromark/cakephp-queue` v8 (MIT license). KMP team has partially adapted it: entities extend BaseEntity, tables extend BaseTable, controllers use AppController with authorization, Plugin class implements KMPPluginInterface. Now KMP-owned.

#### Key Architecture
- **Job lifecycle:** `createJob()` → `requestJob()` (FOR UPDATE row locking in transaction) → `runJob()` → `markJobDone()`/`markJobFailed()`
- **Task discovery:** `TaskFinder` scans `Queue/Task/` dirs in app and all plugins, builds `[name => className]` map
- **Worker process:** `Processor::run()` loops, fetching jobs via `requestJob()`, with PCNTL signal handling for graceful shutdown
- **Email integration:** KMP uses `MailerTask` via `QueuedMailerAwareTrait::queueMail()` — all email is async through Queue
- **DI support:** Tasks can use `ServicesTrait` to access the DI container for service injection

#### Critical Findings (22 issues total)
- **P0 (2):** Command injection in `terminateProcess()` (unsanitized PID to exec()), open redirect in `refererRedirect()`
- **P1 (10):** Broken `getFailedStatus()` (wrong task name prefix), `cleanOldJobs()` passes unix timestamp instead of DateTime, missing index on `queued_jobs.workerkey`, deprecated `TableRegistry` in migration, deprecated `loadComponent()`, silent save failures in markJobDone/markJobFailed, wrong auth context in QueueProcessesController, missing Shim dependency for JsonableBehavior, configVersion never written back
- **P2 (10):** Various cleanup — broken `clearDoublettes()`, missing strict_types in 2 files, weak worker key entropy, declare(ticks=1) should be pcntl_async_signals, missing docblocks, copy-paste policy docblock

#### MariaDB/JSON Pattern
The `text` column + `setColumnType('json')` in `initialize()` is correct for MariaDB. No changes needed — CakePHP ORM handles serialization transparently.

#### Dead Code Candidates
- `clearDoublettes()` — broken, never called in KMP
- 8 example task files — upstream examples, not used in production
- `EmailTask` — KMP uses `MailerTask` instead
- `SimpleQueueTransport` — appears unused

📌 Team update (2026-02-10): Queue plugin code review complete — 22 issues found (2 P0 security, 10 P1, 10 P2). Full findings in decisions/inbox/kaylee-queue-code-review.md. Key: command injection in terminateProcess(), broken getFailedStatus(), cleanOldJobs timestamp bug. — decided by Kaylee

📌 Team update (2026-02-10): Queue plugin ownership review — decided to own the plugin, security issues found, test triage complete

### 2026-02-10: Queue Plugin Security & Code Quality Fixes

Fixed 18 issues across Queue plugin production code:
- **P0 Security:** Deleted ExecuteTask.php (arbitrary exec), deleted 8 example tasks (demo code in prod), fixed command injection in terminateProcess() (numeric validation + int cast), hardened open redirect in refererRedirect() (backslash check)
- **P1 Code:** Fixed cleanOldJobs() timestamp (DateTime instead of time()), fixed getFailedStatus() wrong prefix, fixed configVersion not persisted, fixed QueueProcessesController auth context ("migrate"→"index"), added logging for markJobDone/Failed silent failures, added class_exists guard for Shim in JsonableBehavior, replaced deprecated loadComponent(), replaced deprecated TableRegistry in migration with raw SQL
- **P2 Quick Wins:** Fixed policy docblock, added canReset() docblock, added explicit getBranchId() to Queue entities, improved worker key entropy (random_bytes), replaced declare(ticks=1) with pcntl_async_signals, removed broken clearDoublettes()
- All core tests pass (183 unit, 99 feature)

📌 Team update (2026-02-10): Documentation accuracy review completed — all 4 agents reviewed 96 docs against codebase

### 2026-02-10: Documentation Modernization — Backend Docs Fixed

Completed 13 documentation tasks fixing inaccuracies found during codebase review:

#### Key Corrections Made
- **DI Container:** Removed phantom `NavigationRegistry` and `KmpAuthorizationService` from services() doc; added actual registrations (ICalendarService, ImpersonationService)
- **Session Config:** Fixed timeout (30 not 240), cookie name (PHPSESSID not KMP_SESSION), and structure (uses `ini` block, not nested `cookie` object)
- **PermissionsLoader:** Fixed property name (`scoping_rule` not `scope`) and values (`Permission::SCOPE_*` constants not lowercase strings)
- **findUpcoming SQL:** Fixed top-level OR → AND to match actual CakePHP query builder behavior
- **Entity hierarchy:** Fixed `ActivityAuthorization` → `Authorization` (Activities plugin entity name), added `Warrant` entity
- **WarrantManager events:** Removed fictional `ActiveWindow.before/afterStart/Stop` events — no events are dispatched
- **Warrant expiry:** Replaced reference to non-existent `expireOldWarrants()` with actual `SyncActiveWindowStatusesCommand`
- **File paths:** Fixed `src/Service/` → `src/Services/` (plural) in email template docs
- **Plugin listing:** Added Waivers plugin to architecture docs
- **Branch schema:** Removed non-existent `deleted_date` column
- **Migration scoping:** Fixed colon-delimited scope values to `Permission::SCOPE_*` constants

#### New Documentation Created
- `docs/7.7-console-commands.md` — Documented 5 CLI commands (generate_public_ids, migrate_award_events, reset_database, sync_member_warrantable_statuses, update_database)
- Added PublicIdBehavior section to `docs/3.2-model-behaviors.md`
- Added 11 service descriptions to `docs/6-services.md`
- Added `warrant_rosters` and `warrant_roster_approvals` tables to `docs/3.3-database-schema.md`

#### Pattern Observed
Docs were consistently wrong about: DI registrations (showing services that aren't registered), session config structure (CakePHP uses flat `ini` block not nested objects), and event names (fictional ActiveWindow events). These likely came from AI-generated docs that assumed patterns rather than reading actual source.
