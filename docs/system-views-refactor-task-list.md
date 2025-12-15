# System Views Refactor Task List (DVTables)

_Last updated: 2025-12-14_

## Goal
Standardize Dataverse grid **system views** so that:
- System views are defined in the relevant `*GridColumns` class via `public static function getSystemViews(array $options = []): array`.
- Controllers retrieve system views from GridColumns and pass them into `processDataverseGrid([... 'systemViews' => ...])`.
- System view configs use `config['columns']` (not `visibleColumns`).

## Conventions
- Always call `GridColumns::getSystemViews([])` (or with contextual options), even if options are empty.
- System view IDs are unique per grid.
- Dynamic date/time-based views are computed inside `getSystemViews()`.

## Current migration status
Repository scan (2025-12-14) indicates:
- All controllers that pass `systemViews` are now sourcing them from `*GridColumns::getSystemViews($options)`.
- No remaining controller-owned system view arrays or `get*SystemViews()` helper methods were found.
- All 11 GridColumns with custom system views have been audited:
  - Correct signature: `getSystemViews(array $options = []): array`
  - Using `config['columns']` (not `visibleColumns`)
  - Controllers properly call `GridColumns::getSystemViews([...])` and pass to `processDataverseGrid`
- **Remaining work**: Decide/add new system views for grids that currently run without any.

---

## Completed migrations (system views now live in GridColumns)

- [x] Warrants grid: [app/src/Controller/WarrantsController.php](../app/src/Controller/WarrantsController.php) → [app/src/KMP/GridColumns/WarrantsGridColumns.php](../app/src/KMP/GridColumns/WarrantsGridColumns.php)
- [x] Warrant Rosters grid: [app/src/Controller/WarrantRostersController.php](../app/src/Controller/WarrantRostersController.php) → [app/src/KMP/GridColumns/WarrantRostersGridColumns.php](../app/src/KMP/GridColumns/WarrantRostersGridColumns.php)
- [x] Gatherings grid: [app/src/Controller/GatheringsController.php](../app/src/Controller/GatheringsController.php) → [app/src/KMP/GridColumns/GatheringsGridColumns.php](../app/src/KMP/GridColumns/GatheringsGridColumns.php)
- [x] Member Roles sub-grid: [app/src/Controller/MembersController.php](../app/src/Controller/MembersController.php) → [app/src/KMP/GridColumns/MemberRolesGridColumns.php](../app/src/KMP/GridColumns/MemberRolesGridColumns.php)
- [x] Member Gathering Attendances sub-grid: [app/src/Controller/MembersController.php](../app/src/Controller/MembersController.php) → [app/src/KMP/GridColumns/GatheringAttendancesGridColumns.php](../app/src/KMP/GridColumns/GatheringAttendancesGridColumns.php)
- [x] Verify Queue grid: [app/src/Controller/MembersController.php](../app/src/Controller/MembersController.php) → [app/src/KMP/GridColumns/VerifyQueueGridColumns.php](../app/src/KMP/GridColumns/VerifyQueueGridColumns.php)
- [x] Officers grid (context-sensitive): [app/plugins/Officers/src/Controller/OfficersController.php](../app/plugins/Officers/src/Controller/OfficersController.php) → [app/plugins/Officers/src/KMP/GridColumns/OfficersGridColumns.php](../app/plugins/Officers/src/KMP/GridColumns/OfficersGridColumns.php)
- [x] Member Authorizations grid: [app/plugins/Activities/src/Controller/AuthorizationsController.php](../app/plugins/Activities/src/Controller/AuthorizationsController.php) → [app/plugins/Activities/src/KMP/GridColumns/MemberAuthorizationsGridColumns.php](../app/plugins/Activities/src/KMP/GridColumns/MemberAuthorizationsGridColumns.php)
- [x] Authorization Approvals grids: [app/plugins/Activities/src/Controller/AuthorizationApprovalsController.php](../app/plugins/Activities/src/Controller/AuthorizationApprovalsController.php) → [app/plugins/Activities/src/KMP/GridColumns/AuthorizationApprovalsGridColumns.php](../app/plugins/Activities/src/KMP/GridColumns/AuthorizationApprovalsGridColumns.php)
- [x] Waiver Types grid: [app/plugins/Waivers/src/Controller/WaiverTypesController.php](../app/plugins/Waivers/src/Controller/WaiverTypesController.php) → [app/plugins/Waivers/src/KMP/GridColumns/WaiverTypesGridColumns.php](../app/plugins/Waivers/src/KMP/GridColumns/WaiverTypesGridColumns.php)
- [x] Recommendations grids (all contexts): [app/plugins/Awards/src/Controller/RecommendationsController.php](../app/plugins/Awards/src/Controller/RecommendationsController.php) → [app/plugins/Awards/src/KMP/GridColumns/RecommendationsGridColumns.php](../app/plugins/Awards/src/KMP/GridColumns/RecommendationsGridColumns.php)

