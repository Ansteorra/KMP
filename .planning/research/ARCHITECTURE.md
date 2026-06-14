# Architecture Patterns — CakePHP 5 Plugin Maintenance

**Project:** Kingdom Management Portal (KMP)
**Scope:** Maintenance-mode architecture research
**Researched:** 2026-06-14
**Confidence:** HIGH — derived from direct codebase inspection, no speculative claims

---

## Component Map and Boundaries

### Dependency Flow (canonical direction)

```
Plugin → Core App → CakePHP Framework
   ↑         ↑
   │         └── App\Policy\BasePolicy
   │             App\Services\ViewCellRegistry
   │             App\Services\NavigationRegistry
   │             App\Model\Entity\BaseEntity
   │             App\Model\Table\BaseTable
   │             App\KMP\StaticHelpers
   │
   └── Plugin exposes: *Plugin::bootstrap(), NavigationProvider, ViewCellProvider
```

Plugins depend downward on core. Core must NOT depend upward on plugins. One confirmed inversion exists: `app/src/Model/Entity/Member.php` uses `Activities\Model\Entity\MemberAuthorizationsTrait` — this is the only known upward coupling in the namespace graph.

### Plugin Boundary Table

| Plugin | Own Tables | Owns Policies | Injects Into Core Via | Core Reaches Into Plugin Via |
|--------|-----------|--------------|----------------------|------------------------------|
| Activities | Yes | Yes | ViewCellRegistry, NavigationRegistry, `MemberAuthorizationsTrait` on `Member` entity | `GatheringActivitiesController` direct `fetchTable('Waivers.*')` + `GatheringWaiversTable` class_exists check |
| Awards | Yes | Yes | ViewCellRegistry, NavigationRegistry | None confirmed |
| Officers | Yes | Yes | ViewCellRegistry, NavigationRegistry, `ApiDataRegistry` | None confirmed |
| Waivers | Yes | Yes | ViewCellRegistry, NavigationRegistry | `GatheringsController:841` `class_exists` + `fetchTable('Waivers.GatheringWaivers')` |
| Queue | Yes | No | Background task runner | None |
| Bootstrap | No | No | UI helpers only | None |
| GitHubIssueSubmitter | No | No | Form endpoint | None |

### Cross-Cutting Core Services

These services are shared infrastructure. Every plugin uses them; none of them depend on any plugin.

| Service | Location | Shared How | Testability |
|---------|----------|-----------|------------|
| `ViewCellRegistry` | `app/src/Services/` | Static arrays populated during `bootstrap()` | Unit-testable; `::reset()` needed in tests |
| `NavigationRegistry` | `app/src/Services/` | Static arrays populated during `bootstrap()` | Same |
| `ActiveWindowManager` | `app/src/Services/ActiveWindowManager/` | DI-injected via container | Good — interface-based |
| `WarrantManager` | `app/src/Services/WarrantManager/` | DI-injected via container | Mostly skipped (see CONCERNS) |
| `StaticHelpers` | `app/src/KMP/StaticHelpers.php` | Static methods hit DB directly | Poor — static, no DI, no cache |
| `ServiceResult` | `app/src/Services/ServiceResult.php` | Return type convention | N/A — value object |
| `BasePolicy` | `app/src/Policy/BasePolicy.php` | Inherited by all policy classes | Testable via policy unit tests |

---

## Safe Change Patterns

### Pattern 1: Touch One Plugin at a Time

**What:** Make all changes for a bug fix within a single plugin boundary before moving to the next.

**Why:** The verification pipeline (`bash bin/verify.sh`) runs PHPUnit across all plugins. A change in plugin A that breaks plugin B's test is only visible after the full suite runs. Isolating changes to one plugin lets you run `vendor/bin/phpunit plugins/Awards/tests/` fast and catch failures locally before running the full suite.

**Build order implication:** Plugin migrations run in order Activities=1, Officers=2, Awards=3, Waivers=4. If a database fix touches the schema, run `bin/cake migrations migrate --plugin <Name>` for the specific plugin only. Never assume migration ordering is transitive — Awards schema can reference Officers schema, but Officers cannot reference Awards schema.

