# Project Context

- **Owner:** Josh Handel (josh@liveoak.ws)
- **Project:** KMP — Membership management system for SCA Kingdoms. Handles members, officers, warrants, awards, activities, and workflow-driven approvals. ~2 years of active development.
- **Stack:** CakePHP 5.x, Stimulus.JS, MariaDB, Docker, Laravel Mix, Bootstrap, plugin architecture
- **Created:** 2026-02-10

## Learnings

<!-- Append new learnings below. Each entry is something lasting about the project. -->

### 2026-02-10: Full Architecture Map

#### Overall Structure
KMP is a CakePHP 5.x application with a plugin-based architecture. The app lives in `/app/` with Docker orchestration at the repo root. Three services: PHP/Apache app, MariaDB 11, Mailpit (dev email). Frontend is Stimulus.JS + Turbo Frames + Bootstrap 5, compiled via Laravel Mix (webpack.mix.js inside `/app/`).

#### Application Entry Point
`app/src/Application.php` is the orchestrator. It:
- Configures 9-layer middleware stack (error → security headers → assets → routing → body parser → CSRF → auth → authz → footprint)
- Registers DI services: `ActiveWindowManagerInterface`, `WarrantManagerInterface`, `CsvExportService`, `ICalendarService`, `ImpersonationService`
- Sets up dual authentication: session-based for web, Bearer token for API (`/api/v1/`)
- Configures authorization with ORM + Controller policy resolvers
- Manages version-gated AppSettings (auto-applied on version change)
- Registers core navigation and view cells via static registries

#### Plugin Ecosystem (6 active, 2 inactive)

**Active domain plugins:**
1. **Activities** (`migrationOrder: 1`) — Activity types, activity groups, member authorizations (like martial arts authorizations), authorization approvals. Has API endpoints (implements `KMPApiPluginInterface`). Services: `AuthorizationManagerInterface`/`DefaultAuthorizationManager`.
2. **Officers** (`migrationOrder: 2`) — Officer positions, departments, offices, warrant rosters. Has API endpoints (implements `KMPApiPluginInterface`). Services: `OfficerManagerInterface`/`DefaultOfficerManager`, plus read-only API services. Uses `ApiDataRegistry` to inject officer data into branch API responses.
3. **Awards** (`migrationOrder: 3`) — Award domains, levels, recommendations with state machine workflow (`RecommendationsStatesLog`). Append-only state transitions for audit trail. Services: `AwardsNavigationProvider`, `AwardsViewCellProvider`.
4. **Waivers** (`migrationOrder: 4`) — Waiver types, gathering waivers, gathering-activity waivers. Services: `GatheringActivityService`. Has mailer for waiver notifications.

**Infrastructure plugins:**
5. **Queue** — Job queue system for async operations (email, background tasks). 23 task types configured. Worker runs via cron (`cronScripts/runCakeCommand.sh`). Single worker, 1800s timeout.
6. **GitHubIssueSubmitter** — In-app bug reporting that creates GitHub issues.

**Inactive (commented out in plugins.php):**
7. **Template** — Reference implementation for plugin development.
8. **Events** — Not yet implemented.

**Third-party plugins (via Composer):**
- `DebugKit`, `Bake`, `Tools`, `Migrations`, `Muffin/Footprint` (audit), `Muffin/Trash` (soft delete), `BootstrapUI`, `Bootstrap`, `AssetMix`, `Authentication`, `Authorization`, `ADmad/Glide` (image processing), `CsvView`

#### Plugin Registration Pattern
1. Listed in `app/config/plugins.php` with optional `migrationOrder`
2. Plugin class extends `BasePlugin` (or implements `KMPPluginInterface` for migration ordering)
3. In `bootstrap()`: registers navigation via `NavigationRegistry::register()`, view cells via `ViewCellRegistry::register()`, version-gated AppSettings via `StaticHelpers`
4. Plugins can register DI services in their own `services()` method
5. API plugins implement `KMPApiPluginInterface::registerApiRoutes()` — called in routes.php loop
6. Plugin enable/disable via `Plugin.{Name}.Active` AppSetting

#### Key Services and Relationships

**Core Services (DI-registered in Application::services()):**
- `ActiveWindowManagerInterface` → `DefaultActiveWindowManager` — Manages time-bounded entities (start/expire dates). Does NOT own transactions.
- `WarrantManagerInterface` → `DefaultWarrantManager` — Warrant lifecycle (submit, approve, cancel, expire). Depends on ActiveWindowManager. Manages own transactions.
- `CsvExportService` — Data export to CSV
- `ICalendarService` — iCal calendar generation
- `ImpersonationService` — Super-user impersonation with audit logging