---

## Remaining controller + GridColumns audits (no system views currently wired)

These controllers call `processDataverseGrid(...)` but do **not** currently provide `systemViews`.
For each, decide whether to (A) add system views or (B) explicitly keep none.

### Core app (`app/src`)
- [x] Members index grid: [app/src/Controller/MembersController.php](../app/src/Controller/MembersController.php) + [app/src/KMP/GridColumns/MembersGridColumns.php](../app/src/KMP/GridColumns/MembersGridColumns.php) ✓ Added status-based views (Active, Verified, Minors, All)
- [ ] App Settings grid: [app/src/Controller/AppSettingsController.php](../app/src/Controller/AppSettingsController.php) + [app/src/KMP/GridColumns/AppSettingsGridColumns.php](../app/src/KMP/GridColumns/AppSettingsGridColumns.php) - Simple config, no views needed
- [x] Branches grid: [app/src/Controller/BranchesController.php](../app/src/Controller/BranchesController.php) + [app/src/KMP/GridColumns/BranchesGridColumns.php](../app/src/KMP/GridColumns/BranchesGridColumns.php) ✓ Added views (With Members, All)
- [x] Email Templates grid: [app/src/Controller/EmailTemplatesController.php](../app/src/Controller/EmailTemplatesController.php) + [app/src/KMP/GridColumns/EmailTemplatesGridColumns.php](../app/src/KMP/GridColumns/EmailTemplatesGridColumns.php) ✓ Added views (Active, Inactive, All)
- [ ] Gathering Activities grid: [app/src/Controller/GatheringActivitiesController.php](../app/src/Controller/GatheringActivitiesController.php) + [app/src/KMP/GridColumns/GatheringActivitiesGridColumns.php](../app/src/KMP/GridColumns/GatheringActivitiesGridColumns.php) - Simple lookup, no views needed
- [ ] Gathering Types grid: [app/src/Controller/GatheringTypesController.php](../app/src/Controller/GatheringTypesController.php) + [app/src/KMP/GridColumns/GatheringTypesGridColumns.php](../app/src/KMP/GridColumns/GatheringTypesGridColumns.php) - Simple lookup, no views needed
- [x] Permissions grid: [app/src/Controller/PermissionsController.php](../app/src/Controller/PermissionsController.php) + [app/src/KMP/GridColumns/PermissionsGridColumns.php](../app/src/KMP/GridColumns/PermissionsGridColumns.php) ✓ Added views (System, Custom, Warrant Required, All)
- [x] Roles grid: [app/src/Controller/RolesController.php](../app/src/Controller/RolesController.php) + [app/src/KMP/GridColumns/RolesGridColumns.php](../app/src/KMP/GridColumns/RolesGridColumns.php) ✓ Added views (System Roles, Custom Roles, All)
- [ ] Warrant Periods grid: [app/src/Controller/WarrantPeriodsController.php](../app/src/Controller/WarrantPeriodsController.php) + [app/src/KMP/GridColumns/WarrantPeriodsGridColumns.php](../app/src/KMP/GridColumns/WarrantPeriodsGridColumns.php) - Simple date ranges, no views needed

