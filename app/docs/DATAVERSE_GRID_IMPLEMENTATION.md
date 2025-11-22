# Dataverse-Style Grid Views Implementation - Progress Report

## Overview

A comprehensive, reusable grid view system has been implemented for KMP, inspired by Power Apps Dataverse. This system allows users to create, save, and manage custom views of data grids with filters, sorting, column visibility, and pagination preferences.

## Implementation Status: Phase 1 Complete (Backend + Core Frontend)

### ✅ Completed Components

#### 1. Database Layer
- **Migration**: `20251119000000_CreateGridViews.php`
  - Created `grid_views` table with full schema
  - Supports system defaults and per-user views
  - Includes soft delete, timestamps, and audit fields
  - Foreign keys to members table
  - Comprehensive indexes for performance
  - **Status**: ✅ Migration run successfully

#### 2. Model Layer
- **Entity**: `App\Model\Entity\GridView`
  - Complete entity with all properties
  - Helper methods: `getConfigArray()`, `setConfigArray()`, `isSystemDefault()`, `isUserDefault()`
  - Proper accessibility controls
  
- **Table**: `App\Model\Table\GridViewsTable`
  - Extends BaseTable (inherits cache management)
  - Complete validation rules
  - Business rules (unique system defaults, unique user defaults)
  - Custom finders: `findByGrid`, `findSystemDefault`, `findUserDefault`
  - Proper associations to Members table

#### 3. Service Layer
- **GridViewService**: `App\Services\GridViewService`
  - `getEffectiveView()`: Priority-based view resolution
  - `getViewsForGrid()`: Load all applicable views
  - `createView()`, `updateView()`, `deleteView()`: CRUD operations
  - `setUserDefault()`, `clearUserDefault()`: Default management
  - `createSystemDefault()`: Admin system defaults
  - Enforces ownership and access control

#### 4. Configuration & Validation
- **GridViewConfig**: `App\KMP\GridViewConfig`
  - Normalize and validate view configs
  - Support for 13 filter operators (eq, neq, gt, gte, lt, lte, contains, startsWith, endsWith, in, notIn, isNull, isNotNull)
  - Sort validation and normalization
  - Column visibility/order management
  - Page size constraints (10-100)
  - Extract methods for ORM integration: `extractFilters()`, `extractSort()`, `extractVisibleColumns()`, `extractPageSize()`

#### 5. Controller Layer
- **GridViewsController**: `App\Controller\GridViewsController`
  - RESTful JSON API endpoints
  - Actions: `index`, `effective`, `add`, `edit`, `delete`, `setDefault`, `clearDefault`
  - Full authentication and authorization
  - Comprehensive error handling
  - Validates configs before saving

#### 6. Grid Column Metadata
- **MembersGridColumns**: `App\KMP\GridColumns\MembersGridColumns`
  - Defined 25+ columns for Members grid
  - Metadata includes: key, label, type, sortable, filterable, defaultVisible, width, alignment
  - Custom cell renderers: memberName, statusBadge, branchName, warrantCount, etc.
  - Click actions: navigate, toggleSubRow, openModal
  - Support for sub-row templates (warrants, awards)

#### 7. Frontend Controller
- **grid-view Stimulus Controller**: `assets/js/controllers/grid-view-controller.js`
  - Complete view lifecycle management
  - Actions: switchView, saveView, updateView, deleteView, setDefault, resetToDefault
  - Filter management: applyFilter, removeFilter
  - Sort management: applySort (cycle through asc → desc → none)
  - Column visibility: toggleColumn
  - State tracking: isDirty flag, currentState object
  - API integration with CSRF token handling
  - Turbo integration for seamless page updates

### 🔨 In Progress / Not Started

#### 8. UI Elements (Next Phase)
- **Grid View Toolbar Element**: `templates/element/grid_view_toolbar.php`
  - View selector dropdown
  - Action buttons (Save, Update, Delete, Set Default, Reset)
  - Column picker button/modal
  - Dirty state indicator
  
- **Dataverse Table Element**: `templates/element/dataverse_table.php`
  - Reusable grid renderer
  - Uses column metadata for rendering
  - Integrates with grid-view controller
  - Supports custom cell renderers
  - Click action handlers
  - Sub-row templates
  
- **Column Picker Modal**: `templates/element/column_picker.php`
  - Checkbox list of available columns
  - Drag-and-drop for reordering
  - Select all/none helpers
  - Apply/Cancel buttons

#### 9. Members Index DV Implementation
- **Route**: Add `/members/index-dv` to routes.php
- **Controller Action**: `MembersController::indexDv()`
  - Load column metadata from `MembersGridColumns`
  - Use `GridViewService` to get effective view
  - Apply filters/sort/columns to query
  - Pass everything to template
- **Template**: `templates/Members/index_dv.php`
  - Include grid_view_toolbar element
  - Include dataverse_table element
  - Set up data attributes for Stimulus controller

#### 10. Testing & Validation
- Unit tests for GridViewService
- Integration tests for GridViewsController
- Browser tests for Stimulus controller
- Manual testing of all features

## Architecture Highlights

