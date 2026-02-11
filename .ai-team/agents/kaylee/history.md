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

### 2026-02-10: Queue Plugin Review & Fixes (summarized)

**Review:** Forked from `dereuromark/cakephp-queue` v8, now KMP-owned. Job lifecycle: `createJob()` → `requestJob()` (row locking) → `runJob()` → done/failed. Email async via `MailerTask` + `QueuedMailerAwareTrait::queueMail()`. MariaDB `text` + `setColumnType('json')` is correct.

**22 issues found, 18 fixed:** P0 — deleted ExecuteTask.php (arbitrary exec), fixed command injection in `terminateProcess()`, hardened open redirect. P1 — fixed `cleanOldJobs()` timestamp, `getFailedStatus()` prefix, `configVersion` persistence, auth context, silent save failures, deprecated APIs. P2 — worker key entropy, pcntl_async_signals, removed broken `clearDoublettes()`, added `getBranchId()` to entities. All core tests pass.

### 2026-02-10: Documentation Modernization (summarized)

Fixed 13 doc inaccuracies: DI container (phantom services removed), session config (timeout 30 not 240, `ini` block not nested `cookie`), PermissionsLoader (`scoping_rule`/`SCOPE_*` constants), entity names (`Authorization` not `ActivityAuthorization`), WarrantManager (no events dispatched), warrant expiry (`SyncActiveWindowStatusesCommand` not `expireOldWarrants()`), file paths (`Services/` plural). Created `docs/7.7-console-commands.md`, expanded `docs/6-services.md` and `docs/3.2-model-behaviors.md`. Pattern: AI-generated docs assumed patterns rather than reading source.

### 2026-02-10: Email Template Conditional Processing (superseded — see below)

_Original PHP-style syntax replaced with `{{#if}}` mustache-like syntax. See next entry._

### 2026-02-10: Email Template {{#if}} Conditional Syntax

#### Architecture Decision
Replaced the PHP-style conditional syntax (`<?php if ($var == "value") : ?>`) with a clean `{{#if}}` mustache-like DSL. The PHP-style syntax was confusing because it looked like real PHP but was never executed as PHP. The new syntax is unambiguous and admin-friendly.

#### Syntax
- `{{#if varName == "value"}}...{{/if}}` — equality check
- `{{#if varName != "value"}}...{{/if}}` — not-equal check
- `{{#if a == "x" || b == "y"}}...{{/if}}` — OR conditions
- `{{#if a == "x" && b == "y"}}...{{/if}}` — AND conditions

#### Implementation Pattern
- `processConditionals()` regex: `/\{\{#if\s+(.+?)\}\}(.*?)\{\{\/if\}\}/s` — runs BEFORE `{{variable}}` substitution
- `evaluateCondition()` unchanged — still splits `||` then `&&` with correct precedence
- `evaluateComparison()` regex: `/^\$?(\w+)\s*(==|!=)\s*["\']([^"\']*)["\']$/` — no `$` prefix required, supports `!=`, optional `$` for backward compat
- `extractVariables()` excludes `{{#if ...}}` and `{{/if}}` control tags from variable list, finds var names from conditions via `\b(\w+)\s*(?:==|!=)`
- `convertTemplateVariables()` in `EmailTemplatesController` now also converts PHP conditionals to `{{#if}}` syntax during file template import

#### Key File Paths
- `app/src/Services/EmailTemplateRendererService.php` — core template renderer, owns conditional processing
- `app/src/Controller/EmailTemplatesController.php` — `convertTemplateVariables()` converts PHP file template syntax to `{{#if}}` on import
- `app/src/Mailer/TemplateAwareMailerTrait.php` — bridge that chooses DB vs file templates; calls renderer
- `app/plugins/Activities/templates/email/text/notify_requester.php` — canonical file-based template (still uses PHP syntax — gets converted on import)

#### Security Constraint
The renderer NEVER executes PHP from DB-stored templates. The `{{#if}}` syntax is regex-parsed as a pattern language. Unsupported expressions log a warning and evaluate to false. This is critical — DB content is admin-editable and must never be `eval()`'d.
