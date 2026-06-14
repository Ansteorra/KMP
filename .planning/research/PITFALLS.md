# Domain Pitfalls — CakePHP 5 Application Maintenance

**Domain:** CakePHP 5 plugin-architecture membership management system (KMP)
**Analysis Date:** 2026-06-14
**Scope:** Maintenance-mode pitfalls — regressions, security issues, and cascading failures from small fixes

---

## Authorization Pitfalls

### CRITICAL: Adding Native Type Hints to BasePolicy Overridable Methods

**What goes wrong:** A developer adds native PHP type hints to `canAdd()`, `canEdit()`, `canDelete()`, `canView()`, or `canIndex()` in `app/src/Policy/BasePolicy.php` — often during a "cleanup" or when satisfying a PHPStan suggestion.

**Why it happens:** PHPStan level 5 flags loosely typed method parameters. The untyped `$query`/`$entity` params on `BasePolicy` methods look like a bug to a maintainer who doesn't know why they're untyped. `phpcbf` will also add type hints from docblocks if run globally.

**Consequences:** Plugin policy classes override these methods with different signatures (e.g. `Awards\Policy\RecommendationPolicy::canEdit()` may accept an Awards-specific entity). PHP's LSP enforcement at runtime throws a fatal error for any request that reaches that policy method. Previously broke `ServicePrincipalPolicy::canAdd()`.

**Prevention:**
- The `BaseEntity|Table` union type on method params in `BasePolicy` is intentional — do not narrow it.
- Never run `vendor/bin/phpcbf` on the full codebase. Only manually fix PHPCS issues in files you are editing.
- Add a comment above each overridable method in `BasePolicy` explaining the LSP constraint (this partially exists; do not remove it).
- PHPStan suppressions for these specific signatures belong in `phpstan-baseline.neon`; do not "fix" them.

**Detection:** `composer test` will surface fatal errors immediately. PHPStan will report a covariance violation.

**Phase/area affected:** Any change to `app/src/Policy/BasePolicy.php` or any plugin `*Policy.php` that overrides a base method.

---

### CRITICAL: Bypassing Authorization on New Actions

**What goes wrong:** A developer adds a new controller action — perhaps a helper endpoint like a status toggle or a bulk operation — and forgets `$this->Authorization->authorize($entity)` or `$this->Authorization->authorizeModel(...)`. Because `AuthorizationMiddleware` requires authorization to be performed for every request, this throws a `MissingIdentityException` or `AuthorizationRequiredException` in production — but only after the action already ran its logic.

**Why it happens:** CakePHP 5's Authorization plugin requires an explicit authorize call; it does not automatically infer authorization from routing. It is easy to copy an action template and omit the authorize step when the action "feels" like an internal helper.

**Consequences:** Either an unguarded action executes before failing (partial data mutation with an error response), or the middleware catches it and throws — leaving the developer confused about why a seemingly complete action errors.

**Prevention:**
- Every controller action must call `$this->Authorization->authorize($entity)` (entity-level) or `$this->Authorization->authorizeModel($table, 'action')` (table-level) before any data mutation.
- For read-only informational endpoints, use `$this->Authorization->skipAuthorization()` explicitly and document why it is safe to skip.
- The `PermissionsLoader` test suite (`tests/TestCase/`) is currently skipped — this is the highest-risk gap because authorization loading is untested. Do not add new permission structures without a test.

**Detection:** Missing authorization raises `AuthorizationRequiredException` at the end of the request (caught by middleware). Newly added actions with no authorize call will surface this in integration tests.

**Phase/area affected:** Every new controller action, especially bulk-operation or AJAX endpoints.

---

### CRITICAL: scopeIndex Returning Unrestricted Queries for Non-Super Users

**What goes wrong:** When writing a new `TablePolicy`, a developer inherits `scopeIndex()` from `BasePolicy` without overriding it. `BasePolicy::scopeIndex()` calls `addBranchScopeQuery()` — but only if `$branchIds` is not empty. If the entity has no `branch_id` column or if `addBranchScopeQuery()` is not implemented on that Table, the scope silently returns the full unfiltered query.

**Why it happens:** The scope method works correctly for branch-scoped entities. Entities that don't have a branch relationship (e.g. app settings, global reference tables) fail silently because the branch check resolves to empty.

