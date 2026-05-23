# Technical Concerns & Known Issues

## Critical Concerns

1. **Parsedown safe mode disabled** in `EmailTemplateRendererService.php:28` — intentional but raw HTML passes through unsanitized in email templates. Risk of HTML injection if template data is user-controlled.

2. **`TableAdminController` executes arbitrary SQL** for super users — full DB access with no query sandboxing. A compromised super-user session has unrestricted database read/write.

3. **Member privacy settings have 13 unimplemented stubs** (`Member.php:194–221`) — `// TODO Check Privacy Settings` comments exist throughout the entity. Privacy filtering is not actually enforced for these fields.

4. **Membership expiry validation commented out** in warrant creation and roster building (two separate locations) — warrants can be created or roster members added without valid SCA membership.

## Technical Debt

| Item | Location | Notes |
|------|----------|-------|
| Waivers Documents table is a stub | `plugins/Waivers/` | Not implemented |
| `GatheringActivityService.hasWaiverLock()` always returns `false` | `plugins/Activities/` | Waiver lock check is non-functional |
| No caching on `AppSettingsTable` | `src/Model/Table/AppSettingsTable.php` | Settings loaded from DB on every request |
| Silenced errors in `SortableBehavior` | `src/Model/Behavior/` | `@` error suppression hides failures |
| Deprecated `CallForCellsHandlerBase` retained | `src/` | Legacy base class kept for BC |
| Deprecated `RecommendationsController::applyHiddenStateVisibility()` not removed | `plugins/Awards/` | Dead method |
| Deprecated `event_id` column in Recommendations | `plugins/Awards/` | Schema debt |
| Officers autocomplete privacy audit TODO | `plugins/Officers/` | Data leakage risk |
| Legacy MD5 password hashing still active | `src/Model/` | Weak hashing for old accounts |
| SHA-1 used for CSRF HMAC | `src/` | Weak HMAC algorithm |
| Plugin/core boundary violated | `GatheringsController:839` | Plugin reaching into core |

## Known Dangerous Patterns

From `CLAUDE.md` — these are intentional behaviors that must not be "fixed":

- **Never run `phpcbf` on the entire codebase** — adds type hints from docblocks that violate PHP's LSP (broke `WarrantsTable::afterSave()` and `ServicePrincipalPolicy::canAdd()` previously).
- **`BasePolicy` methods use untyped `$query`/`$entity` params** — intentional for LSP compatibility with plugins.
- **Quick-login PIN intentionally upserts by `device_id`** across members — reassigns device ownership to current authenticated member by design.
- **`Members/emailTaken` must remain publicly accessible** — used by `member-unique-email` Stimulus controller during anonymous registration.
- **`Members/PublicProfile` response must keep `data.external_links`** — `rec-add-controller.js` reads this key directly.
- **Recommendation states outside Need to Schedule/Scheduled/Given automatically clear `gathering_id`** — intentional, `updateStates` bulk action also nulls `gathering_id`.

## Static Analysis Debt

| Tool | Metric | Detail |
|------|--------|--------|
| PHPStan | Level 5 baseline | `phpstan-baseline.neon` is ~6,139 lines suppressing ~1,023 errors |
| PHPCS | ~3,400 violations | Global pre-existing violations; only changed files checked in CI |
| PHPStan | 1 unbaselinable error | HtmlHelper type covariance — treated as pass in `verify.sh` |

## Dependency Risks

| Package | Risk | Notes |
|---------|------|-------|
| `laravel-mix` v6 | EOL | Requires custom patch; upstream abandoned |
| `popper.js` v1 | Legacy | Bootstrap 5 uses v2 internally; v1 retained for compatibility |
| `face-api.js` ^0.22.2 | Unmaintained | Pinned to prevent quality regression (downgrade to ^0.20.0 weakens detection) |
| `erusev/parsedown` v1.7 | Unmaintained | No v2 stable release; safe mode disabled |
| `guifier` | Low popularity | Low community adoption, maintenance uncertainty |
| 7 npm overrides | Transitive conflicts | `package.json` overrides block automatic resolution |

## Architecture Risks

| Issue | Detail |
|-------|--------|
| **`MembersController` is 2,702 lines** | God controller; high change frequency, merge conflict risk |
| **`RecommendationsController` is 2,379 lines** | Same issue in Awards plugin |
| **`StaticHelpers` as global DB-hitting utility** | Static methods perform DB queries — untestable, no DI |
| **`DataverseGridTrait` is 1,816 lines** | Trait too large; should be a service |
| **PostgreSQL not fully tested in CI** | `nightly.yml` runs Postgres but seed data gaps cause skips |
| **Confusing PHPUnit suite definition** | `--testsuite all` only covers ~half the tests |
| **E2E tests require sudo + manual DB reset** | Not in CI; high friction for contributors |

## Test Coverage Gaps

| Area | Coverage | Risk |
|------|----------|------|
| `GatheringWaivers` controller | Near-zero | Untested waiver flow |
| `WarrantManager` | Mostly skipped | Core business logic under-tested |
| `GatheringTypes` | Skipped | No coverage |
| `PermissionsLoader` | Skipped | **Security risk** — authorization loader untested |
| `Template` plugin | Always skipped | No working tests |
| `Bootstrap` plugin | None | Plugin has no tests at all |
| OpenAPI tests | All skip | Generated API tests do not run |
| JS Stimulus controllers | ~5 of 20+ | Most controllers have no unit tests |

## Improvement Opportunities

- **`AppSettings` caching** — add request-level or short-TTL cache to reduce DB load
- **DI for `AppSettings`** — inject via container instead of static access
- **Seed data gap remediation** — improve PostgreSQL CI coverage by filling seed gaps
- **Dead code cleanup** — remove deprecated methods and commented-out code
- **Build system modernization** — migrate from EOL Laravel Mix to Vite
- **Controller size reduction** — extract `MembersController` and `RecommendationsController` into service classes
