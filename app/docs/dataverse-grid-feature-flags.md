# Dataverse Grid Feature Flags

This document describes all available feature flags for configuring Dataverse grid behavior. All features are **optional** and can be enabled/disabled independently through the `processDataverseGrid()` configuration.

## Quick Reference

```php
$result = $this->processDataverseGrid([
    // Required configuration
    'gridKey' => 'Members.index.main',
    'gridColumnsClass' => \App\KMP\GridColumns\MembersGridColumns::class,
    'baseQuery' => $this->Members->find(),
    'tableName' => 'Members',
    'defaultSort' => ['Members.sca_name' => 'asc'],
    'defaultPageSize' => 25,
    
    // Optional feature flags (all default to true unless specified)
    'showViewTabs' => true,           // Show view tabs at top of grid
    'showAllTab' => true,             // Show "All" tab (ignored if systemViews)
    'canAddViews' => true,            // Show "+" button to create custom views
    'canFilter' => true,              // Enable filter dropdown button
    'showFilterPills' => true,        // Show active filter badges/pills
    'canExportCsv' => true,           // Show CSV export button
    'enableColumnPicker' => true,     // Enable column picker modal
    
    // System views mode (optional)
    'systemViews' => null,            // Array of predefined views
    'defaultSystemView' => null,      // Default system view ID
    'queryCallback' => null,          // Callback to modify query per view
]);
```

## Feature Flags Detailed

### View Management Features

#### `showViewTabs` (bool, default: `true`)
Controls whether the view tabs row is displayed at the top of the grid.

**When `true`:**
- View tabs are rendered with dropdown for switching views
- "All" tab shown (if `showAllTab` is true)
- Create view button shown (if `canAddViews` is true)
- System view tabs shown (if `systemViews` provided)

**When `false`:**
- Entire view tabs row is hidden
- Grid still functions with default view or URL parameters
- Useful for simple grids that don't need view management

**Example:**
```php
// Simple grid without view tabs
'showViewTabs' => false,
```

---

#### `showAllTab` (bool, default: `true` for saved views, `false` for system views)
Controls whether the "All" tab is shown in the view tabs.

**When `true`:**
- "All" tab appears as first tab
- Shows all records without view-specific filtering
- Users can click to clear view selection

**When `false`:**
- No "All" tab shown
- Always shows a specific view (default or selected)
- Useful for grids that always require filtering (e.g., warrants)

**Example:**
```php
// Force users to select a view (no "All" option)
'showAllTab' => false,
```

**Note:** Automatically set to `false` when `systemViews` is provided unless explicitly overridden.

---

#### `canAddViews` (bool, default: `true` for saved views, `true` for system views)
Controls whether users can create custom views.

**When `true`:**
- "+" button shown in view tabs
- Users can save current filters/columns as custom view
- Custom views appear alongside system views

**When `false`:**
- No "+" button shown
- Users can only use predefined views
- Useful for locked-down grids with specific views only

**Example:**
```php
// Read-only views (no custom views allowed)
'canAddViews' => false,
```

**Note:** Automatically set based on view mode (saved vs system) unless explicitly overridden.

---

### Filter Features

#### `canFilter` (bool, default: `true`)
Master switch for all filtering functionality.

**When `true`:**
- Filter dropdown button shown
- Search box available (if searchable columns exist)
- Dropdown filters available (if configured in columns)
- Date-range filters available (if configured in columns)
- "Clear all" button shown when filters active

**When `false`:**
- No filter button shown
- All filter UI hidden
- Grid can still be filtered via URL parameters
- Useful for simple display-only grids

**Example:**
```php
// Display-only grid (no filtering allowed)
'canFilter' => false,
```

**Automatic Behavior:**
- Filter button only shown if at least one filter type available
- Checks for: `hasSearch`, `hasDropdownFilters`, or `hasDateRangeFilters`

---

#### `showFilterPills` (bool, default: `true`)
Controls display of active filter badges/pills.

**When `true`:**
- Active filters shown as removable badges
- Search term shown as badge
- Each badge has "×" to remove individual filter
- Visual feedback for active filtering state

**When `false`:**
- No filter badges shown
- Filters still active (applied to query)
- Cleaner UI for grids with many filters
- Users must use filter dropdown to see active filters

**Example:**
```php
// Hide filter pills for cleaner toolbar
'showFilterPills' => false,
```

**Note:** Setting `canFilter => false` also hides pills regardless of this flag.

---

### Export Features

#### `canExportCsv` (bool, default: `true`)
Controls whether CSV export button is shown.

**When `true`:**
- "Export CSV" button shown in toolbar
- Users can download current view as CSV
- Respects visible columns and active filters
- Requires `canExport` policy permission

**When `false`:**
- No export button shown
- CSV export not available via UI
- May still be accessible via direct URL if not blocked by policy

**Example:**
```php
// Sensitive data grid (no CSV export)
'canExportCsv' => false,
```

**Security:**
Always implement `canExport()` policy method for grids with sensitive data:
```php
public function canExport(Member $user): bool {
    return $this->_hasPolicy($user, 'index');
}
```

---

### Column Management Features

#### `enableColumnPicker` (bool, default: `true`)
Controls whether column picker modal is available.

**When `true`:**
- Column picker icon shown in table header
- Modal allows showing/hiding columns
- Column visibility saved to view (if view selected)
- Required columns always visible

**When `false`:**
- No column picker icon shown
- Columns determined by view config or defaults
- Useful for fixed-column grids
- Cleaner UI when column management not needed