**Consequences:** Users who should only see records belonging to their branch see all records in the system.

**Prevention:**
- For entities with no branch relationship, override `scopeIndex()` in the entity-specific `TablePolicy` and apply the correct filter explicitly.
- Never assume `scopeIndex()` from `BasePolicy` is a safe default for new entities — verify what `addBranchScopeQuery()` does on the target Table class.

**Detection:** Manual review of the grid output for a non-super-user. PHPStan will not catch this.

**Phase/area affected:** New entity grids, especially in plugin code where entities may span branches differently than core entities.

---

### Moderate: Super User Bypass Masking Policy Gaps During Development

**What goes wrong:** All development and QA testing is done with the `admin@amp.ansteorra.org` super-user account. `BasePolicy::before()` returns `true` immediately for super users, skipping all policy evaluation. A policy method that is broken — missing, returns wrong value, or throws — is never exercised during development.

**Why it happens:** Super-user shortcut is correct by design but makes it invisible when a non-super user would hit a policy bug.

**Consequences:** A regression in a policy method ships to production and is discovered only when real users (with scoped roles) trigger the failing path.

**Prevention:**
- Test authorization paths using the `iris@ampdemo.com` basic-user account or a role-scoped test user.
- PHPUnit controller tests should use non-super-user fixtures for at least one test per action.
- `PermissionsLoader` tests are the highest-priority gap to close — currently skipped entirely.

**Detection:** Test failures only when running as a non-super user. Not caught by PHPStan.

**Phase/area affected:** Any code change touching policy classes or permission-gated actions.

---

### Moderate: Controller Policy Resolution Failure on New Prefix Routes

**What goes wrong:** CakePHP's custom `ControllerResolver` resolves policy classes by controller name, plugin, and prefix. Adding a new URL prefix (e.g. `/admin/`) without a corresponding `AdminControllerPolicy` class causes `MissingPolicyException` at runtime for every request under that prefix.

**Why it happens:** The `ControllerResolver::findPolicy()` method throws `MissingPolicyException` if neither the plugin-namespaced nor the app-namespaced policy class is found. There is no fallback to `BasePolicy`.

**Consequences:** A whole prefix segment of the app returns 500 errors if the policy class is missing.

**Prevention:**
- Any new controller (especially under a new prefix) needs a corresponding `*ControllerPolicy` class, even if it just extends `BasePolicy` directly.
- Run the full test suite (`composer test`, not `--testsuite all`) after adding any new controller.

**Detection:** First request to the new controller will throw a 500 in development. No static analysis warning.

**Phase/area affected:** New controllers and new route prefixes.

---

## ORM and Migration Pitfalls

### CRITICAL: Running Migrations Out of migrationOrder

**What goes wrong:** A developer runs `bin/cake migrations migrate` without understanding that plugin migrations must run in the order defined by `migrationOrder` in `config/plugins.php` (Activities=1, Officers=2, Awards=3, Waivers=4). Running migrations in the wrong order, or running a plugin migration before core migrations, creates foreign-key constraint failures.

**Why it happens:** CakePHP's migration runner applies all pending migrations across plugins unless told otherwise. The `migrationOrder` key in `plugins.php` documents the intent but does not enforce it automatically.

**Consequences:** Foreign key violations at the database level, leaving the schema in a partially migrated state that requires manual SQL repair.

**Prevention:**
- Always migrate core first: `bin/cake migrations migrate`
- Then migrate each plugin in migrationOrder: `bin/cake migrations migrate --plugin Activities`, then Officers, then Awards, then Waivers.
- Document any new migration's foreign-key dependencies in a comment at the top of the migration file.
- The `reset_dev_database.sh` script handles ordering correctly — reference it as the canonical migration sequence.

**Detection:** Database constraint errors during migration. The schema state must be repaired manually if this happens.

**Phase/area affected:** Any migration work, database resets, new deployment environments.

---

### CRITICAL: Breaking ActiveWindow Status Sync by Mutating start_on/expires_on Directly

**What goes wrong:** A developer modifies `start_on` or `expires_on` directly on an ActiveWindow entity (Warrant, MemberRole, Authorization, etc.) via SQL or by setting the property without going through `ActiveWindowBaseEntity::start()` / `expire()`. The `status` column falls out of sync with the date window.