**Example pattern for a fix in Awards:**
```bash
# 1. Make the change
# 2. Run plugin-specific tests first
cd app && vendor/bin/phpunit plugins/Awards/tests/

# 3. Run full suite to check for regressions
cd app && composer test

# 4. Then verify.sh for PHPCS + PHPStan on changed files only
cd app && bash bin/verify.sh
```

### Pattern 2: Policy Changes — Never Add Native Type Hints to BasePolicy Overridable Methods

**What:** Methods in `BasePolicy` (`canAdd`, `canEdit`, `canDelete`, `canView`, `canIndex`, `scopeIndex`) intentionally use untyped `$entity` parameters. Plugin subclasses override these with different argument types — for example, passing `Table` where `BaseEntity` might be expected.

**Why this matters:** PHP's LSP enforcement at the engine level will reject subclasses whose overriding methods have narrower type hints than the parent. Adding `BaseEntity $entity` to `BasePolicy::canAdd()` would break every plugin policy that passes a `Table` object there. This already broke `WarrantsTable::afterSave()` and `ServicePrincipalPolicy::canAdd()` in prior work.

**Safe change rule for policies:**
- Add new `can*` methods to plugin policies freely — they do not override base.
- Never narrow parameter types on methods that override `BasePolicy`.
- When adding a new `can*` method to `BasePolicy`, keep `$entity` untyped or use a broad union (`BaseEntity|Table`).
- Check `BasePolicy.php` signature before touching any policy method signature.

**Detection:** If `phpcbf` or `phpstan` suggests adding a type hint to a `BasePolicy` method, refuse it.

### Pattern 3: ServiceResult Contract for All Service Returns

**What:** All service methods must return `ServiceResult` — never throw exceptions for expected failures, never return raw booleans, never return entities directly without wrapping.

**Why:** Controllers check `$result->success` after every service call. If a service returns a raw value, the controller-side check silently passes or errors in an unexpected way. The pattern `$result->isSuccess()` is load-bearing throughout `MembersController`, `RecommendationsController`, and all plugin controllers.

**Safe change rule:** When refactoring a service method or extracting logic from a god controller, wrap all results in `new ServiceResult(true/false, $reason, $data)` before returning.

### Pattern 4: ViewCellRegistry and NavigationRegistry Are Static — Reset in Tests

**What:** Both registries hold state in static arrays. They are populated during `PluginName::bootstrap()` calls at application startup. In unit tests that do not boot the full application, these arrays may be empty or stale.

**Safe change rule:** If a test asserts on navigation or view cells, call `NavigationRegistry::reset()` / `ViewCellRegistry::reset()` in `tearDown()` to prevent bleed between tests. When adding a new view cell registration in a plugin's `bootstrap()`, add a matching test in the plugin's `*ProvidersTest.php`.

### Pattern 5: DataverseGrid Columns Are the Gating Contract

**What:** Every list page runs through `DataverseGridTrait::processDataverseGrid()`. Column definitions live in `app/src/KMP/GridColumns/*GridColumns.php` (core) or `app/plugins/*/src/KMP/GridColumns/` (plugins). The `GridColumns` class drives: column visibility, filter names, sort fields, and CSV export headers.

**Safe change rule:** When fixing a filtering bug or sort bug on a list page, change the `GridColumns` class first, not the template. The template renders what `GridColumns` says. Changing the column class without updating the template is safe. Changing the template without the column class leads to mismatches.

### Pattern 6: class_exists Guard for Optional Plugin Tables

**What:** Core code that optionally reaches into a plugin uses `class_exists('Waivers\Model\Table\GatheringWaiversTable')` before `fetchTable('Waivers.GatheringWaivers')`. This prevents hard failures when Waivers is not installed.

**Safe change rule:** This pattern is acceptable for optional plugin integration. Do NOT remove the `class_exists` guard — it is the safety net, not a smell. If you are adding a new optional plugin integration to core, use the same pattern. If the integration becomes mandatory, move the logic into a service interface that the plugin registers.

### Pattern 7: Mailers Receive Pre-Formatted Strings — Never DateTime Objects

**What:** `TimezoneHelper::formatDate()` and `::formatDateTime()` must be called in the controller or service layer before data is passed to any mailer method or email template. Mailers and their templates do not have access to the kingdom's timezone setting through the view layer.

**Safe change rule:** When touching any controller action that sends email, audit all date-valued variables for raw `DateTime` objects. If any exist, format them before the `->send()` or service call. This applies to Queue tasks that send email too — format at task creation time, not inside the task.

