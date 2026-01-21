# Dataverse Grid Filter Audit (Custom Filter Handler Candidates)

Scope: `app/src/KMP/GridColumns/*.php` and `app/plugins/*/src/KMP/GridColumns/*.php`.
Goal: identify filterable columns that do **not** map directly to a column on the primary table and therefore need a `customFilterHandler` (or equivalent join/queryField change).

## Findings by Grid

### MembersGridColumns (`app/src/KMP/GridColumns/MembersGridColumns.php`)
- `branch_id`
  - Why flagged: `queryField` points to `Branches.name` (association). Base query uses `contain()` only.
  - Data source: `Branches` (belongsTo `Members`).
  - Recommendation: **Needs handler or join**. Prefer switching to `Members.branch_id` if the filter values are IDs.
- `parent_id`
  - Why flagged: `queryField` points to `Parents.sca_name` (association). Base query uses `contain()` only.
  - Data source: `Parents` (self-relation).
  - Recommendation: **Needs handler or join**. Prefer switching to `Members.parent_id` if the filter values are IDs.
- `warrantable`
  - Why flagged: virtual/computed (no DB column). Auto-filter would generate `Members.warrantable`.
  - Data source: computed in `Member::warrantableReview()`.
  - Recommendation: **Needs customFilterHandler** to map to the correct eligibility logic.

### VerifyQueueGridColumns (`app/src/KMP/GridColumns/VerifyQueueGridColumns.php`)
- `branch_id`
  - Why flagged: `queryField` points to `Branches.name` (association). Base query uses `contain()` only.
  - Data source: `Branches` (belongsTo `Members`).
  - Recommendation: **Needs handler or join**, or change to `Members.branch_id`.

### GatheringsGridColumns (`app/src/KMP/GridColumns/GatheringsGridColumns.php`)
- `branch_id`
  - Why flagged: `queryField` points to `Branches.id` (association). Base query uses `contain()` only.
  - Data source: `Branches` (belongsTo `Gatherings`).
  - Recommendation: **Needs handler or join**, or change to `Gatherings.branch_id`.
- `gathering_type_id`
  - Why flagged: `queryField` points to `GatheringTypes.id` (association). Base query uses `contain()` only.
  - Data source: `GatheringTypes` (belongsTo `Gatherings`).
  - Recommendation: **Needs handler or join**, or change to `Gatherings.gathering_type_id`.
- `activity_filter`
  - Why flagged: filters on `GatheringActivities.id` (BelongsToMany).
  - Data source: `GatheringActivities` via join table.
  - Recommendation: **Probably OK** because the base query already calls `leftJoinWith('GatheringActivities')`. Keep an eye on this if the base query changes.

### GatheringAttendancesGridColumns (`app/src/KMP/GridColumns/GatheringAttendancesGridColumns.php`)
- `gathering_type`
  - Why flagged: `queryField` points to `GatheringTypes.name` (association). Base query uses `contain()` only.
  - Data source: `GatheringTypes` (via `Gatherings`).
  - Recommendation: **Needs handler or join**.
- `start_date`, `end_date`
  - Why flagged: `queryField` points to `Gatherings.start_date` / `Gatherings.end_date` (association). Base query uses `contain()` only.
  - Data source: `Gatherings` (belongsTo `GatheringAttendances`).
  - Recommendation: **Needs handler or join** (system views use these even when `canFilter` is false).

### MemberRolesGridColumns (`app/src/KMP/GridColumns/MemberRolesGridColumns.php`)
- `status`
  - Why flagged: no `member_roles.status` column. Status is derived from dates (ActiveWindow pattern).
  - Data source: computed via `ActiveWindowBaseEntity` logic.
  - Recommendation: **Needs customFilterHandler** that maps status → date window conditions.

### ActivitiesGridColumns (`app/plugins/Activities/src/KMP/GridColumns/ActivitiesGridColumns.php`)
- `activity_group_name`
  - Why flagged: virtual property from `ActivityGroup` association; no base column.
  - Data source: `ActivityGroups` (belongsTo `Activities`).
  - Recommendation: **Needs customFilterHandler** or change queryField to `Activities.activity_group_id` (with ID-based filter values).

### MemberAuthorizationsGridColumns (`app/plugins/Activities/src/KMP/GridColumns/MemberAuthorizationsGridColumns.php`)
- `responded_on`
  - Why flagged: `queryField` points to `CurrentPendingApprovals.responded_on` (association). Base query uses `contain()` only.
  - Data source: `CurrentPendingApprovals` (hasMany association).
  - Recommendation: **Needs handler or join** if filtering is ever enabled for this grid. (Currently `canFilter` is false.)

### OfficersGridColumns (`app/plugins/Officers/src/KMP/GridColumns/OfficersGridColumns.php`)
- `office_name`
  - Why flagged: `queryField` points to `Offices.name` (association). Base query uses `contain()` only.
  - Data source: `Offices`.
  - Recommendation: **Needs handler or join**, or change to `Officers.office_id` for ID filtering.
