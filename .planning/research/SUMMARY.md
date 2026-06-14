# Research Summary — KMP Maintenance

## TL;DR

- **Authorization is the highest-risk area.** Three critical pitfalls (BasePolicy LSP violation, missing `authorize()` calls, unrestricted `scopeIndex`) all lead to fatal errors or silent data exposure. `PermissionsLoader` — which loads all permission data — has zero test coverage.
- **Small plumbing fixes deliver outsized UX value.** Flash messages lost in Turbo Frames, validation errors swallowed by modals, timezone inconsistency, and the `StaticHelpers` N+1 query are all high-friction daily issues with low implementation cost and no architectural risk.
- **The architecture has clear, enforced seams.** Plugin → Core → Framework is the one-way dependency direction. All seven safe-change patterns must be followed on every commit — they are not suggestions.
- **Two runtime EOLs are overdue.** Node 18 EOL'd April 2025. `composer audit` is absent from CI, leaving the PHP supply chain unmonitored. Both are one-line fixes.
- **The test suite has a known false-confidence trap.** `--testsuite all` only runs ~half the tests. Every phase must use `composer test` as the pass/fail gate.

---

## Stack

- **Node 18 is EOL (April 2025) — upgrade to Node 22 LTS.** Affects devcontainer and CI only; no application code changes.
- **`composer audit` is missing from CI.** One-line addition to `tests.yml`; highest value-to-effort ratio of any stack action.
- **Laravel Mix is in maintenance mode but must not be migrated yet.** The `webpack.mix.js` auto-discovery config is complex and working. Vite migration cost exceeds benefit in maintenance mode.
- **`erusev/parsedown` (abandoned 2019) is a potential XSS risk.** Audit every `Parsedown::text()` call site. If user-supplied content reaches it, schedule replacement with `league/commonmark`.
- **MariaDB version mismatch between CI (10.x) and dev/prod (11.x).** Pin CI to `mariadb:11` to align environments.
- **Do not change:** CakePHP `^5.0.1` constraint, PHPStan level 5, Bootstrap 5.3, Stimulus 3.2, Turbo 8, `BasePolicy` signatures, Laravel Mix config, `face-api.js` pin.

---

## Table Stakes Quality

**High-friction daily issues (fix first):**

1. **Flash messages silently lost in Turbo Frame contexts.** Most modal-based saves use `redirect($this->referer())`; the flash div is outside the Turbo Frame boundary. Only `AppSettingsController` has the correct `turbo_stream_flash` pattern. Affects Officers, Activities, and Awards on every save.
2. **Modal validation errors not shown consistently.** `MembersController` has a bespoke session-patching workaround; Officers and Awards modals do not. Failed saves dismiss the modal with no error.
3. **Timezone inconsistency in templates.** `i18nFormat()` used directly in at least one template instead of `TimezoneHelper`. Grep for `i18nFormat` across templates and convert.
4. **Member status shown as raw PHP constant string** (`active`, `unverified_minor`) rather than a human-readable badge. One-line fix using the existing badge column type in `MembersGridColumns.php`.
5. **Empty state grid messages are generic.** "No records found." appears whether filters are active or the table is genuinely empty.

**Medium-priority code quality (fix second):**

6. **`StaticHelpers::getAppSetting()` has no cache.** Causes 10–20+ extra DB queries per page on heavily-templated pages. Add a request-scoped static cache.
7. **`Member::publicData()` has 15+ unresolved privacy TODOs.** Contact info and birth date may be returned without privacy gating to award recommendation submitters.
8. **Two tab systems coexist** (`activeWindowTabs.php` vs `turboActiveTabs.php`). Inconsistent UX; maintenance overhead.

**Anti-improvements to avoid:** profile photos in grid columns, real-time grid auto-refresh, global `phpcbf` run, enabling Turbo Drive.

---

## Architecture Constraints

**Non-negotiable safe-change rules:**

1. **Never add native type hints to `BasePolicy` overridable methods** (`canAdd`, `canEdit`, `canDelete`, `canView`, `canIndex`, `scopeIndex`). PHP fatal error for any request reaching a plugin policy override.
2. **All service methods must return `ServiceResult`** — not raw booleans, not entities, not exceptions for expected failures.
3. **Migration order is Activities=1, Officers=2, Awards=3, Waivers=4.** A plugin migration may reference tables from earlier-ordered plugins only.
4. **Mailers must receive pre-formatted date strings, never `DateTime` objects.** Call `TimezoneHelper::formatDate()` / `formatDateTime()` in the controller or service layer.
5. **Plugin → Core → Framework is the only valid dependency direction.** The single known violation (`Member.php` using `MemberAuthorizationsTrait`) is tracked tech debt; do not add more.
6. **`ViewCellRegistry` and `NavigationRegistry` are static.** Call `::reset()` / `::clear()` in `tearDown()` for any test that triggers plugin bootstrap.
7. **`DataverseGridTrait` + `GridColumns` class is the gating contract for all list pages.**

