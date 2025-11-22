# Dataverse Grid Toolbar Configuration

The Dataverse grid system supports configurable toolbar features that can be customized per grid instance. This allows you to lock down functionality based on the page context or user requirements.

> **Note:** This document covers element-level attribute configuration. For controller-level configuration with `processDataverseGrid()`, see [Dataverse Grid Feature Flags](dataverse-grid-feature-flags.md).

## Overview

All toolbar features can be controlled via:
1. **Controller configuration** (recommended): Pass feature flags to `processDataverseGrid()` in your controller
2. **Element attributes**: Override defaults by passing attributes to the `dv_grid` element

Feature flags set in the controller are passed through to the server during all grid operations (filtering, sorting, pagination, CSV export, etc.).

## Available Configuration Options

| Option | Type | Default | Controller Flag | Description |
|--------|------|---------|-----------------|-------------|
| View tabs | boolean | `true` | `showViewTabs` | Show/hide the entire view tabs section |
| "All" tab | boolean | `true`* | `showAllTab` | Show/hide the "All" tab in views |
| Create views | boolean | `true`* | `canAddViews` | Show/hide the "Create View" button |
| Filter button | boolean | `true` | `canFilter` | Show/hide the filter dropdown button |
| Filter pills | boolean | `true` | `showFilterPills` | Show/hide active filter badges/pills |
| CSV export | boolean | `true` | `canExportCsv` | Show/hide CSV export button |
| Column picker | boolean | `true` | `enableColumnPicker` | Show/hide column picker modal |

\* Defaults change based on view mode (saved views vs system views)

## Basic Usage

### Controller Configuration (Recommended)

Configure features in your controller's `gridData()` method:

```php
public function gridData(CsvExportService $csvExportService)
{
    $result = $this->processDataverseGrid([
        'gridKey' => 'Members.index.main',
        'gridColumnsClass' => \App\KMP\GridColumns\MembersGridColumns::class,
        'baseQuery' => $this->Members->find(),
        'tableName' => 'Members',
        'defaultSort' => ['Members.sca_name' => 'asc'],
        
        // Feature flags
        'showViewTabs' => true,
        'canAddViews' => true,
        'canFilter' => true,
        'showFilterPills' => true,
        'canExportCsv' => true,
        'enableColumnPicker' => true,
    ]);
    
    // ... rest of method
}
```

### Example 1: Full-Featured Grid (Default)

```php
// Controller - all features enabled by default
$result = $this->processDataverseGrid([
    'gridKey' => 'Members.index.main',
    'gridColumnsClass' => \App\KMP\GridColumns\MembersGridColumns::class,
    'baseQuery' => $this->Members->find(),
    'tableName' => 'Members',
    'defaultSort' => ['Members.sca_name' => 'asc'],
]);

// View - simple element call
<?= $this->element('dv_grid', [
    'gridKey' => 'Members.index.main',
    'frameId' => 'members-grid',
    'dataUrl' => $this->Url->build(['action' => 'gridData']),
]) ?>
```

### Example 2: Read-Only Grid (No Filters, No Export)

```php
// Controller - disable filters and export
$result = $this->processDataverseGrid([
    'gridKey' => 'Members.dashboard.widget',
    'gridColumnsClass' => \App\KMP\GridColumns\MembersGridColumns::class,
    'baseQuery' => $this->Members->find(),
    'tableName' => 'Members',
    'defaultSort' => ['Members.sca_name' => 'asc'],
    
    'canFilter' => false,
    'canExportCsv' => false,
    'canAddViews' => false,
    'showViewTabs' => false,
]);

// View
<?= $this->element('dv_grid', [
    'gridKey' => 'Members.dashboard.widget',
    'frameId' => 'dashboard-members',
    'dataUrl' => $this->Url->build(['action' => 'gridData']),
]) ?>
```

Users can view and sort data, but cannot filter, export, or create views.

### Example 3: Simple List (No Toolbar)

```php
<?= $this->element('dv_grid', [
    'gridKey' => 'Members.modal.picker',
    'frameId' => 'member-picker',
    'dataUrl' => $this->Url->build(['action' => 'gridData']),
    'showToolbar' => false,
]) ?>
```

Completely hides the toolbar - just shows the data table.

