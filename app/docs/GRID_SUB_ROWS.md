# Grid View Sub-Rows Pattern

## Overview

The grid view system supports expandable sub-rows that display additional details for a record when clicked. This pattern provides an efficient way to show detailed information without requiring navigation to a separate page.

## Architecture

### Components

1. **Column Configuration** - Define `clickAction: 'toggleSubRow:<type>'` in column metadata
2. **JavaScript Handler** - Stimulus controller manages expand/collapse behavior
3. **Controller Endpoint** - Server action returns HTML fragments
4. **Sub-Row Template** - View template renders the detailed content
5. **Route Configuration** - Explicit route for sub-row endpoint

## Implementation Steps

### 1. Column Metadata Configuration

In your GridColumns class (e.g., `MembersGridColumns.php`):

```php
'warrantable' => [
    'key' => 'warrantable',
    'label' => 'Warrantable',
    'type' => 'boolean',
    'sortable' => true,
    'filterable' => true,
    'clickAction' => 'toggleSubRow:warrantreasons',  // Format: toggleSubRow:<type>
    'defaultVisible' => true,
],
```

**Format**: `toggleSubRow:<type>` where `<type>` is a unique identifier for this sub-row type.

### 2. Controller Action

Add a `subRow()` action to your controller:

```php
public function subRow(?string $id = null, ?string $type = null)
{
    // Disable layout for AJAX response
    $this->viewBuilder()->disableAutoLayout();
    
    // Validate parameters
    if (!$id || !$type) {
        throw new NotFoundException(__('Invalid request'));
    }
    
    // Load the entity
    $member = $this->Members->get($id, contain: []);
    
    // Route to appropriate template based on type
    switch ($type) {
        case 'warrantreasons':
            $this->set('reasons', $member->non_warrantable_reasons);
            $this->render('/element/sub_rows/warrant_reasons');
            break;
            
        case 'awards':
            $awards = $this->Members->Awards
                ->find()
                ->where(['member_id' => $id])
                ->all();
            $this->set('awards', $awards);
            $this->render('/element/sub_rows/awards');
            break;
            
        default:
            throw new NotFoundException(__('Unknown sub-row type: {0}', $type));
    }
}
```

**Key Points**:
- Use `disableAutoLayout()` to return HTML fragments only
- Validate both `$id` and `$type` parameters
- Use a switch statement to handle different sub-row types
- Set view variables and render the appropriate template

### 3. Sub-Row Template

Create template at `templates/element/sub_rows/{type}.php`:

```php
<?php
/**
 * Warrant Reasons Sub-Row Template
 * 
 * Displays reasons why a member is not warrantable.
 * 
 * @var array $reasons Array of non-warrantable reason strings
 */
?>
<div class="warrant-reasons p-3">
    <?php if (empty($reasons)): ?>
        <div class="text-success">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Member is warrantable</strong> - All requirements met
        </div>
    <?php else: ?>
        <div class="text-warning">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Cannot receive warrant due to:</strong>
        </div>
        <ul class="mb-0 mt-2">
            <?php foreach ($reasons as $reason): ?>
                <li><?= h($reason) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
```

**Best Practices**:
- Include clear docblock with variable descriptions
- Handle empty/null data gracefully
- Use Bootstrap classes for consistent styling
- Always use `h()` helper to escape output
- Keep templates focused and simple

### 4. Route Configuration

Add explicit route in `config/routes.php`:

```php
/**
 * Grid View Sub-Row Route
 * 
 * AJAX endpoint for loading expandable sub-row content in grid views.
 * Returns HTML fragments for additional row details on demand.
 * 
 * @route "/{controller}/sub-row/{id}/{type}" → Controller::subRow($id, $type)
 * @example "/members/sub-row/123/warrantreasons" → Warrant eligibility details
 * @contentType text/html
 */
$builder->connect('/members/sub-row/:id/:type', [
    'controller' => 'Members',
    'action' => 'subRow'
])
->setPass(['id', 'type'])
->setPatterns(['id' => '[0-9]+', 'type' => '[a-z]+']);
```