### View Resolution Priority
1. Explicit view ID (if provided in request)
2. User's default view (if set for this grid)
3. System default view (if exists)
4. Application fallback (handled by controller)

### Config JSON Schema
```json
{
  "filters": [
    {"field": "status", "operator": "eq", "value": "active"}
  ],
  "sort": [
    {"field": "last_name", "direction": "asc"}
  ],
  "columns": [
    {"key": "sca_name", "visible": true},
    {"key": "email_address", "visible": true}
  ],
  "pageSize": 50
}
```

### Grid Key Convention
Format: `{Controller}.{action}.{slug}`
- Examples: `Members.index.main`, `Awards.index.overview`, `Warrants.index.active`

### Feature Flags (Per Grid)
```php
[
    'supportsSavedViews' => true,
    'supportsColumnPicker' => true,
    'supportsFilters' => true,
    'supportsSorting' => true,
    'supportsPageSize' => true
]
```

## Next Steps

### Immediate Priority (Complete MVP)
1. Create grid_view_toolbar.php element
2. Create dataverse_table.php element
3. Create column_picker.php element (or inline in toolbar)
4. Implement MembersController::indexDv() action
5. Create templates/Members/index_dv.php template
6. Add route in config/routes.php
7. Manual testing of full workflow

### Future Enhancements
1. ✅ **Export Integration**: CSV exports now respect current view (filters, columns, sort) - **COMPLETED**
2. **Awards Plugin Migration**: Replace YAML configs with grid column metadata + saved views
3. **Shared Views**: Add role-based or permission-based view sharing
4. **Advanced Filters**: UI for complex filter builder (AND/OR logic, grouped conditions)
5. **Quick Filters**: Predefined filter shortcuts (e.g., "Active Members", "New This Month")
6. **View Templates**: Admin-managed "recommended views" for specific use cases
7. **Column Grouping**: Support for hierarchical column headers
8. **Custom Cell Editors**: Inline editing capabilities for grid cells
9. **Bulk Actions**: Select rows and apply bulk operations
10. **Grid Analytics**: Track view usage, popular filters, etc.

## CSV Export Integration

### Overview
CSV export functionality is fully integrated with the Dataverse grid system. Exports respect all current grid configurations including filters, search terms, sort order, column visibility, and column order.

### Features
- **View-Aware Exports**: Export filename includes the current view name
- **Filtered Data**: Only exports rows matching current filters and search
- **Custom Columns**: Respects column visibility and order from current view
- **Proper Headers**: Uses column labels from metadata as CSV headers
- **Memory Efficient**: Uses streaming via CsvExportService for large datasets
- **Authorization Protected**: Requires `canExport` permission via table policy

### Implementation Details

#### 1. Toolbar Button
Located in `templates/element/grid_view_toolbar.php`:
- Export button added next to the Filter button
- Triggers `exportCsv()` action in grid-view controller
- Icon: Bootstrap icon `bi-download`

#### 2. Controller Processing
Implemented in `DataverseGridTrait::processDataverseGrid()`:
- Checks for `export=csv` query parameter via `isCsvExportRequest()`
- Applies all filters, search, and sort before export
- Returns query object with metadata instead of paginated results
- Controllers (Members, Warrants) handle CSV generation

**Authorization Check**:
The `handleCsvExport()` method enforces authorization before generating CSV:
- Calls `Authorization->authorize($table, 'export')` 
- Throws `ForbiddenException` if user lacks `canExport` permission
- Requires `canExport` method in corresponding TablePolicy

#### 3. Column Selection
For each visible column:
- Uses column metadata to determine field names
- Handles relation fields via `relationField` property
- Qualifies fields with table name for direct fields
- Uses column labels as CSV headers
- **All logic centralized in `DataverseGridTrait::handleCsvExport()`**

#### 4. Filename Generation
Format: `{entity}_{view}_{date}.csv`
- Examples:
  - `members_2025-11-22.csv` (no view)
  - `members_active_users_2025-11-22.csv` (with view)
  - `warrants_current_2025-11-22.csv` (system view)

#### 5. JavaScript Handler
In `grid-view-controller.js`:
```javascript
exportCsv() {
    const currentUrl = new URL(window.location.href)
    currentUrl.searchParams.set('export', 'csv')
    window.location.href = currentUrl.toString()
}
```

### Usage Examples

**Any Controller Using DataverseGridTrait:**
```php
// 1. Declare dependency injection
public static array $inject = [CsvExportService::class];

// 2. Add service as method parameter (CakePHP 5 pattern)
public function gridData(CsvExportService $csvExportService) {
    $result = $this->processDataverseGrid([...]);
    
    // CSV export is now just one line!
    if (!empty($result['isCsvExport'])) {
        return $this->handleCsvExport($result, $csvExportService, 'entityName');
    }
    
    // ... rest of method
}
```

**Members Grid:**
```php
public function gridData(CsvExportService $csvExportService) {
    $result = $this->processDataverseGrid([...]);
    
    if (!empty($result['isCsvExport'])) {
        return $this->handleCsvExport($result, $csvExportService, 'members');
    }
    
    // ... rest of method
}
```

