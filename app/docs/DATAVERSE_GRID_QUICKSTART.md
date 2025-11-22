# Dataverse Grid System - Developer Quick Start

## What's Already Built

✅ **Complete Backend**: Database, models, service layer, REST API, config/validation
✅ **Core Frontend**: Stimulus controller with full view management
✅ **Column Metadata**: Members grid fully defined with 25+ columns

## What You Need to Build

Three UI elements + one controller action + one template = Working Members DV Grid

---

## Step 1: Grid View Toolbar Element

**File**: `app/templates/element/grid_view_toolbar.php`

```php
<?php
/**
 * Grid View Toolbar
 * 
 * Required variables:
 * - $gridKey: string
 * - $availableViews: array of GridView entities
 * - $currentView: GridView entity or null
 * - $columnMetadata: array from MembersGridColumns::getColumns()
 */
?>
<div class="grid-view-toolbar mb-3" 
     data-controller="grid-view"
     data-grid-view-grid-key-value="<?= h($gridKey) ?>"
     data-grid-view-member-id-value="<?= $this->Identity->get('id') ?>"
     data-grid-view-current-view-value='<?= json_encode($currentView) ?>'
     data-grid-view-available-views-value='<?= json_encode($availableViews) ?>'>
    
    <div class="row align-items-center">
        <div class="col-md-4">
            <!-- View Selector -->
            <select class="form-select" 
                    data-grid-view-target="viewSelector"
                    data-action="change->grid-view#switchView">
                <option value="">Select a view...</option>
                <?php foreach ($availableViews as $view): ?>
                    <option value="<?= $view->id ?>" <?= $currentView && $currentView->id === $view->id ? 'selected' : '' ?>>
                        <?= h($view->name) ?>
                        <?php if ($view->is_system_default): ?>
                            (System Default)
                        <?php elseif ($view->is_default): ?>
                            (My Default)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-8 text-end">
            <!-- Action Buttons -->
            <button type="button" class="btn btn-sm btn-primary" 
                    data-action="click->grid-view#saveView"
                    data-grid-view-target="saveButton">
                <i class="bi bi-save"></i> Save As New
            </button>
            
            <button type="button" class="btn btn-sm btn-success" 
                    data-action="click->grid-view#updateView"
                    data-grid-view-target="updateButton">
                <i class="bi bi-check"></i> Update View
            </button>
            
            <button type="button" class="btn btn-sm btn-warning" 
                    data-action="click->grid-view#setDefault"
                    data-grid-view-target="setDefaultButton">
                <i class="bi bi-star"></i> Set Default
            </button>
            
            <button type="button" class="btn btn-sm btn-danger" 
                    data-action="click->grid-view#deleteView"
                    data-grid-view-target="deleteButton">
                <i class="bi bi-trash"></i> Delete
            </button>
            
            <button type="button" class="btn btn-sm btn-secondary" 
                    data-action="click->grid-view#resetToDefault">
                <i class="bi bi-arrow-counterclockwise"></i> Reset
            </button>
            
            <!-- Column Picker Button -->
            <button type="button" class="btn btn-sm btn-outline-primary" 
                    data-bs-toggle="modal" 
                    data-bs-target="#columnPickerModal">
                <i class="bi bi-layout-three-columns"></i> Columns
            </button>
        </div>
    </div>
</div>

<!-- Column Picker Modal -->
<div class="modal fade" id="columnPickerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Columns</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php foreach ($columnMetadata as $key => $meta): ?>
                    <div class="form-check">
                        <input class="form-check-input" 
                               type="checkbox" 
                               value="<?= h($key) ?>" 
                               id="col_<?= h($key) ?>"
                               data-grid-view-target="columnPicker"
                               data-action="change->grid-view#toggleColumn">
                        <label class="form-check-label" for="col_<?= h($key) ?>">
                            <?= h($meta['label']) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
```

---

## Step 2: Dataverse Table Element

**File**: `app/templates/element/dataverse_table.php`