**Example:**
```php
// Fixed columns (no picker needed)
'enableColumnPicker' => false,
```

**Note:** Required columns (with `'required' => true`) always visible regardless of picker state.

---

## System Views Mode

When using system views instead of saved user views, provide these additional config options:

```php
'systemViews' => [
    'sys-current' => [
        'id' => 'sys-current',
        'name' => 'Current',
        'description' => 'Active records only',
        'config' => [
            'expression' => [...],
            'filters' => [...],
        ],
        'canManage' => false,
    ],
    // ... more views
],
'defaultSystemView' => 'sys-current',
'queryCallback' => function($query, $systemView) {
    // Apply system view filtering
    return $query;
},
```

**Key Points:**
- System views use string IDs (e.g., `'sys-current'`)
- Users can still create custom views (if `canAddViews => true`)
- Custom views shown after system views in dropdown
- `showAllTab` defaults to `false` in system views mode

---

## Common Configurations

### Minimal Grid (Display Only)
```php
$result = $this->processDataverseGrid([
    'gridKey' => 'SimpleData.list',
    'gridColumnsClass' => SimpleDataColumns::class,
    'baseQuery' => $this->SimpleData->find(),
    'tableName' => 'SimpleData',
    'defaultSort' => ['name' => 'asc'],
    
    // Disable all optional features
    'showViewTabs' => false,
    'canFilter' => false,
    'canExportCsv' => false,
    'enableColumnPicker' => false,
]);
```

### Locked-Down Grid (Predefined Views Only)
```php
$result = $this->processDataverseGrid([
    'gridKey' => 'Warrants.index',
    'gridColumnsClass' => WarrantsColumns::class,
    'baseQuery' => $this->Warrants->find(),
    'tableName' => 'Warrants',
    'systemViews' => $this->getSystemViews(),
    'defaultSystemView' => 'sys-current',
    
    // Lock to system views only
    'showAllTab' => false,
    'canAddViews' => false,
    'canExportCsv' => true,
]);
```

### Power User Grid (All Features)
```php
$result = $this->processDataverseGrid([
    'gridKey' => 'Members.advanced',
    'gridColumnsClass' => MembersColumns::class,
    'baseQuery' => $this->Members->find(),
    'tableName' => 'Members',
    
    // Enable everything (default behavior)
    'showViewTabs' => true,
    'showAllTab' => true,
    'canAddViews' => true,
    'canFilter' => true,
    'showFilterPills' => true,
    'canExportCsv' => true,
    'enableColumnPicker' => true,
]);
```

### Report Grid (Export-Focused)
```php
$result = $this->processDataverseGrid([
    'gridKey' => 'Reports.monthly',
    'gridColumnsClass' => ReportColumns::class,
    'baseQuery' => $this->Reports->find(),
    'tableName' => 'Reports',
    
    // Export-focused config
    'canFilter' => true,
    'canExportCsv' => true,
    'showFilterPills' => false,  // Cleaner for reports
    'showViewTabs' => false,     // Single report view
    'enableColumnPicker' => false, // Fixed columns
]);
```

---

## UI Impact Matrix

| Feature Flag | View Tabs | Filter Button | Filter Pills | Export Button | Column Picker |
|-------------|-----------|---------------|--------------|---------------|---------------|
| `showViewTabs` | ✅ | - | - | - | - |
| `showAllTab` | Adds "All" | - | - | - | - |
| `canAddViews` | Adds "+" | - | - | - | - |
| `canFilter` | - | ✅ | ✅ | - | - |
| `showFilterPills` | - | - | ✅ | - | - |
| `canExportCsv` | - | - | - | ✅ | - |
| `enableColumnPicker` | - | - | - | - | ✅ |

**Legend:**
- ✅ = Primary control
- Adds = Modifies existing element
- `-` = No direct impact

---

## JavaScript State Access

All feature flags are available in the JavaScript `gridState` object:

```javascript
// In Stimulus controller
const config = this.gridState.config;

if (config.canExportCsv) {
    // Show export button
}

if (config.showFilterPills) {
    // Render filter pills
}

if (config.enableColumnPicker) {
    // Enable column picker icon
}
```

---

## Backward Compatibility

All feature flags default to `true` (enabled) to maintain backward compatibility:

```php
// These are equivalent:
$this->processDataverseGrid([...]);

$this->processDataverseGrid([
    ...,
    'canFilter' => true,
    'canExportCsv' => true,
    'showFilterPills' => true,
    'showViewTabs' => true,
    'enableColumnPicker' => true,
]);
```

**Exception:** `showAllTab` defaults based on view mode:
- Saved views mode: `true`
- System views mode: `false`

---

## Testing Considerations

When testing grids with feature flags:

1. **Unit Tests**: Verify grid state includes correct config flags
2. **Integration Tests**: Test UI rendering with various flag combinations
3. **Authorization Tests**: Verify export blocked when policy denies
4. **Accessibility Tests**: Ensure hidden features don't break keyboard nav

Example test:
```php
public function testGridWithoutExport(): void
{
    $this->get('/members/grid-data');
    
    $this->assertResponseOk();
    $state = $this->viewVariable('gridState');
    $this->assertFalse($state['config']['canExportCsv']);
    
    // Export button should not be in HTML
    $this->assertResponseNotContains('Export CSV');
}
```

---

## See Also

- [Dataverse Grid Implementation](dataverse-grid-implementation.md)
- [Grid Column Configuration](dataverse-grid-columns.md)
- [CSV Export System](csv-export-system.md)
- [Authorization Policies](../7.1-security-best-practices.md)
