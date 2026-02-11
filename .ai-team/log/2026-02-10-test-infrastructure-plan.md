# Session: Test Infrastructure Plan

- **Date:** 2026-02-10
- **Requested by:** Josh Handel

## Who Worked

- **Jayne (Tester):** Ran full test suite, performed deep infrastructure assessment
- **Mal (Lead):** Created 6-phase attack plan from Jayne's findings

## What Was Done

1. Jayne executed all 4 test suites (core-unit, core-feature, plugins, all). Only 2 of 4 suites run — plugins and all crash on a namespace collision in Waivers plugin. Of 280 tests in runnable suites, 230 pass. 7 authorization tests fail consistently. 73% of test files lack transaction wrapping.
2. Jayne identified 6 infrastructure blockers: namespace collision, missing transaction wrapping (64/88 files), duplicate test files, wrong BaseTestCase constants (KINGDOM_BRANCH_ID=1 and TEST_BRANCH_LOCAL_ID=1073 reference nonexistent records), bootstrap warnings, and 7 consistent authorization test failures.
3. Jayne assessed test quality: 54 markTestIncomplete tests (37 are auto-generated stubs), authentication traits write to DB without cleanup, two competing auth patterns exist.
4. Mal created a 6-phase attack plan with 15 tasks: (1) Make suites runnable, (2) Fix state leakage, (3) Consolidate auth patterns, (4) Investigate auth failures, (5) Remove dead weight, (6) Establish CI pipeline. Estimated 6-8 working days.

## Decisions Made

- Josh directive: No new feature work until testing is solid.
- Auth trait consolidation: Standardize on `TestAuthenticationHelper`, deprecate `AuthenticatedTrait` and `SuperUserAuthenticatedTrait`.
- ViewCell stubs: Delete 12 auto-generated incomplete stubs — they inflate counts while testing nothing.
- BaseTestCase constants: KINGDOM_BRANCH_ID → 2, TEST_BRANCH_LOCAL_ID → 14.
- Queue plugin tests excluded from migration — they have their own fixture system.

## Key Outcomes

- Clear prioritized plan exists: runnability → reliability → coverage
- Infrastructure design (BaseTestCase + transactions + SeedManager) is sound; the problem is adoption
- All decisions logged to `.ai-team/decisions/inbox/` for merge
