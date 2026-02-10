# Decision: Skipped Test Triage & Suite Config

**By:** Jayne (Tester)
**Date:** 2026-02-10

## phpunit.xml.dist Suite Assignments

- `ApplicationTest.php` → core-unit (tests Application bootstrapping, not HTTP behavior)
- `tests/TestCase/Command/` → core-feature (CLI commands are feature-level tests)

## Skipped Test Decisions

### Removed as redundant/dead (9 tests):
- Circular reference & root branch deletion tests → tested at model level, controller test adds no value
- EditGet → no edit.php template exists, GET on /members/edit renders nothing
- TransactionIsolation/Rollback → tested in BaseTestCaseTest, not a Members concern
- BakeQueueTaskCommand (both tests) → bake reference files not migrated, bake is not critical for owned plugin
- RunCommandTest::testServiceInjection → requires upstream TestApp/Foo fixture we don't have
- HelloWorldControllerTest::testJsonResponse → demo plugin, JSON responses not needed

### Fixed with real assertions (4 tests):
- testAddPostWithValidData → POST valid member data, assert redirect to view page
- testEditPostWithValidData → POST edit on admin member, assert redirect
- testDelete → DELETE test member, assert redirect (uses TEST_MEMBER_AGATHA_ID)
- testAutoComplete → GET with query, assert HTML list-group-item in response

### Auth blocker:
testAddPostWithValidData and testEditPostWithValidData fail with the same pre-existing `/pages/unauthorized` redirect that affects ALL MembersControllerTest tests. The `SuperUserAuthenticatedTrait` isn't properly authenticating. Once auth is fixed (Phase 5 work), these tests will pass as-is.
