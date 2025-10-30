# Tab Ordering System

## Overview

The KMP view record system supports flexible tab ordering that allows mixing plugin-provided tabs with template-specific tabs in any order. This is achieved through CSS flexbox `order` property, making it a clean, server-side rendered solution.

## How It Works

### Plugin Tabs

Plugin tabs are registered through the `ViewCellRegistry` system and automatically include an `order` field in their configuration. The system uses this order value to position tabs.

**Example from a plugin's ViewCellProvider:**
```php
$cells[] = [
    'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
    'label' => 'Officers',
    'id' => 'branch-officers',
    'order' => 1,  // Lower numbers appear first (leftmost)
    'tabBtnBadge' => null,
    'cell' => 'Officers.BranchOfficers',
    'validRoutes' => [
        ['controller' => 'Branches', 'action' => 'view', 'plugin' => null],
    ]
];
```

### Base Template Tabs

Template-specific tabs (defined in view templates like `Members/view.php`) can now specify their own order to interleave with plugin tabs.

**Example from a view template:**
```php
<?php $this->KMP->startBlock("tabButtons") ?>
<!-- Plugin tabs typically use orders 1-10, so choose accordingly -->
<button class="nav-link" 
    id="nav-roles-tab" 
    data-bs-toggle="tab" 
    data-bs-target="#nav-roles" 
    type="button" 
    role="tab"
    aria-controls="nav-roles" 
    aria-selected="false" 
    data-detail-tabs-target='tabBtn'
    data-tab-order="10"
    style="order: 10;"><?= __("Roles") ?>
</button>
<?php $this->KMP->endBlock() ?>
```

**Matching tab content panel:**
```php
<?php $this->KMP->startBlock("tabContent") ?>
<div class="related tab-pane fade m-3" 
    id="nav-roles" 
    role="tabpanel" 
    aria-labelledby="nav-roles-tab"
    data-detail-tabs-target="tabContent"
    data-tab-order="10"
    style="order: 10;">
    <!-- Tab content here -->
</div>
<?php $this->KMP->endBlock() ?>
```

## Order Value Guidelines

### Recommended Ranges

- **1-10**: Plugin tabs (Officers, Authorizations, Awards, etc.)
- **10-20**: Primary entity tabs (Members, Roles, Notes)
- **20-30**: Secondary entity tabs (Additional Info, Settings)
- **30+**: Administrative or rarely used tabs
- **999**: Default fallback for tabs without explicit order

### Example Ordering Scenario

For a Member profile page, you might have:

```
Order 1: "Offices" (Officers plugin)
Order 2: "Authorizations" (Activities plugin)
Order 3: "Awards" (Awards plugin)
Order 10: "Roles" (base template)
Order 20: "Notes" (base template)
Order 30: "Additional Info" (base template)
```

This creates a natural flow: plugin-enhanced data first, core member data second, supplementary data last.

## Implementation Details

### Layout Template

The `view_record.php` layout template ensures flexbox ordering:

```php
<!-- Tab navigation with flexbox -->
<div class="nav nav-tabs" id="nav-tabButtons" role="tablist" style="display: flex;">
    <?= $this->element('pluginTabButtons', [...]) ?>
    <?= $this->fetch("tabButtons") ?>
</div>

<!-- Tab content with flexbox -->
<div class="tab-content" id="nav-tabContent" style="display: flex; flex-direction: column;">
    <?= $this->element('pluginTabBodies', [...]) ?>
    <?= $this->fetch("tabContent") ?>
</div>
```

### Plugin Tab Elements

The `pluginTabButtons.php` and `pluginTabBodies.php` elements automatically apply the order from the ViewCellRegistry configuration:

```php
data-tab-order="<?= $tab['order'] ?? 999 ?>"
style="order: <?= $tab['order'] ?? 999 ?>;"
```

### JavaScript Controller Integration

The `detail-tabs-controller.js` Stimulus controller respects the tab ordering:

- **First Tab Activation**: Uses `data-tab-order` to determine which tab should be active by default
- **URL State Management**: Recognizes the visually first tab (lowest order) as the "default" tab
- **Order-Aware Logic**: Sorts tabs by order attribute before selecting the first one

This ensures that when you load a page, the tab with the lowest order value is activated first, regardless of its position in the DOM.

## Backwards Compatibility

Tabs without explicit order values default to `999`, which places them at the end. This ensures:

1. Existing templates without order continue to work
2. Plugin tabs without order appear after ordered tabs
3. No breaking changes to existing views

## CSS Flexbox Order

The system uses CSS flexbox `order` property:

- **How it works**: Flexbox items are arranged by their `order` value (ascending)
- **Default value**: 0 (but we use 999 as default for clarity)
- **Performance**: CSS-only, no JavaScript required
- **Browser support**: All modern browsers (IE11+)

