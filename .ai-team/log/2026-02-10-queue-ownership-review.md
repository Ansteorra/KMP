# Queue Plugin Ownership Review

**Date:** 2026-02-10
**Requested by:** Josh Handel

## Who Worked

- **Mal** — Architecture review
- **Kaylee** — Deep code review
- **Jayne** — Test triage

## What Was Done

Josh directed the team to "own" the Queue plugin (3rd-party code hosted in-repo, forked from dereuromark/cakephp-queue).

**Mal (Architecture):** Reviewed integration, dependencies, and ownership risks. Plugin is infrastructure-critical — all email flows through it. Already heavily diverged from upstream. Recommends owning and slimming down. Flagged ExecuteTask as high-risk security concern (arbitrary shell command execution).

**Kaylee (Code Review):** Found 22 issues — 2 P0 security, 10 P1, 10 P2. P0: command injection in `terminateProcess()` (unsanitized PID to `exec('kill')`), open redirect in `refererRedirect()` (incomplete URL validation). Notable P1s: broken `getFailedStatus()`, deprecated CakePHP APIs, missing DB indexes, wrong authorization context, silent save failures.

**Jayne (Test Triage):** 119 tests total, 81 failures (68 errors + 13 failures), 38 pass. All failures are infrastructure damage — 0 code bugs. 5 root causes identified. Silver bullets: fixing "plugin already loaded" (16 errors) + missing Admin prefix routes (29 errors) resolves 45 of 68 errors.

## Decisions Made

- Own the Queue plugin permanently; do not re-sync with upstream
- Remove ExecuteTask and example tasks from production
- Fix P0 security issues before other Queue work
- Fix Queue test infrastructure in phases (silver bullets first)
- Do NOT migrate Queue tests to BaseTestCase pattern

## Key Outcomes

- Full architectural, code quality, and test assessment of Queue plugin complete
- Security issues documented and prioritized
- Test fix plan with 5 phases created (silver bullets → autoloading → fixtures → config)
- Correction: Queue tests do NOT "work fine" — 81/119 fail due to KMP integration issues