**Why it happens:** Date columns are simple strings in the database; it looks like a safe field to update. The status lifecycle (`Upcoming → Current → Expired`) is maintained by the console command `bin/cake sync_active_window_statuses`, which runs on a schedule and only sets `modified_by = 1` (the system user). Manually edited records that are already in `Current` status but have a past `start_on` are never re-evaluated.

**Consequences:** An entity appears `Current` in queries but should be `Expired`, or vice versa. Warrant checks, activity authorization checks, and officer roster queries return incorrect results silently.

**Prevention:**
- Always use `ActiveWindowBaseEntity::start()` and `expire()` methods for date window transitions — never set `start_on`/`expires_on` directly.
- When patching entity dates via a controller form, route through the service layer (`ActiveWindowManagerInterface`) rather than raw Table saves.
- The sync command is safe to run manually on demand: `bin/cake sync_active_window_statuses --dry-run` to preview.

**Detection:** Manual data audits. No automatic detection at the ORM layer.

**Phase/area affected:** Warrants, Officers, Activities/Authorizations, MemberRoles — anything that uses `ActiveWindowBehavior`.

---

### CRITICAL: Adding allowFallbackClass(false) to CLI Context

**What goes wrong:** `Application::bootstrap()` already sets `allowFallbackClass(false)` on the TableLocator for web requests, but explicitly skips this for CLI (`PHP_SAPI !== 'cli'`). A developer copies the block and removes the CLI guard, causing every CLI command (`bin/cake migrations`, `bin/cake sync_active_window_statuses`, etc.) to throw a `MissingTableClassException` for any table that doesn't have an explicit class file.

**Why it happens:** The guard looks like dead code during a cleanup.

**Consequences:** All CLI commands fail. Migrations cannot run. Cron-driven status sync breaks silently.

**Prevention:**
- The `PHP_SAPI !== 'cli'` guard in `Application::bootstrap()` is intentional. Do not remove it.
- Any new CLI command that loads tables must be tested with `bin/cake` directly, not just via PHPUnit's web harness.

**Detection:** Immediate failure of all `bin/cake` commands.

**Phase/area affected:** `Application::bootstrap()`, any modification to the `FactoryLocator` setup.

---

### Moderate: Missing getBranchId() Override Causes Silent Authorization Pass

**What goes wrong:** When creating a new entity class that extends `BaseEntity`, the default `getBranchId()` returns `$this->branch_id ?? null`. For entities where the branch relationship is indirect (e.g., branch comes from a related member rather than a direct column), this returns `null`. `BasePolicy::_hasPolicy()` treats `null` branch_id as "no branch restriction" and returns `true` for non-globally-scoped permissions.

**Why it happens:** `BaseEntity::getBranchId()` documents that "child classes should override for complex branch relationships" — but if a developer misses this, there is no error; access is just silently granted.

**Consequences:** Non-global users get access to records outside their branch without any error or log entry.

**Prevention:**
- Any new entity with an indirect branch relationship must override `getBranchId()`.
- Search for `getBranchId` overrides in existing entities to understand patterns (e.g., via an associated Member).
- Integration tests for non-super users must assert that out-of-branch records are inaccessible.

**Detection:** Requires testing with a branch-scoped (non-global) user role.

**Phase/area affected:** New entities in any plugin, especially those that associate with Members or other branch-carrying entities.

---

### Moderate: StaticHelpers::getAppSetting() Causing N+1 DB Queries

**What goes wrong:** `StaticHelpers::getAppSetting()` reads from the `app_settings` table on every call with no request-level cache. In templates or view cells, calling this multiple times per request (e.g., for site title, logo path, kingdom name) causes a DB query per call. Views that iterate or render multiple cells compound this.

**Why it happens:** The static method API invites inline usage anywhere without thinking about query cost.

**Consequences:** Performance degradation proportional to the number of `getAppSetting()` calls per request. On pages with multiple plugin cells, this can add 10–20+ extra queries.

**Prevention:**
- Call `StaticHelpers::getAppSetting()` once in the controller and pass results to the view via `$this->set()`, not inline in templates.
- Do not add new `getAppSetting()` calls inside view cells or template fragments.
- The known improvement is to add a request-level cache — until that is implemented, minimize call sites.

