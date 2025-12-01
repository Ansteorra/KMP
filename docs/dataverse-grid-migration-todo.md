# Dataverse Grid Migration - Work In Progress

**Last Updated:** November 30, 2025  
**Branch:** DVTables  
**Status:** Phases 1-5 Complete, Phases 6-10 Pending

## Overview

Migrating all entity listing pages from legacy table-based templates to the modern Dataverse Grid system with:
- Lazy-loading Turbo Frames
- Column picker for visibility control
- Advanced filtering and sorting
- CSV export capability
- Consistent UI patterns

## Completed Phases

### Phase 1: Core Simple GridColumns ✅
Created/verified GridColumns classes for simple entities:
- `RolesGridColumns` - Fixed `is_system` field name (was `is_system_role`)
- `PermissionsGridColumns` - Fixed `SCOPE_BRANCH_AND_CHILDREN` constant
- `EmailTemplatesGridColumns` - Existed, no changes needed
- `GatheringTypesGridColumns` - Existed, no changes needed
- `GatheringActivitiesGridColumns` - Existed, no changes needed
- `WarrantPeriodsGridColumns` - Existed, no changes needed

### Phase 2: Core Complex GridColumns ✅
- `BranchesGridColumns` - Has `path` column for hierarchy display
- `AppSettingsGridColumns` - Has `value_preview` for truncated values

### Phase 3: Core Controller Updates ✅
Added `DataverseGridTrait` and `gridData()` method to:
- `RolesController`
- `PermissionsController`
- `EmailTemplatesController`
- `GatheringTypesController`
- `GatheringActivitiesController`
- `WarrantPeriodsController`
- `BranchesController` - Special: includes `computeBranchPaths()` method for hierarchy

Controllers already done (before this work):
- `MembersController`
- `WarrantsController`
- `AppSettingsController`

### Phase 4: Core Template Migrations ✅
Updated index.php templates to use `dv_grid` element:
- `templates/Roles/index.php`
- `templates/Permissions/index.php`
- `templates/EmailTemplates/index.php` - Includes sync/discover buttons
- `templates/GatheringTypes/index.php`
- `templates/GatheringActivities/index.php`
- `templates/WarrantPeriods/index.php` - Includes add modal
- `templates/Branches/index.php`
- `templates/AppSettings/index.php` - Includes add modal

### Phase 5: Core Routes ✅
No explicit routes needed - CakePHP's fallback routes handle `gridData` action automatically.

---

## Pending Phases

### Phase 6: Activities Plugin
**Location:** `plugins/Activities/`

Controllers to update:
- `ActivitiesController` - Main activities listing
- `ActivityGroupsController` - Activity group management

Files to create:
- `plugins/Activities/src/KMP/GridColumns/ActivitiesGridColumns.php`
- `plugins/Activities/src/KMP/GridColumns/ActivityGroupsGridColumns.php`

Templates to update:
- `plugins/Activities/templates/Activities/index.php`
- `plugins/Activities/templates/ActivityGroups/index.php`

### Phase 7: Awards Plugin
**Location:** `plugins/Awards/`

Controllers to update:
- `AwardsController` - Award types listing
- `RecommendationsController` - Recommendations listing (complex - has system views)
- `AwardsDomainController` - Domain management

Files to create:
- `plugins/Awards/src/KMP/GridColumns/AwardsGridColumns.php`
- `plugins/Awards/src/KMP/GridColumns/RecommendationsGridColumns.php`
- `plugins/Awards/src/KMP/GridColumns/AwardsDomainGridColumns.php`

Templates to update:
- `plugins/Awards/templates/Awards/index.php`
- `plugins/Awards/templates/Recommendations/index.php`
- `plugins/Awards/templates/AwardsDomain/index.php`

**Note:** RecommendationsController already has some DataverseGrid work started. Check `RecommendationsGridColumns.php` exists.

### Phase 8: Officers Plugin
**Location:** `plugins/Officers/`

Controllers to update:
- `OfficersController` - Officers listing
- `OfficesController` - Office types listing
- `DepartmentsController` - Department management

Files to create:
- `plugins/Officers/src/KMP/GridColumns/OfficersGridColumns.php`
- `plugins/Officers/src/KMP/GridColumns/OfficesGridColumns.php`
- `plugins/Officers/src/KMP/GridColumns/DepartmentsGridColumns.php`