**Warrants Grid:**
```php
public function gridData(CsvExportService $csvExportService) {
    $result = $this->processDataverseGrid([...]);
    
    if (!empty($result['isCsvExport'])) {
        return $this->handleCsvExport($result, $csvExportService, 'warrants');
    }
    
    // ... rest of method
}
```

### Files Modified
1. `/app/templates/element/grid_view_toolbar.php` - Added export button
2. `/app/src/Controller/DataverseGridTrait.php` - Added CSV export detection, authorization, and handling
3. `/app/src/Controller/MembersController.php` - Added CSV export handling
4. `/app/src/Controller/WarrantsController.php` - Added CSV export handling
5. `/app/assets/js/controllers/grid-view-controller.js` - Added exportCsv() action
6. `/app/src/Policy/MembersTablePolicy.php` - Added canExport authorization method
7. `/app/src/Policy/WarrantsTablePolicy.php` - Added canExport authorization method

### Authorization Setup
Controllers using CSV export must have a corresponding policy with `canExport` method:

```php
// In MembersTablePolicy.php or WarrantsTablePolicy.php
public function canExport(KmpIdentityInterface $user, BaseEntity|Table $entity, mixed ...$optionalArgs): bool
{
    $method = __FUNCTION__;
    return $this->_hasPolicy($user, $method, $entity);
}
```

This ensures only authorized users can export data. The `_hasPolicy` method checks against the role permissions system.

### Benefits
- **Extreme Simplicity**: Just one line of code per controller
- **Zero Duplication**: All CSV export logic in trait
- **Consistency**: All grids export identically
- **User Experience**: Export matches exactly what user sees in grid
- **Flexibility**: Can export any view configuration (saved or ad-hoc)
- **Easy Integration**: Any new grid gets CSV export with minimal effort

## Integration Points

### Existing Systems
- ✅ BaseTable (cache management)
- ✅ Authentication/Authorization
- ✅ Muffin/Footprint (audit trail)
- ✅ Muffin/Trash (soft delete)
- ✅ Turbo/Stimulus (frontend framework)

### Future Integration
- ✅ CsvExportService (respect view configs) - **COMPLETED**
- 🔲 Awards plugin (replace YAML)
- 🔲 All other index grids (Members, Warrants, Gatherings, etc.)

## Files Created

### Backend
1. `/app/config/Migrations/20251119000000_CreateGridViews.php`
2. `/app/src/Model/Entity/GridView.php`
3. `/app/src/Model/Table/GridViewsTable.php`
4. `/app/src/Services/GridViewService.php`
5. `/app/src/KMP/GridViewConfig.php`
6. `/app/src/Controller/GridViewsController.php`
7. `/app/src/KMP/GridColumns/MembersGridColumns.php`

### Frontend
8. `/app/assets/js/controllers/grid-view-controller.js`

### Documentation
9. This file

## Known Limitations / Design Decisions

1. **Column Limit**: System is designed for up to 100 columns, validated during config normalization
2. **Filter Operators**: Limited to 13 operators; complex expressions not supported in MVP
3. **Multi-Sort**: Supported in schema, but UI may initially only support single-column sort
4. **Access Control**: Regular users can only manage their own views; system defaults require admin (enforced in service layer, not controller)
5. **Performance**: Large result sets may need pagination optimization; consider lazy loading for sub-rows
6. **Browser Support**: Relies on Stimulus/Turbo; requires modern browsers

## Rollout Strategy

1. **Phase 1 (Current)**: Complete Members DV index as prototype
2. **Phase 2**: Validate UX, performance, and gather feedback
3. **Phase 3**: Migrate 2-3 more core grids (Warrants, Gatherings)
4. **Phase 4**: Awards plugin YAML replacement
5. **Phase 5**: System-wide rollout with feature flag toggle

## Security Considerations

- ✅ CSRF protection on all write operations
- ✅ Authentication required for all endpoints
- ✅ Ownership validation before CRUD operations
- ✅ System defaults protected from regular user modification
- ✅ SQL injection prevention via ORM
- ✅ JSON validation to prevent malformed configs
- ✅ Page size limits to prevent DOS via large queries

## Performance Considerations

- ✅ Database indexes on grid_key, member_id, defaults
- ✅ Soft delete support (deleted views not loaded)
- ✅ Config validation prevents malformed queries
- ✅ Page size constraints (10-100)
- 🔲 TODO: Cache frequently accessed views
- 🔲 TODO: Optimize queries with many filters
- 🔲 TODO: Consider materialized views for complex aggregations

## Compliance & Best Practices

- ✅ Follows CakePHP 5.x conventions
- ✅ PSR-12 coding standards
- ✅ Comprehensive documentation
- ✅ Error handling and validation
- ✅ Follows KMP copilot instructions (migrations, entities, services, Stimulus)
- ✅ Parallel deployment (won't break existing grids)

---

**Status**: Phase 1 Complete - Ready for UI implementation and Members prototype
**Branch**: DVTables
**Next Milestone**: Complete Members DV grid and validate full workflow
