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

### 2026-02-12: AddHamletFieldsToBranches Migration

Created `app/config/Migrations/20260212180000_AddHamletFieldsToBranches.php` — adds `can_have_officers` (boolean, default true, NOT NULL) and `contact_id` (integer, nullable, FK → members.id SET NULL on delete) to the `branches` table.

#### Migration Pattern Details
- All migrations extend `Migrations\AbstractMigration` with `declare(strict_types=1)`.
- Use `up()`/`down()` pair (not `change()`) for explicit reversibility; the most recent project migrations use this pattern.
- Guard with `hasColumn()`/`hasForeignKey()` checks for idempotency.
- Foreign keys added via `addForeignKey()` after `update()` so the column exists first.
- FK constraint naming convention: `fk_{table}_{descriptive_suffix}` (e.g., `fk_branches_contact_member`).
- Timestamp-based filenames: `YYYYMMDDHHMMSS_ClassName.php`.

#### Column Naming Conventions
- Boolean capability flags use `can_have_*` prefix (e.g., `can_have_members`, `can_have_officers`).
- Foreign key columns use `{referenced_entity}_id` suffix (e.g., `contact_id`, `branch_id`, `parent_id`).
- Boolean columns: `"boolean"` type with explicit `"default"`, `"null" => false`, `"limit" => null`.
- Integer FK columns: `"integer"` type with `"limit" => 11`, `"signed" => true`, `"null" => true` for optional refs.

### 2026-02-12: Badge Count Bug Fix — countGatheringsNeedingWaivers()

#### What Changed
Fixed two bugs in `GatheringWaiversTable::countGatheringsNeedingWaivers()` that caused the "Gatherings Needing Waivers" badge count to not match the list view.

**Bug 1 — Permission action mismatch:** Changed `getBranchIdsForAction('needingWaivers', ...)` to `getBranchIdsForAction('uploadWaivers', ...)` to match the list view controller (line 236). The badge was checking a different permission than the page it linked to.

**Bug 2 — Date filtering inverted:** The badge was counting ongoing/future gatherings (`end_date >= today`). Josh wanted it to count ONLY past gatherings where the event has ended but waivers haven't been uploaded. Replaced with `end_date < today` (or `end_date IS NULL AND start_date < today` for single-day events). Removed the `$oneWeekFromNow` variable since future-looking logic is no longer needed. The list view is intentionally broader (shows upcoming + past) — the badge is a subset: past-only, needing action.

#### Key File Paths
- `app/plugins/Waivers/src/Model/Table/GatheringWaiversTable.php` — `countGatheringsNeedingWaivers()` static method (badge count)
- `app/plugins/Waivers/src/Controller/GatheringWaiversController.php` — `needingWaivers()` action (list view, line ~1784) — NOT modified

📌 Team update (2026-02-12): Badge count query in GatheringWaiversTable changed to past-only gatherings + aligned permission to uploadWaivers — decided by Kaylee

### 2026-02-22: Runtime startup hardening (Redis/MySQL/Apache)

- Setup and migration commands should force `CACHE_ENGINE=apcu` (or non-Redis) to avoid RedisEngine initialization failures during bootstrap when Redis is not ready.
- In container startup scripts, prefer explicit `MYSQL_HOST`/`MYSQL_PORT`/`MYSQL_USERNAME` vars over parsing `DATABASE_URL` when available, especially on Railway-managed databases.
- Production image should explicitly disable extra Apache MPMs (`mpm_event`, `mpm_worker`) and ensure `mpm_prefork` is enabled to avoid “More than one MPM loaded”.

### 2026-02-22: Railway startup hardening for installer migrations

- Railway migration flows should perform an SSH readiness pre-check loop before running `bin/cake migrations`, because Railway services may be asleep or still starting immediately after deploy.
- Keep migration failures explicit: if SSH never becomes reachable after bounded retries, return a direct readiness error instead of only per-command migration failures.
- Runtime entrypoint should re-assert Apache MPM state (`disable mpm_event/mpm_worker`, `enable mpm_prefork`) at startup, not only at image build time, to prevent drift-related boot failures.

📌 Team update (2026-02-22): Railway startup hardening decisions from inbox were merged into a single consolidated entry in `.ai-team/decisions.md`; inbox cleared. — archived by Scribe

### 2026-02-22: Railway 502 readiness root cause pattern

- KMP production startup must map Apache's listen port to the runtime `PORT` env var (not hardcode port 80), otherwise Railway can keep the container running but return edge 502 due to upstream port mismatch.
- `docker/entrypoint.prod.sh` is the safest place to enforce this at boot by rewriting `ports.conf` and `000-default.conf` before launching `apache2-foreground`.

### 2026-02-22: Railway managed MySQL URL precedence and blank-page risk

- In production `app_local.php` generation, when `MYSQL_HOST` + `MYSQL_USERNAME` are present, datasource `url` should be `null` so Cake uses explicit MySQL env fields instead of reparsing `DATABASE_URL`.
- Railway-managed credentials can contain URL-special characters; when injected into a raw `mysql://user:pass@host/db` string, malformed DSNs can break dynamic Cake requests while Apache still serves static assets.