**Singleton Registries (static, not DI):**
- `NavigationRegistry` — Menu items from core + plugins, session-cached
- `ViewCellRegistry` — Tab/detail/modal/JSON cells from core + plugins, route-matched
- `ApiDataRegistry` — API response data providers from plugins

**Other Services (instantiated directly):**
- `DocumentService` — File storage (local or Azure Blob via Flysystem)
- `GridViewService` — Saved grid view configs with user/system defaults
- `EmailTemplateRendererService` — Variable substitution in email templates
- `RetentionPolicyService` — Document expiration calculation
- `MailerDiscoveryService` — Reflection-based mailer class discovery
- `AuthorizationService` — Extended CakePHP authz with debug logging
- `PdfProcessingService` / `ImageToPdfConversionService` — PDF generation
- `OpenApiMergeService` — Merges core + plugin OpenAPI specs

**ServiceResult Pattern:** All service methods return `ServiceResult(success, reason, data)`. Never throw from services.

#### RBAC & Authorization Architecture

**Permission chain:** Members → MemberRoles (temporal, warrant-gated) → Roles → Permissions → PermissionPolicies → Policy classes

**Three scoping levels:** Global, Branch Only, Branch+Children

**Key classes:**
- `PermissionsLoader` — Core RBAC engine with multi-tier caching
- `BasePolicy` — Super-user bypass in `before()`, delegates to `_hasPolicy()`
- `ControllerResolver` — Fallback authorization for controller actions
- 37 policy files in `app/src/Policy/`

**Authentication:**
- Web: Session + Form (email_address/password), brute-force protection, MD5→bcrypt migration
- API: Bearer token via `ServicePrincipal` authenticator
- Mobile: Token-based for PWA card access
- CSRF skipped for `/api/` routes

#### Database & Model Layer

**45 core migrations**, plugin migrations managed separately per plugin.

**Model hierarchy:**
- Entities extend `BaseEntity` (provides `getBranchId()` for auth) or `ActiveWindowBaseEntity` (for time-bounded)
- Tables extend `BaseTable` (provides cache invalidation, impersonation audit hooks)
- 34 entity classes, 33 table classes

**Custom Behaviors:**
- `ActiveWindowBehavior` — Temporal filtering (findUpcoming, findCurrent, findPrevious)
- `JsonFieldBehavior` — JSON column querying with `$.path` notation
- `PublicIdBehavior` — Base62 random IDs (anti-enumeration)
- `SortableBehavior` — Position-based ordering with group support

**Key conventions:**
- JSON columns: declare via `getSchema()->setColumnType('field', 'json')`, query via `JsonFieldBehavior`
- AppSettings stored in DB, accessed via `StaticHelpers::getAppSetting()`, complex values as YAML
- Soft deletes via `Muffin/Trash`, audit trail via `Muffin/Footprint`

#### Frontend Architecture

**Stack:** Stimulus.JS + Turbo Frames (Drive disabled) + Bootstrap 5.3.6 + Laravel Mix

**60 Stimulus controllers** in `app/assets/js/controllers/` covering:
- Grids (DataverseGrid), forms, modals, autocomplete, kanban, maps
- Mobile/PWA with offline support, service worker, IndexedDB caching
- QR codes, markdown editing, timezone handling

**Build:** `webpack.mix.js` auto-discovers controllers from `assets/js/controllers/` and `plugins/*/assets/js/controllers/`. Outputs `controllers.js`, `core.js` (vendor), CSS files.

**Template system:** 6 layouts (default, ajax, turbo_frame, mobile_app, public_event, error). Block-based (`$this->KMP->startBlock()`/`endBlock()`). Plugin content via ViewCellRegistry.

**Tab ordering:** CSS flexbox with `data-tab-order` + `style="order: N;"`. Guidelines: 1-10 plugins, 10-20 primary, 20-30 secondary, 30+ admin.

#### Configuration Approach
- `app/config/.env` — DB credentials, SMTP, Azure storage, wkhtml path
- `app/config/app.php` / `app_local.php` — CakePHP framework config
- `app/config/app_queue.php` — Queue worker config
- `app/config/plugins.php` — Plugin registration
- Docker env vars override `.env` when `APP_NAME=KMP_DOCKER`
- AppSettings in DB via `StaticHelpers` — version-gated defaults applied in bootstrap