### Example 4: Search-Only Grid

```php
<?= $this->element('dv_grid', [
    'gridKey' => 'Members.public.directory',
    'frameId' => 'member-directory',
    'dataUrl' => $this->Url->build(['action' => 'gridData']),
    'showViewTabs' => false,
    'canFilter' => true,
    'showSearch' => true,
    'showDropdownFilters' => false,
    'showDateRangeFilters' => false,
    'showExport' => false,
]) ?>
```

Only shows search functionality - no views, no dropdown filters, no export.

### Example 5: Embedded Report Grid

```php
<?= $this->element('dv_grid', [
    'gridKey' => 'Warrants.report.current',
    'frameId' => 'current-warrants-report',
    'dataUrl' => $this->Url->build(['action' => 'gridData']),
    'showViewTabs' => false,
    'canAddViews' => false,
    'canFilter' => true,
    'showExport' => true,
]) ?>
```

Fixed view with filtering and export - ideal for embedded reports.

## Implementation Details

### How It Works

1. **Element Attributes**: Configuration is set on the `dv_grid` element as HTML attributes
2. **Data Attributes**: Attributes are converted to `data-toolbar-*` attributes on the grid container
3. **JavaScript Extraction**: The `grid-view-controller` reads these attributes on initialization
4. **URL Parameters**: Configuration is included in all URL parameters (`toolbar_show_toolbar=true`, etc.)
5. **Server Processing**: Templates read URL parameters and override default config
6. **Consistent State**: Configuration persists through all grid operations

### Data Flow

```
dv_grid element attributes
    ↓
data-toolbar-* attributes on grid container
    ↓
JavaScript extractToolbarConfig()
    ↓
URL query parameters (toolbar_*)
    ↓
dv_grid_content.php extracts from request
    ↓
grid_view_toolbar.php applies overrides
```

### Parameter Format

Attributes are converted to snake_case URL parameters with `toolbar_` prefix:

- `showExport` → `toolbar_show_export=true`
- `canFilter` → `toolbar_can_filter=false`
- `showViewTabs` → `toolbar_show_view_tabs=true`

## Best Practices

### 1. Lock Down Public Grids

For public-facing grids, disable filtering and export:

```php
'canFilter' => false,
'showExport' => false,
```

### 2. Embedded Widgets

For dashboard widgets or modals, simplify the interface:

```php
'showViewTabs' => false,
'canAddViews' => false,
```

### 3. Report Pages

For dedicated report pages, keep export but hide view management:

```php
'showViewTabs' => false,
'canAddViews' => false,
'showExport' => true,
```

### 4. Data Pickers

For modal data pickers, show minimal UI:

```php
'showToolbar' => false,  // or
'showViewTabs' => false,
'showExport' => false,
'canFilter' => true,
'showSearch' => true,
'showDropdownFilters' => false,
```

### 5. Admin Grids

For admin interfaces, enable everything (default behavior):

```php
// No overrides needed - all features enabled by default
```

## Controller-Level Defaults

You can still set defaults in your controller's `processDataverseGrid()` call:

```php
$result = $this->processDataverseGrid([
    'config' => [
        'canFilter' => false,  // Default: no filtering
        'hasSearch' => true,   // Default: search enabled
    ],
]);
```

These defaults will be overridden by `dv_grid` element attributes if provided.

## Testing Toolbar Configuration

To test different configurations, add URL parameters manually:

```
/members/index-dv?toolbar_show_export=false&toolbar_can_filter=false
```

This is useful for testing before implementing in templates.

## Related Files

- `/app/templates/element/dv_grid.php` - Grid element with attribute support
- `/app/templates/element/dv_grid_content.php` - Extracts toolbar config from URL
- `/app/templates/element/grid_view_toolbar.php` - Applies toolbar configuration
- `/app/assets/js/controllers/grid-view-controller.js` - Reads attributes and builds URLs

## Future Enhancements

Potential future toolbar configuration options:

- `showColumnPicker` - Toggle column visibility picker
- `showPageSize` - Toggle page size selector
- `showPagination` - Toggle pagination controls
- `allowSort` - Toggle column sorting
- `showRefresh` - Add refresh button
- `customActions` - Inject custom toolbar buttons