```php
<?php
/**
 * Dataverse Table Element
 * 
 * Required variables:
 * - $data: ResultSet or array of entities
 * - $columnMetadata: array from GridColumns class
 * - $visibleColumns: array of column keys to display
 * - $currentSort: array of sort definitions
 * - $gridKey: string
 */

use App\KMP\GridViewConfig;

// Default to all columns if not specified
$visibleColumns = $visibleColumns ?? array_keys($columnMetadata);
?>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <?php foreach ($visibleColumns as $columnKey): ?>
                    <?php if (!isset($columnMetadata[$columnKey])) continue; ?>
                    <?php $meta = $columnMetadata[$columnKey]; ?>
                    <th scope="col" 
                        style="width: <?= $meta['width'] ?? 'auto' ?>; text-align: <?= $meta['alignment'] ?? 'left' ?>"
                        <?php if ($meta['sortable']): ?>
                            class="sortable"
                            data-grid-view-target="sortHeader"
                            data-field="<?= h($columnKey) ?>"
                            data-action="click->grid-view#applySort"
                        <?php endif; ?>>
                        <?= h($meta['label']) ?>
                        <?php if ($meta['sortable']): ?>
                            <i class="bi bi-arrow-down-up sort-icon"></i>
                        <?php endif; ?>
                    </th>
                <?php endforeach; ?>
                <th scope="col" class="actions text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $row): ?>
                <tr>
                    <?php foreach ($visibleColumns as $columnKey): ?>
                        <?php if (!isset($columnMetadata[$columnKey])) continue; ?>
                        <?php $meta = $columnMetadata[$columnKey]; ?>
                        <td style="text-align: <?= $meta['alignment'] ?? 'left' ?>">
                            <?php
                            // Render cell based on type and renderer
                            $value = $row->{$columnKey} ?? '';
                            
                            // Use custom renderer if specified
                            if (!empty($meta['cellRenderer'])) {
                                echo $this->element(
                                    "grid_cells/{$meta['cellRenderer']}", 
                                    ['value' => $value, 'row' => $row, 'meta' => $meta]
                                );
                            } else {
                                // Default rendering by type
                                switch ($meta['type']) {
                                    case 'date':
                                        echo $this->Timezone->format($value, 'Y-m-d');
                                        break;
                                    case 'datetime':
                                        echo $this->Timezone->format($value, 'Y-m-d H:i:s');
                                        break;
                                    case 'boolean':
                                        echo $value ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>';
                                        break;
                                    default:
                                        echo h($value);
                                }
                            }
                            ?>
                        </td>
                    <?php endforeach; ?>
                    <td class="actions text-end">
                        <?= $this->Html->link('View', ['action' => 'view', $row->id], ['class' => 'btn btn-sm btn-primary']) ?>
                        <?= $this->Html->link('Edit', ['action' => 'edit', $row->id], ['class' => 'btn btn-sm btn-secondary']) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if (method_exists($data, 'count')): ?>
        <div class="paginator">
            <ul class="pagination">
                <?= $this->Paginator->first('«') ?>
                <?= $this->Paginator->prev('‹') ?>
                <?= $this->Paginator->numbers() ?>
                <?= $this->Paginator->next('›') ?>
                <?= $this->Paginator->last('»') ?>
            </ul>
            <p><?= $this->Paginator->counter('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total') ?></p>
        </div>
    <?php endif; ?>
</div>
```

---

## Step 3: Members Controller Action

**File**: `app/src/Controller/MembersController.php`

Add this action:

```php
use App\KMP\GridColumns\MembersGridColumns;
use App\KMP\GridViewConfig;
use App\Services\GridViewService;

/**
 * Dataverse-style Members index with saved views
 */
public function indexDv(): void
{
    $gridKey = 'Members.index.main';
    $member = $this->Authentication->getIdentity();
    $gridViewService = new GridViewService();
    
    // Get column metadata
    $columnMetadata = MembersGridColumns::getColumns();
    
    // Get effective view (from request, user default, or system default)
    $requestedViewId = $this->request->getQuery('viewId');
    $effectiveView = $gridViewService->getEffectiveView($gridKey, $member, $requestedViewId);
    
    // Parse view config or use defaults
    if ($effectiveView) {
        $config = $effectiveView->getConfigArray();
    } else {
        $config = GridViewConfig::createDefault($columnMetadata);
    }
    
    // Build query with filters
    $query = $this->Members->find();
    
    // Apply filters from config
    $filters = GridViewConfig::extractFilters($config);
    if (!empty($filters)) {
        $query->where($filters);
    }
    
    // Apply sort from config
    $sort = GridViewConfig::extractSort($config);
    if (!empty($sort)) {
        $query->orderBy($sort);
    }
    
    // Get visible columns
    $visibleColumns = GridViewConfig::extractVisibleColumns($config);
    if (empty($visibleColumns)) {
        $visibleColumns = array_keys(MembersGridColumns::getDefaultVisibleColumns());
    }
    
    // Set page size
    $pageSize = GridViewConfig::extractPageSize($config);
    
    // Paginate
    $this->paginate = [
        'limit' => $pageSize,
        'contain' => ['Branches', 'Parents'], // Adjust based on visible columns
    ];
    
    $members = $this->paginate($query);
    
    // Get available views for toolbar
    $availableViews = $gridViewService->getViewsForGrid($gridKey, $member);
    
    // Pass to view
    $this->set(compact(
        'members',
        'gridKey',
        'columnMetadata',
        'visibleColumns',
        'effectiveView',
        'availableViews',
        'config'
    ));
}
```

---

## Step 4: Members Template

**File**: `app/templates/Members/index_dv.php`

```php
<?php
/**
 * Dataverse-style Members Index
 */
$this->assign('title', 'Members');
?>

<div class="members index content">
    <h3><?= __('Members') ?></h3>
    
    <?= $this->element('grid_view_toolbar', [
        'gridKey' => $gridKey,
        'availableViews' => $availableViews,
        'currentView' => $effectiveView,
        'columnMetadata' => $columnMetadata,
    ]) ?>
    
    <?= $this->element('dataverse_table', [
        'data' => $members,
        'columnMetadata' => $columnMetadata,
        'visibleColumns' => $visibleColumns,
        'currentSort' => $config['sort'] ?? [],
        'gridKey' => $gridKey,
    ]) ?>
</div>
```

---

## Step 5: Add Route

**File**: `app/config/routes.php`

Add inside the main scope:

```php
$builder->connect('/members/index-dv', ['controller' => 'Members', 'action' => 'indexDv']);
```

---

## Testing Checklist

1. ✅ Navigate to `/members/index-dv`
2. ✅ Grid displays with default columns
3. ✅ Click column headers to sort
4. ✅ Open column picker, hide/show columns
5. ✅ Click "Save As New", enter name, view saved
6. ✅ Switch to saved view using dropdown
7. ✅ Modify filters/sort, click "Update View"
8. ✅ Click "Set Default", becomes user's default
9. ✅ Navigate away and back, default loads
10. ✅ Click "Delete", view removed

---

## Quick Fixes for Common Issues

### "grid-view controller not registered"
→ Check `assets/js/index.js` includes the controller file and registers it

### "CSRF token mismatch"
→ Ensure layout includes `<meta name="csrf-token" content="<?= $this->request->getAttribute('csrfToken') ?>">`

### "Column not found in columnMetadata"
→ Verify column key in visibleColumns matches key in MembersGridColumns::getColumns()

### "Views not loading"
→ Check browser console for API errors, verify GridViewsController routes are accessible

### "Sort not working"
→ Ensure column has `sortable => true` in metadata, check Stimulus controller is receiving click events

---

## Next Grids to Migrate

Once Members is validated:

1. **Warrants** - Similar structure, add WarrantsGridColumns
2. **Gatherings** - Add date range filters, location filters
3. **Awards** - Replace YAML config, most complex use case

For each new grid:
1. Create `GridColumns\{Entity}GridColumns` class
2. Add `{entity}/index-dv` route
3. Add `{Entity}Controller::indexDv()` action
4. Create `templates/{Entity}/index_dv.php` template
5. Reuse same toolbar and table elements

---

**Questions?** See `/docs/DATAVERSE_GRID_IMPLEMENTATION.md` for architecture details.