#### CLI Commands (7)
- `AgeUpMembersCommand` — Age-based status transitions
- `SyncActiveWindowStatusesCommand` — Expire time-bounded entities
- `SyncMemberWarrantableStatusesCommand` — Update warrant eligibility
- `GeneratePublicIdsCommand` — Backfill public IDs
- `ResetDatabaseCommand` / `UpdateDatabaseCommand` — Dev utilities
- `MigrateAwardEventsCommand` — Data migration

#### API Structure
- Versioned under `/api/v1/` with `Api/V1` controller prefix
- Core endpoints: Members, Branches, Roles, ServicePrincipals
- Plugin endpoints: Activities and Officers register via `KMPApiPluginInterface`
- OpenAPI spec merged from core + plugins at `/api-docs/openapi.json`
- Bearer token auth, no CSRF

#### Key File Paths
- Application entry: `app/src/Application.php`
- Core KMP utilities: `app/src/KMP/` (StaticHelpers, PermissionsLoader, TimezoneHelper, KmpIdentityInterface)
- Services: `app/src/Services/` (ActiveWindowManager/, WarrantManager/, ViewCellRegistry, NavigationRegistry, etc.)
- Controllers: `app/src/Controller/` (26 controllers + Api/ subdirectory)
- Models: `app/src/Model/` (Entity/, Table/, Behavior/)
- Policies: `app/src/Policy/` (37 policy files)
- Mailers: `app/src/Mailer/` (KMPMailer, QueuedMailerAwareTrait, TemplateAwareMailerTrait)
- Plugins: `app/plugins/` (Activities, Awards, Officers, Waivers, Queue, GitHubIssueSubmitter, Bootstrap, Template)
- Frontend: `app/assets/js/` (controllers/, index.js) + `app/assets/css/`
- Templates: `app/templates/`
- Config: `app/config/` (.env, app.php, plugins.php, routes.php, app_queue.php)
- Tests: `app/tests/TestCase/`
- Docker: `docker-compose.yml`, `docker/Dockerfile.app`
- Docs: `docs/` (extensive — 80+ files)
- Build: `app/webpack.mix.js` (exists inside app/, not repo root)

📌 Team update (2026-02-10): Backend patterns documented — 14 critical conventions including ServiceResult, transaction ownership, entity/table hierarchy, and authorization flow — decided by Kaylee
📌 Team update (2026-02-10): Frontend patterns documented — 81 Stimulus controllers cataloged, asset pipeline, tab ordering, inter-controller communication via outlet-btn — decided by Wash
📌 Team update (2026-02-10): Test suite audited — 88 files but ~15-20% real coverage, 36/37 policies untested, no CI pipeline, recommend adding CI test runner as Priority 1 — decided by Jayne

### 2026-02-10: Test Infrastructure Attack Plan

**Decision:** Josh directed all new feature work paused until testing is solid. Created 6-phase attack plan.

**Key architectural decisions made:**
1. **Auth trait consolidation:** Standardize on `TestAuthenticationHelper` (array-based, no DB writes). Deprecate `AuthenticatedTrait` and `SuperUserAuthenticatedTrait` — they write to DB which conflicts with transaction wrapping.
2. **BaseTestCase migration priority:** The 17 files that write data get migrated first (Phase 2.1). Read-only tests migrated second (Phase 2.2). Queue plugin's 31 tests are explicitly excluded — they have their own fixture system and work.
3. **Auth failure triage:** 7 authorization test failures need investigation before fix. Do NOT assume test bug vs code bug. Jayne investigates, Mal reviews classification, then Kaylee fixes any production code bugs.
4. **ViewCell stubs:** Delete all 12 auto-generated `markTestIncomplete` stubs. They inflate counts while testing nothing. Coverage expansion is separate future work.
5. **Constants verified:** `KINGDOM_BRANCH_ID` must be 2 (Ansteorra), `TEST_BRANCH_LOCAL_ID` must be 14 (Shire of Adlersruhe). Current values (1 and 1073) reference nonexistent records.

**Phase order:** Runnable → Reliable → Auth consolidation → Auth failure investigation → Dead weight removal → CI pipeline. Estimated 6-8 working days.

**Plan location:** `.ai-team/decisions/inbox/mal-test-attack-plan.md`

📌 Team update (2026-02-10): Josh directive — no new features until testing is solid. Test assessment consolidated with deep dive findings into single decision block. — decided by Josh Handel