**Detection:** Enable `PERF_REQUEST_LOG_ENABLED` and `PERF_LOG_ALL_REQUESTS` env flags; query count spikes indicate this pattern.

**Phase/area affected:** Templates, view cells, any code path that touches app settings during rendering.

---

### Moderate: Nested Set Model Corruption from Direct Branch Updates

**What goes wrong:** The Branch hierarchy uses the Nested Set Model (`lft`/`rght` columns managed by a behavior). Updating a branch record directly via SQL or bypassing the Table save lifecycle (e.g., using `updateAll()`) skips the NSM behavior and corrupts the `lft`/`rght` values.

**Why it happens:** `updateAll()` looks like an efficient way to bulk-update fields. NSM behavior hooks run on `save()` but not on raw `updateAll()`.

**Consequences:** Branch hierarchy queries break. Permission scoping that uses `lft`/`rght` for "Branch and Children" returns wrong branch sets. The corruption propagates to all authorization checks that scope by branch hierarchy.

**Prevention:**
- Never use `updateAll()` or raw SQL to modify Branch entities. Always use `save()` through the Table class.
- For bulk status transitions on non-Branch entities, `updateAll()` is fine — but verify there are no active behaviors that must respond to the change.
- If NSM corruption occurs, the behavior provides a `recover()` method to rebuild the tree.

**Detection:** Branch hierarchy pages display incorrect nesting. Authorization checks return unexpected results for hierarchically-scoped roles.

**Phase/area affected:** Any code touching the Branches table, especially permission scoping.

---

## Frontend (Stimulus / Turbo) Pitfalls

### CRITICAL: Forgetting the Webpack Build Step After JS/CSS Changes

**What goes wrong:** A developer edits a Stimulus controller in `app/assets/js/controllers/` or `app/plugins/*/assets/js/controllers/` and tests the change in the browser — but the browser serves the compiled bundle from `webroot/js/controllers.js`, which has not been rebuilt. The change appears to have no effect.

**Why it happens:** The dev server at localhost:8080 serves static files from `webroot/`. Asset changes in `assets/` are not reflected until webpack runs. There is no hot-reload in the current Laravel Mix setup.

**Consequences:** Developer loses time debugging a change that is correct but not compiled. Worse: a developer confirms a change "works" by testing a stale build, then commits without building — and the actual bundle does not include the change.

**Prevention:**
- Run `npm run dev` after every JS or CSS change. Use `npm run watch` during active frontend development.
- CI runs `npm run prod` as part of `bin/verify.sh` — a commit without a rebuilt bundle will produce a stale `webroot/` that differs from `assets/`.
- Add the compiled `webroot/js/` and `webroot/css/` outputs to your mental checklist before committing.

**Detection:** Browser shows old behavior. Dev Tools Network tab shows an unchanged `controllers.js` hash.

**Phase/area affected:** Any Stimulus controller change, any CSS change in `app/assets/`.

---

### CRITICAL: Registering Stimulus Controllers Without window.Controllers Guard

**What goes wrong:** A new Stimulus controller file does not use the `window.Controllers["name"] = ControllerClass` registration pattern — for example, it imports directly or calls `stimulusApp.register()` directly inside the controller file. The controller either fails silently (if `stimulusApp` is not yet defined when the controller file loads) or registers fine but is invisible to the dynamic registration loop in `index.js`.

**Why it happens:** The `index.js` registration pattern (`for (const controller in window.Controllers)`) is KMP-specific and not standard Stimulus documentation. A developer unfamiliar with the pattern may follow the official Stimulus docs instead.

**Consequences:** The controller is never registered with the Stimulus Application instance. Elements with `data-controller="my-feature"` silently do nothing.

**Prevention:**
- All controller files must end with: `if (!window.Controllers) { window.Controllers = {}; } window.Controllers["identifier"] = MyController;`
- Reference `app/assets/js/controllers/popover-controller.js` as the canonical pattern.
- Jest tests that import a controller and call `register()` directly are also acceptable for unit tests — but the registration guard is required for runtime.

**Detection:** `window.Stimulus.router.modulesByIdentifier` in the browser console will not contain the identifier. No runtime error is thrown.

**Phase/area affected:** Any new Stimulus controller, whether in `app/assets/` or `app/plugins/*/assets/`.

---