---

## Tech Debt Reduction Strategy

### Principle: Incremental, Addable, Non-Destructive

The project has ~1947 PHPStan baseline suppressions and ~3400 PHPCS violations. These cannot be fixed in one pass. The correct approach is the strangler fig: each fix leaves the touched file cleaner than it was found, without touching untouched files.

### Prioritization Tiers

**Tier 1 — Fix as you touch (zero extra cost):**
- Fix PHPCS violations only in files you are already modifying. `verify.sh` checks only changed files.
- Remove dead code (deprecated methods, commented-out blocks) encountered during a fix.
- Add a test for the specific behavior you are fixing, not the entire class.

**Tier 2 — Targeted extractions (medium effort, high value):**
- Extract a method from a god controller into a service when you are already modifying that method. The `MembersController` (2702 lines) and `RecommendationsController` (2379 lines) should be treated as read-only until you need to touch a section — then extract that section to a service.
- Add `ServiceResult` return types to any service method you touch that currently returns a raw value.

**Tier 3 — Structural (plan separately, do not do inline):**
- `StaticHelpers` DI injection — requires a container change and touches every caller. Plan as its own task.
- `DataverseGridTrait` → `GridViewService` extraction — already started (`app/src/Services/GridViewService.php` exists). Do not attempt mid-fix.
- Build system migration (Laravel Mix → Vite) — isolated change, plan separately, no code changes.

### PHPStan Baseline Management Rule

**Never add a new suppression to `phpstan-baseline.neon`.** If PHPStan reports an error in new code you wrote, fix the code. The baseline exists only for pre-existing errors in files you did not touch. Running `vendor/bin/phpstan analyse --generate-baseline` is prohibited unless you have simultaneously eliminated at least as many errors as you are suppressing.

### PHPCS Rule

**Never run `vendor/bin/phpcbf` on any directory.** Run `vendor/bin/phpcs app/src/Path/To/File.php` on individual files you already modified, then manually fix the reported issues. The auto-fix adds docblock-derived type hints that break LSP compatibility.

---

## Test Coverage Approach

### Coverage Priorities for Maintenance

The existing gaps (from CONCERNS.md) represent the highest regression risk during maintenance:

| Gap | Risk Level | Recommended First Test to Add |
|-----|-----------|-------------------------------|
| `PermissionsLoader` — untested | CRITICAL | Unit test asserting a known role grants expected policy access |
| `WarrantManager` — mostly skipped | HIGH | Test `requestWarrant()` success path and cancellation path |
| `GatheringWaivers` controller — near-zero | HIGH | Integration test for the happy path of adding a waiver |
| JS Stimulus controllers — 5 of 20+ | MEDIUM | Add Jest test for the specific controller you are modifying |
| `Bootstrap` plugin — zero tests | LOW | Not a priority; it only wraps UI helpers |

### Test Pattern for a Maintenance Fix

When fixing a bug, write the test in this order:

1. **Write a failing test that reproduces the bug** — this proves the bug exists and the test is meaningful.
2. **Make the fix** — the test should now pass.
3. **Run the affected plugin suite** to catch regressions: `vendor/bin/phpunit plugins/PluginName/tests/`
4. **Run the full suite** before committing: `composer test`

This sequence is faster than writing tests after the fix because the failing test tells you exactly where the behavior breaks.

### Where to Put New Tests

| What you are fixing | Test class to add or extend | Base class to use |
|--------------------|----------------------------|------------------|
| Core controller action | `tests/TestCase/Controller/*ControllerTest.php` | `HttpIntegrationTestCase` |
| Plugin controller action | `plugins/*/tests/TestCase/Controller/*ControllerTest.php` | `PluginIntegrationTestCase` |
| Policy access rule | `tests/TestCase/Policy/*PolicyTest.php` or plugin equivalent | `BaseTestCase` |
| Service method | `tests/TestCase/Services/*ServiceTest.php` or plugin equivalent | `BaseTestCase` |
| JS Stimulus controller | `assets/js/controllers/__tests__/*-controller.test.js` | Jest |

### Authentication in Controller Tests

Use the modern `HttpIntegrationTestCase` or `PluginIntegrationTestCase` pattern. The old `AuthenticatedTrait` writes to the database and breaks transaction isolation. The correct pattern:

