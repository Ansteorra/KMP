# Tab Ordering Quick Reference

## TL;DR

Mix plugin tabs and template tabs in any order using CSS flexbox `order` property.

## Quick Setup

### For View Templates

**Tab Button:**
```php
<button class="nav-link" 
    id="nav-my-tab-tab" 
    data-bs-toggle="tab" 
    data-bs-target="#nav-my-tab" 
    type="button" 
    role="tab"
    data-detail-tabs-target='tabBtn'
    data-tab-order="10"
    style="order: 10;"><?= __("Label") ?>
</button>
```

**Tab Content:**
```php
<div class="related tab-pane fade m-3" 
    id="nav-my-tab" 
    role="tabpanel" 
    data-detail-tabs-target="tabContent"
    data-tab-order="10"
    style="order: 10;">
    <!-- content -->
</div>
```

### For Plugins

In your ViewCellProvider:
```php
$cells[] = [
    'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
    'label' => 'Tab Name',
    'id' => 'my-tab',
    'order' => 5,  // <-- This controls position
    'cell' => 'Plugin.Cell',
    'validRoutes' => [...]
];
```

## Order Ranges

| Range | Purpose |
|-------|---------|
| 1-10 | Plugin tabs |
| 10-20 | Primary entity tabs |
| 20-30 | Secondary entity tabs |
| 30+ | Admin/rare tabs |
| 999 | Default (no order) |

## Required Attributes

Both button and content need:
- `data-tab-order="X"` - For reference/debugging
- `style="order: X;"` - For CSS flexbox ordering

## Common Patterns

### Example: Member View Tabs

```
Order 1: "Offices" (Officers plugin)
Order 2: "Authorizations" (Activities plugin)  
Order 3: "Awards" (Awards plugin)
Order 10: "Roles" (template)
Order 20: "Notes" (template)
Order 30: "Additional Info" (template)
```

### Example: Branch View Tabs

```
Order 1: "Officers" (Officers plugin)
Order 10: "Members" (template)
Order 20: "Sub-Branches" (template)
```

## Checklist

- [ ] Tab button has `data-tab-order` and `style="order: X;"`
- [ ] Tab content has matching `data-tab-order` and `style="order: X;"`
- [ ] Order value chosen doesn't conflict with existing tabs
- [ ] Both button and content use the **same** order value

## Troubleshooting

**Wrong order?**
1. Check both button and content have same order
2. Verify parent has `display: flex`
3. Check browser console for errors

**Default order (999)?**
- Add explicit `order` field to plugin config
- Add `data-tab-order` and `style="order: X;"` to template tab

## See Also

- Full documentation: `/docs/tab-ordering-system.md`
- Plugin guide: `/docs/5-plugins.md`
- View patterns: `/docs/4.5-view-patterns.md`