### CRITICAL: Turbo Frame Responses Bypassing CSRF

**What goes wrong:** API or AJAX endpoints added to handle Turbo Frame requests are placed under `/api/` or given a custom prefix that matches the CSRF skip rule (`/api/` path prefix). This means CSRF tokens are not validated for those requests, even if they perform state-changing mutations.

**Why it happens:** The CSRF skip rule in `Application::middleware()` was intended only for machine-to-machine API calls authenticated via Bearer token. A developer creating a Turbo Frame data endpoint under `/api/` for convenience gets CSRF skipped without realizing it.

**Consequences:** State-mutating endpoints without CSRF protection are vulnerable to cross-site request forgery.

**Prevention:**
- Turbo Frame endpoints that mutate state must NOT be placed under `/api/`. They must use standard web routes where CSRF is enforced.
- Only endpoints authenticated by `ServicePrincipalAuthenticator` (Bearer token / X-API-Key) belong under `/api/`.
- Review route placement for any new endpoint that is reachable from a browser session.

**Detection:** Inspect the middleware stack for the request path. CSRF bypass only applies to `/api/` prefix.

**Phase/area affected:** New endpoints that serve Turbo Frame partial responses with form submissions.

---

### Moderate: ViewCellRegistry Order Collision Between Plugins

**What goes wrong:** Two plugins register a view cell tab (type `PLUGIN_TYPE_TAB`) for the same `validRoute` with the same `order` value. `ViewCellRegistry::organizeViewCells()` uses `order` as the array key — the later registration silently overwrites the earlier one.

**Why it happens:** Plugin authors pick an order number without auditing what is already registered. The convention (plugin tabs 1–10, core entity tabs 10–20, secondary tabs 20–30) is documented in CLAUDE.md but not enforced.

**Consequences:** One plugin's tab disappears entirely. No error is thrown.

**Prevention:**
- Before adding a new tab registration, grep `validRoutes` across all plugin `bootstrap()` files for the target controller/action pair to check for order conflicts.
- Plugin tabs should use distinct order values: Activities uses a different number than Awards.
- Add `data-tab-order` and `style="order: N"` attributes to template tabs that match the registry value — inconsistency here is a sign of a conflict.

**Detection:** A tab that was visible disappears after a plugin registration change. `ViewCellRegistry::getDebugInfo()` can enumerate registered sources.

**Phase/area affected:** Any plugin that registers view cells on shared entity detail pages (e.g., a Member view that has tabs from Activities, Officers, and Awards).

---

### Moderate: NavigationRegistry Static State Persisting Across Test Runs

**What goes wrong:** `NavigationRegistry` and `ViewCellRegistry` use static arrays populated during plugin `bootstrap()`. In PHPUnit, if tests exercise plugin bootstrap without clearing these statics between test cases, navigation and cell registrations accumulate. A test that expects only the core navigation sees nav items from a prior test's plugin registration.

**Why it happens:** Static state in PHP persists for the lifetime of the PHP process. PHPUnit re-uses the process between test cases by default.

**Consequences:** Flaky tests that fail when run in certain orderings. Hard to reproduce intermittently.

**Prevention:**
- Call `ViewCellRegistry::clear()` and (if a similar method exists) navigation registry clear in `tearDown()` for any test that triggers plugin bootstrap.
- Integration tests that test navigation or cell rendering should bootstrap only the plugins under test.

**Detection:** Tests pass in isolation but fail when run with the full suite. Order-dependent failures.

**Phase/area affected:** PHPUnit tests for controllers that render plugin tabs or navigation.

---

### Minor: Bootstrap Tooltips Not Re-Initialized After Turbo Frame Renders

**What goes wrong:** Bootstrap tooltips on elements inside a Turbo Frame are initialized once on page load. When a Turbo Frame refreshes its content (e.g., after a form submission), new elements in the frame are not initialized because the `turbo:render` handler in `index.js` only initializes tooltips for elements not already tracked by Bootstrap.

**Why it happens:** The `turbo:render` handler uses `if (!bootstrap.Tooltip.getInstance(el))` — correct for elements that persist across renders, but only elements present in the DOM at `turbo:render` time are scanned. Elements that arrive late via subsequent Turbo Frame loads are missed.

**Consequences:** Tooltips silently fail to appear inside Turbo Frames that refresh after the initial page load.