```php
class MyControllerTest extends HttpIntegrationTestCase
{
    public function testSomeAction(): void
    {
        $this->authenticateAsSuperUser(); // session only, no DB write
        $this->get('/some/path');
        $this->assertResponseOk();
    }
}
```

For non-super-user scenarios use `$this->authenticateAsMember(BaseTestCase::TEST_MEMBER_BRYCE_ID)` — constants are defined in `BaseTestCase` for each seeded test persona.

### Do Not Use `--testsuite all`

The `--testsuite all` definition in `phpunit.xml.dist` only covers half the test files due to `tests/TestCase/` not recursing into subdirectories correctly. Use `composer test` (no arguments) which invokes the default suite or explicitly name suites:
- `vendor/bin/phpunit --testsuite core-unit`
- `vendor/bin/phpunit --testsuite core-feature`
- `vendor/bin/phpunit --testsuite plugins`

---

## Plugin Boundary Rules

### Rule 1: Plugins May Import Core, Core May Not Import Plugins

Plugins import from `App\*` namespaces freely. Core (`app/src/`) must not import from `Activities\*`, `Awards\*`, `Officers\*`, `Waivers\*` namespaces. The one confirmed violation (`Member.php` using `MemberAuthorizationsTrait`) should be treated as a known technical debt item — do not add new violations in this direction.

**Enforcement:** Before committing a change to `app/src/`, run:
```bash
grep -rn "use Activities\\|use Awards\\|use Officers\\|use Waivers" app/src/
```
If the output grows beyond the known single line in `Member.php`, the change violates this rule.

### Rule 2: Optional Plugin Coupling Uses class_exists, Not Imports

When core code optionally uses a plugin's table (e.g., `GatheringsController` checking for Waivers), guard with `class_exists('Waivers\Model\Table\...')` before any `fetchTable()` call. Never add an import (`use Waivers\...`) in core — that creates a hard dependency.

### Rule 3: Plugin-to-Plugin Communication Goes Through Core Services

Plugins do not import from each other. If Activities needs to know about Officers warrants, it uses `WarrantManagerInterface` (a core service interface) — it does not import `Officers\Services\DefaultOfficerManager`. The `ApiDataRegistry` and `ViewCellRegistry` provide extension points for plugin-to-plugin data sharing without coupling.

**Verified:** No inter-plugin namespace imports exist in the current codebase. This is the correct state.

### Rule 4: Plugin Migrations Cannot Cross-Reference Plugin Tables Above Their migrationOrder

Migration order is Activities=1, Officers=2, Awards=3, Waivers=4. A migration in Awards (order 3) may add a foreign key to an Officers (order 2) table because Officers runs first. A migration in Officers may NOT reference an Awards table because Awards has not yet been created when Officers migrates. Core tables always exist before any plugin migration.

**Build order implication:** When adding a new migration to a plugin, verify whether it references another plugin's table. If it does, the referencing plugin must have a higher `migrationOrder`.

### Rule 5: View Cell and Navigation Registration Happens Entirely in bootstrap()

Plugins register all their view cells and navigation items in `PluginName::bootstrap()`, never in controller actions or service methods. Registration calls `ViewCellRegistry::register()` and `NavigationRegistry::register()` with static arrays or closures. The timing matters: registration happens at application startup, before any request is handled.

**Implication for fixes:** If a plugin's tab or nav item is not appearing, the bug is in `bootstrap()` or the view cell provider's callback — not in the controller or template. Start debugging there.

### Rule 6: Policy Classes Must Exist for Every Entity and Table

The `ControllerResolver` maps controller names to policy classes. CakePHP's authorization middleware will throw `MissingPolicyException` if a policy class does not exist. Every entity needs both an `EntityPolicy` and a `TablePolicy`. Every new model added during a fix needs corresponding policy files, even if they just extend `BasePolicy` with no overrides.

---

## Data Flow: Key Maintenance Checkpoints

### Web Request — Where to Intervene for a Bug Fix

```
Request
  → Middleware (security, routing, auth) — rarely touch
  → AppController::beforeFilter() — nav history, CSV detection, cell loading
  → Controller action
      → Authorization::authorize() — touch only policy files if auth is wrong
      → Service call (returns ServiceResult) — touch service file for logic bugs
      → Table/ORM query — touch table file for data bugs
      → View variables set
  → Template rendered (Turbo Frames for partial updates)
  → Response
```