**Pattern Validation**:
- `id` - Numbers only (entity ID)
- `type` - Lowercase letters only (sub-row type identifier)

### 5. CSS Styling (Already Included)

The grid view system includes pre-built CSS for sub-rows in `assets/css/app.css`:

```css
/* Sub-row expansion styles for grid view */
.sub-row {
    background-color: #f8f9fa;
    border-top: 1px solid #dee2e6;
}

.sub-row-content {
    padding: 0 !important;
    background-color: #f8f9fa;
}

.sub-row .warrant-reasons {
    background-color: #ffffff;
    border-left: 3px solid #0d6efd;
    border-radius: 4px;
    margin: 0.5rem;
}

.sub-row-error .sub-row-content {
    background-color: #f8d7da;
}

tr.row-expanded {
    border-bottom: 2px solid #0d6efd;
}

.toggle-icon {
    transition: transform 0.2s ease;
    color: #6c757d;
}
```

**Customization**:
- Add custom classes for specific sub-row types (e.g., `.warrant-reasons`)
- Use consistent margin/padding within `.sub-row-content`
- Error states automatically styled with `.sub-row-error`

## How It Works

### User Interaction Flow

1. **User clicks** on a cell with `toggleSubRow` clickAction
2. **Chevron icon** indicates expandability (bi-chevron-right)
3. **JavaScript handler** intercepts the click via Stimulus action
4. **AJAX request** sent to `/{controller}/sub-row/{id}/{type}`
5. **Server** loads entity and renders template
6. **HTML fragment** returned to client
7. **JavaScript** inserts new `<tr>` element below main row
8. **Animation** rotates chevron to down position (bi-chevron-down)
9. **Row highlighted** with blue bottom border (`row-expanded` class)
10. **Clicking again** removes sub-row and resets visual state

### Technical Details

**Request Format**:
```
GET /members/sub-row/123/warrantreasons
X-Requested-With: XMLHttpRequest
Accept: text/html
```

**Response Format**:
```html
<div class="warrant-reasons p-3">
    <ul>
        <li>Member is under 18</li>
        <li>Membership is expired</li>
    </ul>
</div>
```

**DOM Structure**:
```html
<tr data-id="123" class="row-expanded">
    <td>
        <a href="#" data-action="click->grid-view#toggleSubRow" 
           data-row-id="123" data-subrow-type="warrantreasons">
            <i class="bi bi-chevron-down toggle-icon"></i>
            <span>No</span>
        </a>
    </td>
    <!-- other cells -->
</tr>
<tr id="subrow-123-warrantreasons" class="sub-row">
    <td colspan="8" class="sub-row-content">
        <div class="warrant-reasons p-3">
            <!-- template content here -->
        </div>
    </td>
</tr>
```

## JavaScript Handler (Built-in)

The `grid-view-controller.js` includes the `toggleSubRow()` method:

```javascript
toggleSubRow(event) {
    event.preventDefault()
    
    const link = event.currentTarget
    const rowId = link.dataset.rowId
    const subRowType = link.dataset.subrowType
    
    const mainRow = link.closest('tr')
    const existingSubRow = mainRow.nextElementSibling
    const subRowId = `subrow-${rowId}-${subRowType}`
    
    if (existingSubRow && existingSubRow.id === subRowId) {
        // Collapse: remove sub-row
        existingSubRow.remove()
        mainRow.classList.remove('row-expanded')
        // Update icon
        link.querySelector('.toggle-icon')?.classList
            .replace('bi-chevron-down', 'bi-chevron-right')
    } else {
        // Expand: fetch and insert sub-row
        const url = `/${controller}/sub-row/${rowId}/${subRowType}`
        fetch(url, { /* ... */ })
            .then(response => response.text())
            .then(html => {
                // Create and insert sub-row
                const subRow = document.createElement('tr')
                subRow.id = subRowId
                subRow.className = 'sub-row'
                subRow.innerHTML = `<td colspan="${colspan}">${html}</td>`
                mainRow.insertAdjacentElement('afterend', subRow)
                mainRow.classList.add('row-expanded')
                // Update icon
                link.querySelector('.toggle-icon')?.classList
                    .replace('bi-chevron-right', 'bi-chevron-down')
            })
    }
}
```