**Prevention:**
- For tooltips inside frequently-refreshed Turbo Frames, initialize them via a Stimulus controller connected/disconnected lifecycle instead of the global `turbo:render` handler.
- The `popover-controller.js` Stimulus controller handles popovers correctly — use the same lifecycle pattern for tooltips.

**Detection:** Hover over a tooltip trigger inside a Turbo Frame after the frame has refreshed. No tooltip appears.

**Phase/area affected:** Any template with Bootstrap tooltips inside Turbo Frames (grid cells, detail tabs).

---

## Testing Pitfalls

### CRITICAL: Running --testsuite all and Trusting the Result

**What goes wrong:** A developer runs `vendor/bin/phpunit --testsuite all` expecting to run all ~1018 tests. The `all` suite definition in `phpunit.xml.dist` scans `tests/TestCase/` but misses tests in `plugins/*/tests/TestCase/` that are outside the core directory tree. The result is a green run that covered only about half the actual test suite.

**Why it happens:** `--testsuite all` sounds definitive. The phpunit.xml.dist definition for `all` was written before the plugin test layout stabilized.

**Consequences:** A regression in plugin tests ships with a falsely "clean" test run. This is specifically called out in CLAUDE.md but is easy to miss.

**Prevention:**
- Always use `composer test` (maps to `vendor/bin/phpunit` with no suite filter — runs everything).
- `--testsuite core-unit`, `--testsuite core-feature`, and `--testsuite plugins` are valid targeted suites.
- Never use `--testsuite all` for a confidence check.

**Detection:** `composer test` will fail where `--testsuite all` passed.

**Phase/area affected:** Any change to plugin code. CI gate uses `composer test`.

---

### CRITICAL: PHPStan Baseline Growing Without a Gate

**What goes wrong:** New code introduces PHPStan errors that are not fixable immediately (e.g., type errors in third-party integrations). A developer adds them to `phpstan-baseline.neon` as a workaround. The baseline grows from ~1023 entries to 1100, 1200 — each addition making future cleanup harder.

**Why it happens:** `phpstan-baseline.neon` suppression is a legitimate escape hatch. Without a CI gate on baseline size, suppression is the path of least resistance.

**Consequences:** PHPStan stops surfacing real bugs because the noise-to-signal ratio grows. The baseline becomes a graveyard of suppressed errors that may include actual bugs.

**Prevention:**
- New code should not add to `phpstan-baseline.neon`. If a PHPStan error cannot be fixed immediately, document why in a comment, not a baseline entry.
- Periodically run PHPStan without the baseline to see the raw error count and identify true regressions.
- The known unbaselinable `HtmlHelper` type covariance error is the only acceptable "pass despite error" in `verify.sh`.

**Detection:** `git diff phpstan-baseline.neon` — any additions are a warning signal.

**Phase/area affected:** Any new PHP file added to `src/` or `plugins/*/src/`.

---

### Moderate: Using --filter Without Plugin Test Discovery

**What goes wrong:** Running `vendor/bin/phpunit --filter TestName` finds and runs a test by name but does not guarantee it discovered the test from the correct plugin namespace. If a test name exists in both core and a plugin, `--filter` may run the wrong one.

**Why it happens:** `--filter` is regex-matched against test method names globally. Ambiguous test names across plugins can match multiple classes.

**Consequences:** Developer believes a specific plugin test passed when actually a different test with the same name ran.

**Prevention:**
- Use the full file path when targeting a specific test: `vendor/bin/phpunit tests/TestCase/Controller/MembersControllerTest.php`.
- For plugin tests: `vendor/bin/phpunit plugins/Awards/tests/TestCase/Controller/RecommendationsControllerTest.php`.

**Detection:** Check the "Tests" count in phpunit output — if it says 1 but you expected 3, another class was matched.

**Phase/area affected:** Day-to-day TDD workflow.

---

### Moderate: Skipped Tests Hiding Untested Security Paths

**What goes wrong:** The `PermissionsLoader` test suite is marked as skip. The `WarrantManager` tests are mostly skipped. A developer adds a code path through `PermissionsLoader` or `WarrantManager` and the tests give no signal because the entire test class is marked skip.

**Why it happens:** Tests were skipped because they required fixtures that were not set up, or because the test was written as a placeholder. The skip masks the gap.