### Activities plugin (`app/plugins/Activities`)
- [x] Activities grid: [app/plugins/Activities/src/Controller/ActivitiesController.php](../app/plugins/Activities/src/Controller/ActivitiesController.php) + [app/plugins/Activities/src/KMP/GridColumns/ActivitiesGridColumns.php](../app/plugins/Activities/src/KMP/GridColumns/ActivitiesGridColumns.php) ✓ Added views (All)
- [ ] Activity Groups grid: [app/plugins/Activities/src/Controller/ActivityGroupsController.php](../app/plugins/Activities/src/Controller/ActivityGroupsController.php) + [app/plugins/Activities/src/KMP/GridColumns/ActivityGroupsGridColumns.php](../app/plugins/Activities/src/KMP/GridColumns/ActivityGroupsGridColumns.php) - Simple lookup, no views needed
- [ ] Authorization Approver Rollup grid (confirm intentionally no system views): [app/plugins/Activities/src/Controller/AuthorizationApprovalsController.php](../app/plugins/Activities/src/Controller/AuthorizationApprovalsController.php) + [app/plugins/Activities/src/KMP/GridColumns/AuthorizationApproverRollupGridColumns.php](../app/plugins/Activities/src/KMP/GridColumns/AuthorizationApproverRollupGridColumns.php) - Aggregated view, no views needed

### Awards plugin (`app/plugins/Awards`)
- [ ] Awards grid: [app/plugins/Awards/src/Controller/AwardsController.php](../app/plugins/Awards/src/Controller/AwardsController.php) + [app/plugins/Awards/src/KMP/GridColumns/AwardsGridColumns.php](../app/plugins/Awards/src/KMP/GridColumns/AwardsGridColumns.php) - Simple lookup, no views needed
- [ ] Domains grid: [app/plugins/Awards/src/Controller/DomainsController.php](../app/plugins/Awards/src/Controller/DomainsController.php) + [app/src/KMP/GridColumns/DomainsGridColumns.php](../app/src/KMP/GridColumns/DomainsGridColumns.php) - Simple lookup, no views needed
- [ ] Levels grid: [app/plugins/Awards/src/Controller/LevelsController.php](../app/plugins/Awards/src/Controller/LevelsController.php) + [app/src/KMP/GridColumns/LevelsGridColumns.php](../app/src/KMP/GridColumns/LevelsGridColumns.php) - Simple lookup, no views needed

### Officers plugin (`app/plugins/Officers`)
- [ ] Departments grid: [app/plugins/Officers/src/Controller/DepartmentsController.php](../app/plugins/Officers/src/Controller/DepartmentsController.php) + [app/src/KMP/GridColumns/DepartmentsGridColumns.php](../app/src/KMP/GridColumns/DepartmentsGridColumns.php) - Simple lookup, no views needed
- [x] Offices grid: [app/plugins/Officers/src/Controller/OfficesController.php](../app/plugins/Officers/src/Controller/OfficesController.php) + [app/src/KMP/GridColumns/OfficesGridColumns.php](../app/src/KMP/GridColumns/OfficesGridColumns.php) ✓ Added views (Required, Warrant Required, All)

---

## GridColumns status (custom `getSystemViews()`)