- `branch_name`
  - Why flagged: `queryField` points to `Branches.name` (association). Base query uses `contain()` only.
  - Data source: `Branches`.
  - Recommendation: **Needs handler or join**, or change to `Officers.branch_id` for ID filtering.
- `warrant_state`
  - Why flagged: virtual/computed (no DB column). Auto-filter would generate `Officers.warrant_state`.
  - Data source: computed in `Officer::_getWarrantState()` from Office requirements and warrant associations.
  - Recommendation: **Needs customFilterHandler**.

### OfficesGridColumns (`app/plugins/Officers/src/KMP/GridColumns/OfficesGridColumns.php`)
- `department_id`
  - Why flagged: `queryField` points to `Departments.id` (association). Base query uses `contain()` only.
  - Data source: `Departments` (belongsTo `Offices`).
  - Recommendation: **Needs handler or join**, or change to `Offices.department_id`.

### AwardsGridColumns (`app/plugins/Awards/src/KMP/GridColumns/AwardsGridColumns.php`)
- `domain_name`
  - Why flagged: `queryField` points to `Domains.id` (association). Base query uses `contain()` only.
  - Data source: `Domains` (belongsTo `Awards`).
  - Recommendation: **Needs handler or join**, or change to `Awards.domain_id`.
- `level_name`
  - Why flagged: `queryField` points to `Levels.id` (association). Base query uses `contain()` only.
  - Data source: `Levels` (belongsTo `Awards`).
  - Recommendation: **Needs handler or join**, or change to `Awards.level_id`.
- `branch_id`
  - Why flagged: `queryField` points to `Branches.id` (association). Base query uses `contain()` only.
  - Data source: `Branches` (belongsTo `Awards`).
  - Recommendation: **Needs handler or join**, or change to `Awards.branch_id`.

### RecommendationsGridColumns (`app/plugins/Awards/src/KMP/GridColumns/RecommendationsGridColumns.php`)
- `branch_id`
  - Why flagged: `queryField` points to `Branches.id` (association). Base query uses `contain()` only.
  - Data source: `Branches` (member branch).
  - Recommendation: **Needs handler or join**, or change to `Recommendations.branch_id`.
- `domain_name`
  - Why flagged: `queryField` points to `Domains.id` (association). Base query uses `contain()` only.
  - Data source: `Awards.Domains`.
  - Recommendation: **Needs handler or join**, or change to `Awards.domain_id` via a custom handler if you want to filter by domain without an explicit join.
- `award_name`
  - Why flagged: `queryField` points to `Awards.abbreviation` (association). Base query uses `contain()` only.
  - Data source: `Awards`.
  - Recommendation: **Needs handler or join**, or change to `Recommendations.award_id` if dropdown values are IDs.
- `assigned_gathering`
  - Why flagged: `queryField` points to `AssignedGathering.name` (association). Base query uses `contain()` only.
  - Data source: `AssignedGathering` (belongsTo `Recommendations`).
  - Recommendation: **Needs handler or join**, or change to `Recommendations.gathering_id` (or `event_id` if still used).
- `branch_type`
  - Why flagged: `queryField` points to `Branches.type` (association). Base query uses `contain()` only.
  - Data source: `Branches`.
  - Recommendation: **Needs handler or join**.
- `gatherings`
  - Status: already has `customFilterHandler`.

### GatheringWaiversGridColumns (`app/plugins/Waivers/src/KMP/GridColumns/GatheringWaiversGridColumns.php`)
- `branch_id`
  - Why flagged: `queryField` points to `Branches.name` (association). Base query joins `Gatherings` but does not join `Branches`.
  - Data source: `Gatherings.Branches`.
  - Recommendation: **Needs handler or join**, or change to `Gatherings.branch_id` / `GatheringWaivers.branch_id` (if present).
- `waiver_type_id`
  - Why flagged: `queryField` points to `WaiverTypes.name` (association). Base query uses `contain()` only.
  - Data source: `WaiverTypes`.
  - Recommendation: **Needs handler or join**, or change to `GatheringWaivers.waiver_type_id` if present.
- `gathering_start_date`
  - Why flagged: uses `Gatherings.start_date` (association).
  - Data source: `Gatherings`.
  - Recommendation: **Probably OK** because the base query uses `innerJoinWith('Gatherings')`. Keep an eye on this if the base query changes.

## Custom User Views (Other Than Recommendations)
Yes. These grids explicitly allow custom user views (`canAddViews => true`):
- Warrants grid (`app/src/Controller/WarrantsController.php`) — `gridKey: Warrants.index.main`
- Members grid (`app/src/Controller/MembersController.php`) — `gridKey: Members.index.main`
- Gatherings grid (`app/src/Controller/GatheringsController.php`) — `gridKey: Gatherings.index.main`
- Gathering Waivers grid (`app/plugins/Waivers/src/Controller/GatheringWaiversController.php`) — `gridKey: Waivers.GatheringWaivers.index.main`

Notes:
- Recommendations grid also allows custom views (`canAddViews => true`) and already uses a custom handler for `gatherings`.
- Many other grids explicitly set `canAddViews => false`, even when system views are enabled.