**High-risk files:** `MembersController` (2702 lines), `RecommendationsController` (2379 lines) — extract only the section you must touch into a service. `PermissionsLoader` is completely untested.

**Build order for multi-file fixes:** migrations → model/table/entity → policies → services → controllers → templates → GridColumns → plugin bootstrap → frontend JS → tests.

---

## Pitfalls

**Critical (will break the app or expose data):**

1. **BasePolicy type hints** — PHP fatal errors in all plugin policy subclasses. Triggered by PHPStan suggestion or `phpcbf` run.
2. **Missing `authorize()` in new controller actions** — `AuthorizationRequiredException` fires after logic already ran.
3. **`scopeIndex()` returning unrestricted queries** — inheriting without overriding silently returns all records.
4. **ActiveWindow date mutation bypassing the behavior** — direct `start_on`/`expires_on` assignment desynchronizes `status`.
5. **Running `--testsuite all` and trusting the result** — covers only ~half the tests.
6. **`configVersion` bump overwriting kingdom settings** — re-seeds all defaults; only bump intentionally, migration first.

**Moderate:**

7. **`getBranchId()` not overridden on new entities** — authorization silently grants access to out-of-branch records.
8. **`StaticHelpers::getAppSetting()` N+1 pattern** in templates.
9. **ViewCellRegistry order collision** — two plugins with the same `order` value silently removes one tab.
10. **Stale model cache after migrations** — run `bin/cake cache clear_all` after every migrate.
11. **`webpack.mix.js` build not run before commit** — stale assets cause `mix-manifest.json` hash mismatch → 404s.

---

## Priority Order

**Phase 1 — Infrastructure Hardening** (zero app-code risk, high safety value)
- Add `composer audit` to CI
- Upgrade Node 18 → 22 LTS in devcontainer and CI
- Pin MariaDB CI image to `mariadb:11`
- Audit `Parsedown` call sites

**Phase 2 — Authorization Safety Net** (highest risk area, zero test coverage)
- Write smoke tests for `PermissionsLoader`
- Write tests for `WarrantManager` paths
- Audit `Member::publicData()` privacy TODOs
- Add at least one non-super-user controller test per plugin

**Phase 3 — Daily UX Friction** (high user-facing impact, low implementation risk)
- Standardize Turbo Stream flash responses across Officers, Activities, Awards
- Propagate modal validation error pattern from Members to Officers/Awards
- Convert `i18nFormat()` template usages to `TimezoneHelper`
- Apply human-readable status badges to member status
- Differentiate empty-state grid messages (filtered vs. genuinely empty)

**Phase 4 — Performance and Code Quality** (medium impact, medium effort)
- Add request-scoped static cache to `StaticHelpers::getAppSetting()`
- Add `Log::error()` to `SortableBehavior` silent failures
- Migrate `activeWindowTabs.php` usages to `turboActiveTabs.php`
- Strangler-fig extraction from god controllers as other work touches them

**Phase 5 — Stack Hygiene** (low urgency, scheduled)
- Plan PHP 8.4 upgrade (Docker base image + CI workflow)
- Evaluate PHPUnit 11 upgrade when 10 approaches EOL (late 2026)

---

## Universal Phase Gates

Every phase must satisfy these before the work is considered done:

1. `composer test` passes (not `--testsuite all`).
2. `bash bin/verify.sh` passes — covers PHPUnit, Jest, webpack build, PHPCS (changed files only), PHPStan.
3. PHPStan baseline (`phpstan-baseline.neon`) must not grow.
4. No native type hints added to `BasePolicy` overridable methods.
5. No new upward plugin imports in `app/src/` — run `grep -rn "use Activities\\|use Awards\\|use Officers\\|use Waivers" app/src/` before committing.
6. New controller actions must have an `authorize()` call — tested with a non-super-user account.
7. JS/CSS changes compiled — `npm run dev` before testing, `npm run prod` before committing.
8. Migrations follow plugin order (Activities=1, Officers=2, Awards=3, Waivers=4) and are followed by `bin/cake cache clear_all`.
9. ActiveWindow entities use `start()`/`expire()` methods — never direct field assignment.
10. New Stimulus controllers use the `window.Controllers` registration guard.