### Has custom system views now
- [x] [app/src/KMP/GridColumns/WarrantsGridColumns.php](../app/src/KMP/GridColumns/WarrantsGridColumns.php)
- [x] [app/src/KMP/GridColumns/WarrantRostersGridColumns.php](../app/src/KMP/GridColumns/WarrantRostersGridColumns.php)
- [x] [app/src/KMP/GridColumns/GatheringsGridColumns.php](../app/src/KMP/GridColumns/GatheringsGridColumns.php)
- [x] [app/src/KMP/GridColumns/MemberRolesGridColumns.php](../app/src/KMP/GridColumns/MemberRolesGridColumns.php)
- [x] [app/src/KMP/GridColumns/GatheringAttendancesGridColumns.php](../app/src/KMP/GridColumns/GatheringAttendancesGridColumns.php)
- [x] [app/src/KMP/GridColumns/VerifyQueueGridColumns.php](../app/src/KMP/GridColumns/VerifyQueueGridColumns.php)
- [x] [app/plugins/Activities/src/KMP/GridColumns/MemberAuthorizationsGridColumns.php](../app/plugins/Activities/src/KMP/GridColumns/MemberAuthorizationsGridColumns.php)
- [x] [app/plugins/Activities/src/KMP/GridColumns/AuthorizationApprovalsGridColumns.php](../app/plugins/Activities/src/KMP/GridColumns/AuthorizationApprovalsGridColumns.php)
- [x] [app/plugins/Waivers/src/KMP/GridColumns/WaiverTypesGridColumns.php](../app/plugins/Waivers/src/KMP/GridColumns/WaiverTypesGridColumns.php)
- [x] [app/plugins/Awards/src/KMP/GridColumns/RecommendationsGridColumns.php](../app/plugins/Awards/src/KMP/GridColumns/RecommendationsGridColumns.php)
- [x] [app/plugins/Officers/src/KMP/GridColumns/OfficersGridColumns.php](../app/plugins/Officers/src/KMP/GridColumns/OfficersGridColumns.php)
- [x] [app/src/KMP/GridColumns/MembersGridColumns.php](../app/src/KMP/GridColumns/MembersGridColumns.php) ✓ NEW
- [x] [app/src/KMP/GridColumns/BranchesGridColumns.php](../app/src/KMP/GridColumns/BranchesGridColumns.php) ✓ NEW
- [x] [app/src/KMP/GridColumns/EmailTemplatesGridColumns.php](../app/src/KMP/GridColumns/EmailTemplatesGridColumns.php) ✓ NEW
- [x] [app/src/KMP/GridColumns/PermissionsGridColumns.php](../app/src/KMP/GridColumns/PermissionsGridColumns.php) ✓ NEW
- [x] [app/src/KMP/GridColumns/RolesGridColumns.php](../app/src/KMP/GridColumns/RolesGridColumns.php) ✓ NEW
- [x] [app/src/KMP/GridColumns/OfficesGridColumns.php](../app/src/KMP/GridColumns/OfficesGridColumns.php) ✓ NEW
- [x] [app/plugins/Activities/src/KMP/GridColumns/ActivitiesGridColumns.php](../app/plugins/Activities/src/KMP/GridColumns/ActivitiesGridColumns.php) ✓ NEW

### No custom system views (simple lookups - intentionally no views)
- [x] [app/src/KMP/GridColumns/AppSettingsGridColumns.php](../app/src/KMP/GridColumns/AppSettingsGridColumns.php) - Simple config table
- [x] [app/src/KMP/GridColumns/GatheringActivitiesGridColumns.php](../app/src/KMP/GridColumns/GatheringActivitiesGridColumns.php) - Simple lookup
- [x] [app/src/KMP/GridColumns/GatheringTypesGridColumns.php](../app/src/KMP/GridColumns/GatheringTypesGridColumns.php) - Simple lookup
- [x] [app/src/KMP/GridColumns/WarrantPeriodsGridColumns.php](../app/src/KMP/GridColumns/WarrantPeriodsGridColumns.php) - Date ranges
- [x] [app/src/KMP/GridColumns/DepartmentsGridColumns.php](../app/src/KMP/GridColumns/DepartmentsGridColumns.php) - Simple lookup
- [x] [app/src/KMP/GridColumns/DomainsGridColumns.php](../app/src/KMP/GridColumns/DomainsGridColumns.php) - Simple lookup
- [x] [app/src/KMP/GridColumns/LevelsGridColumns.php](../app/src/KMP/GridColumns/LevelsGridColumns.php) - Simple lookup
- [x] [app/plugins/Awards/src/KMP/GridColumns/AwardsGridColumns.php](../app/plugins/Awards/src/KMP/GridColumns/AwardsGridColumns.php) - Simple lookup
- [x] [app/plugins/Activities/src/KMP/GridColumns/ActivityGroupsGridColumns.php](../app/plugins/Activities/src/KMP/GridColumns/ActivityGroupsGridColumns.php) - Simple lookup
- [x] [app/plugins/Activities/src/KMP/GridColumns/AuthorizationApproverRollupGridColumns.php](../app/plugins/Activities/src/KMP/GridColumns/AuthorizationApproverRollupGridColumns.php) - Aggregated view

---

## Per-item definition of done
For any row you decide should have system views:
- [ ] Add `getSystemViews(array $options = []): array` to the GridColumns class.
- [ ] Controller uses `GridColumns::getSystemViews([...])` and passes it as `'systemViews' => $systemViews`.
- [ ] Pick a `defaultSystemView`.
- [ ] Ensure system view config uses `config['columns']`.