### Why CSS Instead of Server-Side Sorting?

1. **Simplicity**: No need to merge and sort arrays in PHP
2. **Performance**: Browser handles rendering efficiently
3. **Flexibility**: Easy to override in custom CSS if needed
4. **Separation of concerns**: Layout logic stays in the presentation layer

## Usage Examples

### Adding a New Tab to a View Template

**Step 1: Add the tab button with order**
```php
<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link" 
    id="nav-my-tab-tab" 
    data-bs-toggle="tab" 
    data-bs-target="#nav-my-tab" 
    type="button" 
    role="tab"
    aria-controls="nav-my-tab" 
    aria-selected="false" 
    data-detail-tabs-target='tabBtn'
    data-tab-order="15"
    style="order: 15;"><?= __("My Tab") ?>
</button>
<?php $this->KMP->endBlock() ?>
```

**Step 2: Add the matching tab content panel**
```php
<?php $this->KMP->startBlock("tabContent") ?>
<div class="related tab-pane fade m-3" 
    id="nav-my-tab" 
    role="tabpanel" 
    aria-labelledby="nav-my-tab-tab"
    data-detail-tabs-target="tabContent"
    data-tab-order="15"
    style="order: 15;">
    <!-- Your content here -->
</div>
<?php $this->KMP->endBlock() ?>
```

### Creating a Plugin Tab

In your plugin's ViewCellProvider:

```php
public static function getViewCells(array $urlParams, $user = null): array
{
    if (!StaticHelpers::pluginEnabled('YourPlugin')) {
        return [];
    }

    $cells = [];

    $cells[] = [
        'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
        'label' => 'My Plugin Tab',
        'id' => 'my-plugin-tab',
        'order' => 5,  // Choose appropriate order
        'tabBtnBadge' => null,
        'cell' => 'YourPlugin.YourCell',
        'validRoutes' => [
            ['controller' => 'Members', 'action' => 'view', 'plugin' => null],
        ]
    ];

    return $cells;
}
```

## Best Practices

### 1. Use Consistent Spacing

Use increments of 5 or 10 for order values to leave room for future tabs:
```php
Order 10, 20, 30  // Good - easy to insert tab at 15 or 25
Order 1, 2, 3     // Less flexible
```

### 2. Document Your Order Choices

Add comments explaining why you chose specific order values:
```php
<!-- Order 15: Between plugin tabs (1-10) and secondary tabs (20+) -->
<button ... data-tab-order="15" style="order: 15;">
```

### 3. Keep Tab Button and Content Orders Synchronized

Always use the same order value for both the button and content panel:
```php
// Button
data-tab-order="10" style="order: 10;"

// Content
data-tab-order="10" style="order: 10;"
```

### 4. Test Cross-Plugin Scenarios

When multiple plugins add tabs, verify they order correctly together.

## Troubleshooting

### Tabs Appearing in Wrong Order

**Check:**
1. Both button and content panel have same order value
2. Order values are numeric (not strings)
3. CSS `display: flex` is applied to parent container
4. Browser console for CSS errors

### Wrong Tab Active on Page Load

**Cause:** The detail-tabs controller activates the tab with the lowest order value
**Check:**
1. Verify the tab you want active has the lowest `data-tab-order` value
2. Check that all tabs have `data-tab-order` attributes
3. JavaScript console for errors in detail-tabs-controller

### Plugin Tabs Not Respecting Order

**Verify:**
1. ViewCellRegistry configuration includes `order` field
2. Plugin is properly registered in bootstrap
3. `pluginTabButtons.php` element is up to date

### Default Order (999) Being Used

**Cause:** Tab missing `data-tab-order` attribute or order value
**Fix:** Add explicit order to tab definition

## Future Enhancements

Potential improvements to consider:

1. **Dynamic Order Adjustment**: JavaScript to dynamically reorder tabs based on user preferences
2. **Order Validation**: Development-mode warnings for order conflicts
3. **Visual Order Indicator**: Admin interface showing tab ordering for debugging
4. **Order Presets**: Named order ranges (e.g., "primary", "secondary", "admin")

## Related Documentation

- [View Cell Registry System](./5-plugins.md#view-cell-integration)
- [Plugin Architecture](./5-plugins.md)
- [View Patterns](./4.5-view-patterns.md)
- [Stimulus.JS Controllers](./10.1-javascript-framework.md)

## Technical References

- **CSS Flexbox Order**: [MDN Web Docs](https://developer.mozilla.org/en-US/docs/Web/CSS/order)
- **Bootstrap Nav Tabs**: [Bootstrap Documentation](https://getbootstrap.com/docs/5.0/components/navs-tabs/)
- **CakePHP Blocks**: [CakePHP View Blocks](https://book.cakephp.org/5/en/views.html#using-view-blocks)