**If the bug is "user sees wrong data":** Start at the Table/ORM query, then the service.
**If the bug is "user can't access something they should":** Start at the policy class for that entity.
**If the bug is "UI tab/section is missing":** Start at the plugin's `bootstrap()` and ViewCellProvider.
**If the bug is "grid filter/sort is broken":** Start at the `*GridColumns.php` class.
**If the bug is "email has wrong date":** Start at the controller action that calls the mailer — find the raw DateTime and format it.

### Dataverse Grid Data Flow

```
Browser GET /plugin/entities (renders shell)
  → Browser fires Turbo Frame request GET /plugin/entities/gridData
    → DataverseGridTrait::processDataverseGrid()
      → GridColumns class provides column/filter config
      → Authorization::applyScope() adds branch scoping
      → ORM query built, paginated
      → Renders dv_grid_content element
```

**Implication:** Grid bugs are almost always in `GridColumns` (filter names, sort fields) or in the `scopeIndex` policy method. The template is unlikely to be the bug.

---

## Build Order Implications for Multi-Area Fixes

When a fix spans multiple files across plugins, apply in this sequence to minimize failed intermediate states:

1. **Database migrations first** (lowest layer) — run `bin/cake migrations migrate --plugin <Name>` immediately after writing each migration, not after all migrations are written.
2. **Model/Table and Entity** — add new columns, behaviors, associations.
3. **Policy classes** — add new `can*` methods if new actions are exposed.
4. **Service layer** — add or modify business logic, returning `ServiceResult`.
5. **Controller** — thin wire-up: call service, authorize, set view variables.
6. **Templates** — render what the controller provides.
7. **GridColumns** — if the fix affects list pages.
8. **Plugin bootstrap (ViewCellRegistry / NavigationRegistry)** — if the fix adds a new tab or nav item.
9. **Frontend JS** — run `npm run dev` after any JS or template change that includes Stimulus attributes.
10. **Tests** — write/update tests for each layer touched.

This ordering ensures each layer has its dependencies in place before you test it.

---

## Known Architectural Risks Relevant to Maintenance

| Risk | Impact | Mitigation During Fixes |
|------|--------|------------------------|
| `MembersController` is 2702 lines | High change-frequency file with merge conflict risk | Extract the specific method you are fixing into a service; do not edit adjacent methods |
| `RecommendationsController` is 2379 lines | Same risk in Awards plugin | Same approach |
| `StaticHelpers` hits DB on every call | Performance and testability | Do not add new `StaticHelpers::getAppSetting()` calls; use cached values where possible |
| PHPStan baseline at ~1947 suppressions | New errors may hide in noise | Run `vendor/bin/phpstan analyse --no-progress` and check the diff carefully |
| `PermissionsLoader` is untested | Security-critical code has no regression protection | Before fixing auth bugs, add a baseline test first |
| `MemberAuthorizationsTrait` inverts plugin boundary | Core entity depends on Activities plugin | Do not add more upward dependencies; isolate the existing one |
| `class_exists` plugin boundary crossing in GatheringsController | Fragile integration pattern | Do not extend; refactor toward a registered service if the scope grows |

---

## Confidence Assessment

All findings in this document are derived from direct inspection of the production codebase at `/workspaces/KMP/app/`. No speculative claims are made about behavior that was not verified in code.

| Area | Confidence | Basis |
|------|-----------|-------|
| Plugin dependency direction | HIGH | Grep-verified; one confirmed inversion documented |
| Policy LSP constraint | HIGH | Direct `BasePolicy.php` read; history documented in CONCERNS.md |
| Test infrastructure patterns | HIGH | Read `HttpIntegrationTestCase`, `PluginIntegrationTestCase`, `BaseTestCase` |
| Migration ordering rules | HIGH | `config/plugins.php` confirmed; schema FK direction verified |
| Static registry reset requirement | HIGH | `ViewCellRegistry` and `NavigationRegistry` are static arrays |
| God controller extraction strategy | HIGH | Line counts confirmed; `GridViewService.php` already exists |
| PHPStan/PHPCS rules | HIGH | `verify.sh` and project instructions are explicit |