Templates to update:
- `plugins/Officers/templates/Officers/index.php`
- `plugins/Officers/templates/Offices/index.php`
- `plugins/Officers/templates/Departments/index.php`

### Phase 9: Waivers Plugin
**Location:** `plugins/Waivers/`

Controllers to update:
- `WaiversController` - Waiver types listing
- `WaiverTemplatesController` - Template management (if exists)

Files to create:
- `plugins/Waivers/src/KMP/GridColumns/WaiversGridColumns.php`

Templates to update:
- `plugins/Waivers/templates/Waivers/index.php`

### Phase 10: Tab-Based Grids
Grids embedded in view pages as tabs (e.g., Member Roles tab, Member Warrants tab).

**Already Done:**
- `MembersController::memberRolesGridData()` - Roles tab on member view
- `MembersController::memberWarrantsGridData()` - Warrants tab on member view

**To Investigate:**
- Check if other view pages have tab-based listings that need migration
- Branch view page may have member listing tab
- Role view page may have member listing tab

---

## Implementation Pattern Reference

### Controller Pattern
```php
use DataverseGridTrait;

public function initialize(): void
{
    parent::initialize();
    $this->Authorization->authorizeModel('index', 'add', 'gridData');
}

public function index()
{
    // Empty - dv_grid element lazy-loads via gridData
}

public function gridData(CsvExportService $csvExportService)
{
    $result = $this->processDataverseGrid([
        'gridKey' => 'EntityName.index.main',
        'gridColumnsClass' => \App\KMP\GridColumns\EntityNameGridColumns::class,
        'baseQuery' => $this->EntityName->find(),
        'tableName' => 'EntityName',
        'defaultSort' => ['EntityName.name' => 'asc'],
        'defaultPageSize' => 25,
        'showAllTab' => false,
        'canAddViews' => false,
        'canFilter' => true,
        'canExportCsv' => true,
    ]);

    if (!empty($result['isCsvExport'])) {
        return $this->handleCsvExport($result, $csvExportService, 'entity-name');
    }

    $this->set([
        'entityNames' => $result['data'],
        'gridState' => $result['gridState'],
        // ... other vars
    ]);

    $turboFrame = $this->request->getHeaderLine('Turbo-Frame');
    if ($turboFrame === 'entity-name-grid-table') {
        $this->set('data', $result['data']);
        $this->set('tableFrameId', 'entity-name-grid-table');
        $this->viewBuilder()->disableAutoLayout();
        $this->viewBuilder()->setTemplate('../element/dv_grid_table');
    } else {
        $this->set('data', $result['data']);
        $this->set('frameId', 'entity-name-grid');
        $this->viewBuilder()->disableAutoLayout();
        $this->viewBuilder()->setTemplate('../element/dv_grid_content');
    }
}
```

### Template Pattern
```php
<?= $this->element('dv_grid', [
    'gridKey' => 'EntityName.index.main',
    'frameId' => 'entity-name-grid',
    'dataUrl' => $this->Url->build(['action' => 'gridData']),
]) ?>
```

### GridColumns Pattern
```php
class EntityNameGridColumns extends BaseGridColumns
{
    public static function getColumns(): array
    {
        return [
            'id' => [...],
            'name' => [
                'key' => 'name',
                'label' => 'Name',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'clickAction' => 'navigate:/entity-name/view/:id',
            ],
            // ... more columns
        ];
    }
}
```

---

## Known Issues

1. **AppSettingsController line 154:** Type error with `setLayout(false)` - should be `setLayout(null)`. Pre-existing issue, not caused by this migration.

2. **BranchesController path computation:** Currently loads all parent branches to compute paths. May need optimization for very large hierarchies.

---

## Testing Checklist

For each migrated grid, verify:
- [ ] Grid loads via lazy-loading Turbo Frame
- [ ] Sorting works on sortable columns
- [ ] Filtering works (both search box and dropdown filters)
- [ ] Pagination works
- [ ] Column picker shows/hides columns correctly
- [ ] CSV export downloads correct data
- [ ] Click actions navigate to correct pages
- [ ] Add button works (if applicable)
- [ ] No JavaScript console errors