**Consequences:** Changes to the authorization loading pipeline (`PermissionsLoader`) or warrant lifecycle (`WarrantManager`) ship with zero automated coverage. These are the highest-risk areas of the codebase.

**Prevention:**
- Do not add skips to test methods without a `// TODO:` comment explaining what is needed to un-skip.
- Any change to `PermissionsLoader` or `WarrantManager` must be accompanied by a test — even a simple smoke test. Do not accept the existing skip as coverage.
- Track the number of skipped tests as a metric; it should not increase.

**Detection:** `composer test -- --testdox` shows `S` (skipped) markers. `grep -r 'markTestSkipped\|$this->markTestIncomplete' tests/ plugins/*/tests/` shows the full list.

**Phase/area affected:** Authorization loading, warrant creation/approval, permission checking.

---

## Deployment Pitfalls

### CRITICAL: Deploying Without Running webpack prod Build

**What goes wrong:** A release is cut (Docker image built) without running `npm run prod`. The image ships with either no compiled assets or with development-mode assets (unminified, with source maps, different content hashes). The `mix-manifest.json` version hash used by CakePHP's `AssetMix` helper does not match the actual files, causing 404s for versioned assets.

**Why it happens:** `npm run prod` is part of `bin/verify.sh` but must be run explicitly before building the Docker image. If `verify.sh` is skipped (e.g., during a hotfix), assets may not be rebuilt.

**Consequences:** Users see broken pages with missing CSS and JS. The error manifests as 404s for versioned filenames like `/js/controllers.js?id=abc123`.

**Prevention:**
- The Docker build process (`Dockerfile`) must include `npm run prod` as a build step. Verify this is present and not commented out.
- `bin/verify.sh` runs webpack and is the pre-commit gate. Never push a release commit without running `verify.sh`.
- Check `webroot/mix-manifest.json` is committed and matches the built assets.

**Detection:** 404 errors for `/js/*.js` in the browser. `mix-manifest.json` hash mismatch.

**Phase/area affected:** Release pipeline, Docker image build.

---

### CRITICAL: Misunderstanding configVersion and AppSettings Auto-Update

**What goes wrong:** A developer changes the `$currentConfigVersion` string in `Application::bootstrap()` without understanding that this triggers `StaticHelpers::getAppSetting()` calls for every setting listed in that block on the next request. If new settings are listed but the corresponding DB migration to add them to `app_settings` has not run, the `getAppSetting()` call creates the row with the default value — overwriting any kingdom-specific configuration that was manually set.

**Why it happens:** The version-bump pattern looks like a cache invalidation mechanism. The side effect of re-seeding default settings is not obvious from the code.

**Consequences:** Kingdom administrators lose their customized settings (site title, logo, timezone, etc.) on upgrade if a migration is not run first, or if default values are changed in the config block.

**Prevention:**
- Only bump `$currentConfigVersion` when you intentionally want all settings in that block to be re-evaluated/defaulted.
- New settings should be added to the config block with their correct defaults; existing settings should not have their default values changed in this block.
- Migrations that add new `app_settings` rows should run before the config version bump takes effect.
- Document the intent of each `getAppSetting()` call in the bootstrap block.

**Detection:** Kingdom admins report loss of customization after an upgrade. Settings revert to defaults.

**Phase/area affected:** Any upgrade deployment, especially when `Application::bootstrap()` is modified.

---

### CRITICAL: Plugin Not Registered in plugins.php After Creation

**What goes wrong:** A new plugin's PHP classes and templates are created under `app/plugins/PluginName/` but the plugin is not added to `app/config/plugins.php`. The plugin's `bootstrap()` method never runs — so `NavigationRegistry::register()` and `ViewCellRegistry::register()` calls are never made, and the plugin's Table classes are never loaded.

**Why it happens:** The template plugin in `plugins/Template/` is commented out in `plugins.php` as an example. A developer may think this file is auto-discovered.

**Consequences:** Plugin is completely invisible — no navigation, no view cells, no routes, no migrations applied. The failure is silent (no error), which makes it hard to diagnose.

**Prevention:**
- After creating any plugin, immediately add it to `app/config/plugins.php` with the correct `migrationOrder` if it has migrations.
- Run `bin/cake routes` to verify plugin routes are visible.
- Run the full test suite to catch missing plugin bootstrap.

