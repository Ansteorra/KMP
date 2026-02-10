# KMP Architecture Decisions Log

## 2026-02-10: Queue Plugin — Full Ownership

**Decision:** KMP fully owns the Queue plugin (forked from `dereuromark/cakephp-queue`, MIT license). Upstream divergence is too deep to re-sync. All future maintenance is KMP's responsibility.

**Rationale:** The plugin was already heavily adapted — entities extend BaseEntity, tables extend BaseTable, controllers extend AppController, implements KMPPluginInterface. Only two tasks are used in production: `MailerTask` (async email via `QueuedMailerAwareTrait::queueMail()`) and `EmailTask`.

### Security Fixes Applied (P0)
1. Deleted `ExecuteTask.php` — arbitrary `exec()` from job data
2. Deleted 8 example tasks — demo code discoverable in production
3. Fixed command injection in `terminateProcess()` — numeric validation + int cast on PID
4. Hardened open redirect in `refererRedirect()` — backslash check added

### Code Fixes Applied (P1)
5. Fixed `cleanOldJobs()` — DateTime instead of unix timestamp
6. Fixed `getFailedStatus()` — removed erroneous 'Queue' prefix on task name lookup
7. Fixed `configVersion` not persisted — added `StaticHelpers::setAppSetting()`
8. Fixed wrong authorization context in `QueueProcessesController::index()`
9. Added logging for `markJobDone()`/`markJobFailed()` silent save failures
10. Added `class_exists` guard for Shim dependency in `JsonableBehavior`
11. Replaced deprecated `loadComponent()` with `$this->components()->load()`
12. Replaced deprecated `TableRegistry` in migration with raw SQL

### Cleanup Applied (P2)
13. Fixed policy docblocks
14. Added explicit `getBranchId()` returning null on entities
15. Upgraded worker key entropy to `random_bytes()`
16. Replaced `declare(ticks=1)` with `pcntl_async_signals(true)`
17. Removed broken `clearDoublettes()`

### Test Infrastructure Fixed
- 5 root causes: plugin double-loading, wrong URL prefixes, missing autoload-dev, deleted fixtures, email transport config
- 9 test files deleted (for removed tasks), 22 test files updated
- Result: 210 Queue tests, 0 errors, 0 failures, 6 skips (3 legitimate: missing bake reference files, TestApp task)

---

## 2026-02-10: Test Stub Implementation — Zero Incomplete Tests

**Decision:** All 30 `markTestIncomplete` stubs replaced with real test logic. Zero incomplete tests remain.

**Files:** ImageToPdfConversionService (14), HelloWorld (5), GatheringWaivers (5), Gatherings (2), Officers (3), AuthMiddleware (1)

**Pattern:** Tests that genuinely cannot work (e.g., missing JSON response support in controller) use `markTestSkipped` with clear explanation of what's needed to enable them.