**No JavaScript changes needed** - handler is already implemented!

## Example Use Cases

### 1. Warrant Eligibility Details

**Column**: `warrantable` (boolean)
**Type**: `warrantreasons`
**Data**: Array of strings explaining why member cannot receive warrant
**Template**: Shows requirements checklist

### 2. Award History

**Column**: `award_count` (number)
**Type**: `awards`
**Data**: Related awards with dates and levels
**Template**: Formatted table of award details

### 3. Authorization Breakdown

**Column**: `authorization_count` (number)
**Type**: `authorizations`
**Data**: Active authorizations with expiration dates
**Template**: List with renewal actions

### 4. Note Preview

**Column**: `note_count` (number)
**Type**: `notes`
**Data**: Recent notes from members table
**Template**: Paginated note list with timestamps

### 5. Role History

**Column**: `has_roles` (boolean)
**Type**: `roles`
**Data**: Current and historical role assignments
**Template**: Timeline view of role changes

## Best Practices

### Performance

1. **Lazy Loading** - Sub-rows are only loaded when expanded
2. **Caching** - Once loaded, sub-row HTML is cached in DOM
3. **Minimal Queries** - Load only necessary data in controller
4. **Index Optimization** - Ensure foreign keys are indexed

### Security

1. **Authentication Required** - Sub-row actions should require login
2. **Authorization Checks** - Verify user can view the entity
3. **Input Validation** - Validate both `id` and `type` parameters
4. **Output Escaping** - Always use `h()` helper in templates

### User Experience

1. **Visual Indicators** - Use chevron icons to show expandability
2. **Smooth Animations** - CSS transitions for icon rotation
3. **Loading States** - Consider adding spinner for slow requests
4. **Error Handling** - Display friendly messages on failure
5. **Empty States** - Handle null/empty data gracefully

### Code Organization

1. **Consistent Naming** - Use lowercase, single-word type identifiers
2. **Template Location** - Keep all sub-rows in `/element/sub_rows/`
3. **Controller Pattern** - Use switch statement for type routing
4. **Documentation** - Document available sub-row types in column metadata

## Troubleshooting

### Sub-row not expanding

1. **Check console** for JavaScript errors
2. **Verify route** is configured correctly
3. **Test endpoint** directly in browser
4. **Check permissions** on controller action

### Wrong data displayed

1. **Verify entity loading** in controller action
2. **Check variable names** between controller and template
3. **Debug with** `debug()` helper in template

### Styling issues

1. **Inspect element** to verify class names
2. **Check colspan** matches visible column count
3. **Verify CSS** is compiled and loaded
4. **Test in different browsers** for compatibility

### Performance problems

1. **Add indexes** on foreign keys
2. **Limit contained associations** in controller
3. **Consider pagination** for large result sets
4. **Use select()** to load only needed fields

## Migration from Old Pattern

If you have existing detail views that should become sub-rows:

```php
// Old: Navigate to separate page
'clickAction' => 'navigate:/members/view/:id/warrants'

// New: Expand sub-row inline
'clickAction' => 'toggleSubRow:warrants'
```

**Benefits of Sub-Row Pattern**:
- ✅ No page navigation required
- ✅ Faster user experience
- ✅ Context preserved (filters, sorting, position)
- ✅ Multiple sub-rows can be open simultaneously
- ✅ Reduces database queries (lazy loading)

## Related Documentation

- [Grid View Implementation](DATAVERSE_GRID_IMPLEMENTATION.md)
- [Grid View Quick Start](DATAVERSE_GRID_QUICKSTART.md)
- [Column Click Actions](../src/KMP/GridColumns/MembersGridColumns.php) - See docblock