**Detection:** Plugin pages return 404 (routes not registered). No navigation items appear from the plugin.

**Phase/area affected:** Plugin creation or re-enabling disabled plugins.

---

### Moderate: Cache Not Cleared After Migrations

**What goes wrong:** After running database migrations that add or rename columns, CakePHP's model cache (`_cake_model_`) still holds the old schema. The ORM uses the cached schema and tries to read/write columns that no longer exist or ignores new columns.

**Why it happens:** CakePHP caches introspected table schemas to avoid DB round-trips. The migration runner does not automatically clear this cache.

**Consequences:** After a migration, queries may throw SQL errors for missing columns, or newly added columns are silently ignored until the cache expires.

**Prevention:**
- Run `bin/cake cache clear_all` after every `bin/cake migrations migrate`.
- In the dev environment, schema cache TTL is typically short — but in production the cache may be Redis/Memcached with a longer TTL.
- The `Application::bootstrap()` config version bump also clears `_cake_model_` — but only if the version actually changed.

**Detection:** SQL errors mentioning unknown columns immediately after a migration. New columns not visible in grid views.

**Phase/area affected:** Every migration deployment.

---

### Minor: laravel-mix Patch Dependency Fragility

**What goes wrong:** `laravel-mix` v6 is EOL and requires a custom patch maintained in the project. Running `npm install` or `npm ci` without the patch in place (e.g., on a fresh clone that doesn't pick up `patch-package` post-install hook) produces a build that silently fails or produces incorrect output.

**Why it happens:** The patch is applied via `patch-package` in the `postinstall` hook. If the hook fails silently or a developer bypasses it, the patched behavior is missing.

**Consequences:** Webpack build fails or produces a broken bundle.

**Prevention:**
- Always use `npm ci` (not `npm install`) in CI and Docker builds to ensure reproducible installs.
- If `npm run dev` fails after a fresh install, verify `patch-package` ran by checking if the patch was applied.
- Long-term mitigation: migrate from laravel-mix to Vite (tracked as a known improvement in CONCERNS.md).

**Detection:** Webpack build errors referencing laravel-mix internals. `npm ls laravel-mix` shows version without patch marker.

**Phase/area affected:** Fresh developer environments, Docker image builds, CI.

---

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Mitigation |
|-------------|---------------|------------|
| Any policy change | LSP violation from type hint addition | Read BasePolicy before touching any policy file |
| New controller action | Missing authorize() call | Use non-super-user for manual test immediately |
| New entity type | Missing getBranchId() override | Check branch relationship and write branch-scoped test |
| Migration work | Wrong plugin migration order | Follow migrationOrder in plugins.php, use reset_dev_database.sh as reference |
| ActiveWindow date editing | Status/date desync | Always use start()/expire() methods, never set fields directly |
| JS/CSS changes | Stale webpack bundle | Run npm run dev before testing, npm run prod before commit |
| New Stimulus controller | Missing window.Controllers registration | Use popover-controller.js as template |
| New plugin | Missing plugins.php registration | Verify with bin/cake routes |
| Deployment | Stale model cache | bin/cake cache clear_all after every migration |
| Deployment | AppSettings defaults overwritten | Only bump configVersion intentionally; migrate first |
| Any PHP change | PHPStan baseline growth | Do not add to phpstan-baseline.neon without a documented reason |
| Test run | --testsuite all false confidence | Use composer test for full suite |
| Any security-adjacent change | PermissionsLoader/WarrantManager untested | Write at least a smoke test before shipping |

---

*Sources: Direct codebase analysis of `app/src/Policy/BasePolicy.php`, `app/src/Application.php`, `app/src/Services/ViewCellRegistry.php`, `app/src/Services/NavigationRegistry.php`, `app/src/Model/Entity/ActiveWindowBaseEntity.php`, `app/src/Model/Table/BaseTable.php`, `app/src/Controller/DataverseGridTrait.php`, `app/assets/js/index.js`, `app/webpack.mix.js`, `app/phpunit.xml.dist`, `app/config/plugins.php`, `.planning/codebase/ARCHITECTURE.md`, `.planning/codebase/CONCERNS.md`, `CLAUDE.md`.*
