# Project Context

- **Owner:** Josh Handel (josh@liveoak.ws)
- **Project:** KMP — Membership management system for SCA Kingdoms. Handles members, officers, warrants, awards, activities, and workflow-driven approvals. ~2 years of active development.
- **Stack:** CakePHP 5.x, Stimulus.JS, MariaDB, Docker, Laravel Mix, Bootstrap, plugin architecture
- **Created:** 2026-02-10

## Learnings

<!-- Append new learnings below. Each entry is something lasting about the project. -->

### 2026-02-10: Architecture Overview (summarized from full map)

#### Structure
CakePHP 5.x app in `/app/` with Docker orchestration. Three services: PHP/Apache, MariaDB 11, Mailpit. Frontend: Stimulus.JS + Turbo Frames (Drive disabled) + Bootstrap 5.3.6, built via Laravel Mix (`app/webpack.mix.js`).

#### Plugin Ecosystem
**Active domain:** Activities (auth/activities, API), Officers (warrants/rosters, API), Awards (recommendations/state machine), Waivers (gathering waivers). **Infrastructure:** Queue (async jobs), GitHubIssueSubmitter. **Inactive:** Template (reference impl), Events (not implemented). **Third-party:** DebugKit, Bake, Tools, Migrations, Muffin/Footprint, Muffin/Trash, BootstrapUI, Authentication, Authorization, ADmad/Glide, CsvView.

Plugin registration: `config/plugins.php` → Plugin class implements `KMPPluginInterface` → `bootstrap()` registers navigation/cells/settings → optional DI in `services()` → API via `KMPApiPluginInterface`. Enable/disable: `Plugin.{Name}.Active` AppSetting.

#### Services & DI
Core DI: AWM (no txn), WM (owns txn, depends on AWM), CsvExport, ICal, Impersonation. Plugin DI: OfficerManager (AWM+WM), AuthorizationManager. Static: NavigationRegistry, ViewCellRegistry, ApiDataRegistry. All return `ServiceResult(success, reason, data)`.

#### Auth Architecture
Dual auth: session+form (web), Bearer token (API). Policy-based authorization with ORM+Controller resolvers. 37 policies, all extend BasePolicy (super-user bypass in `before()`). Permission chain: Members→MemberRoles→Roles→Permissions→PermissionPolicies→Policies. Three scopes: Global, Branch Only, Branch+Children. Cached via PermissionsLoader.

#### Dangerous to Change
1. BaseEntity/BaseTable hierarchy  2. PermissionsLoader + permission chain  3. ServiceResult pattern  4. NavigationRegistry/ViewCellRegistry static registration  5. Middleware order  6. ActiveWindowBehavior temporal logic  7. Transaction ownership (AWM=caller, WM=self)  8. window.Controllers registration pattern

#### Key Paths
Application: `app/src/Application.php`. KMP core: `app/src/KMP/`. Services: `app/src/Services/`. Controllers: `app/src/Controller/` (26 + Api/). Policies: `app/src/Policy/` (37 files). Config: `app/config/`. Plugins: `app/plugins/`. Frontend: `app/assets/js/`. Tests: `app/tests/TestCase/`. Build: `app/webpack.mix.js`.

### 2026-02-10: Test Infrastructure Attack Plan

Josh directed all features paused until testing is solid. 6-phase plan created:
1. Make suites runnable (delete duplicates, fix constants) ✅ DONE
2. Fix state leakage (migrate to BaseTestCase) ✅ DONE
3. Auth consolidation (standardize TestAuthenticationHelper) — gap found
4. Auth failure investigation (15 TEST_BUG, 2 CODE_BUG) ✅ DONE
5. Remove dead weight (delete stubs, fix warnings)
6. CI pipeline (GitHub Actions)

Key decisions: Standardize TestAuthenticationHelper (deprecate old traits). Queue plugin excluded. ViewCell stubs to be deleted. Constants: KINGDOM_BRANCH_ID=2, TEST_BRANCH_LOCAL_ID=14.

📌 Team update (2026-02-10): Backend patterns documented — 14 critical conventions including ServiceResult, transaction ownership, entity/table hierarchy, and authorization flow — decided by Kaylee
📌 Team update (2026-02-10): Frontend patterns documented — 81 Stimulus controllers cataloged, asset pipeline, tab ordering, inter-controller communication via outlet-btn — decided by Wash
📌 Team update (2026-02-10): Test suite audited — 88 files but ~15-20% real coverage, 36/37 policies untested, no CI pipeline, recommend adding CI test runner as Priority 1 — decided by Jayne
📌 Team update (2026-02-10): Josh directive — no new features until testing is solid. Test infrastructure is the priority. — decided by Josh Handel
📌 Team update (2026-02-10): Auth triage complete — 15 TEST_BUGs, 2 CODE_BUGs. Kaylee fixed both CODE_BUGs. All 370 project-owned tests now pass (was 121 failures + 76 errors). — decided by Jayne, Kaylee
📌 Team update (2026-02-10): Auth strategy gap identified — authenticateAsSuperUser() does not set permissions. Must be fixed before Phase 3.2 test migration. — decided by Mal
