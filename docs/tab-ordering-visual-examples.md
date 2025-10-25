# Tab Ordering Visual Examples

## Member View Example

### Before (Fixed Order)
```
┌─────────────────────────────────────────────────────┐
│  Offices  │  Authorizations  │  Awards  │  Roles  │  Notes  │  Additional Info  │
│  (Plugin) │     (Plugin)     │ (Plugin) │(Template)│(Template)│    (Template)    │
└─────────────────────────────────────────────────────┘
```
**Problem:** All plugin tabs first, all template tabs second. Cannot interleave.

### After (Flexible Ordering)
```
┌─────────────────────────────────────────────────────┐
│  Offices  │  Authorizations  │  Awards  │  Roles  │  Notes  │  Additional Info  │
│ order: 1  │    order: 2      │ order: 3 │ order:10│ order:20│    order: 30     │
│  (Plugin) │     (Plugin)     │ (Plugin) │(Template)│(Template)│    (Template)    │
└─────────────────────────────────────────────────────┘
```
**Solution:** Each tab has explicit order. Natural grouping of related information.

## Branch View Example

### Layout
```
┌─────────────────────────────────────┐
│  Officers  │  Members  │  Branches  │
│  order: 1  │ order: 10 │ order: 20  │
│  (Plugin)  │ (Template)│ (Template) │
└─────────────────────────────────────┘
```

**Explanation:**
- **Officers (1)**: Plugin-provided officer list appears first
- **Members (10)**: Primary entity data
- **Branches (20)**: Secondary hierarchical data

## Gathering View Example

### Layout
```
┌───────────────────────────────────────────────────────┐
│ Activities │  Location  │  Waivers  │  Reports  │  Notes  │
│ order: 10  │ order: 20  │ order: 5  │ order: 15 │ order: 30│
│ (Template) │ (Template) │  (Plugin) │  (Plugin) │(Template)│
└───────────────────────────────────────────────────────┘

After CSS ordering:
┌───────────────────────────────────────────────────────┐
│  Waivers   │ Activities │  Reports  │  Location  │  Notes  │
│  order: 5  │ order: 10  │ order: 15 │ order: 20  │ order: 30│
│  (Plugin)  │ (Template) │  (Plugin) │ (Template) │(Template)│
└───────────────────────────────────────────────────────┘
```

**Explanation:** Source order in HTML doesn't matter - CSS `order` property controls display.

## Order Range Convention

```
  1-10                 10-20              20-30                30+
┌──────────┐      ┌──────────┐      ┌──────────┐       ┌──────────┐
│  Plugin  │      │ Primary  │      │Secondary │       │  Admin/  │
│   Tabs   │  →   │  Entity  │  →   │  Entity  │   →   │   Rare   │
│          │      │   Tabs   │      │   Tabs   │       │   Tabs   │
└──────────┘      └──────────┘      └──────────┘       └──────────┘

Examples:          Examples:          Examples:          Examples:
- Officers (1)     - Roles (10)       - Notes (20)       - Settings (35)
- Authorizations   - Members (10)     - Location (20)    - Debug (40)
  (2)              - Activities (10)  - Sub-items (20)   - Logs (45)
- Awards (3)
- Waivers (5)
```

## Technical Implementation

### HTML Structure (Simplified)
```html
<!-- Parent container with display: flex -->
<div class="nav nav-tabs" style="display: flex;">
    
    <!-- Plugin tabs (rendered first in source) -->
    <button data-tab-order="1" style="order: 1;">Officers</button>
    <button data-tab-order="2" style="order: 2;">Authorizations</button>
    
    <!-- Template tabs (rendered second in source) -->
    <button data-tab-order="10" style="order: 10;">Roles</button>
    <button data-tab-order="20" style="order: 20;">Notes</button>
</div>
```

### CSS Flexbox Behavior
```
Source Order:    Officers(1)  Authorizations(2)  Roles(10)  Notes(20)
                     ↓              ↓               ↓          ↓
Display Order:   Officers(1)  Authorizations(2)  Roles(10)  Notes(20)
                 [Same in this case because orders are ascending]

Source Order:    Roles(10)  Notes(20)  Officers(1)  Authorizations(2)
                     ↓          ↓           ↓              ↓
Display Order:   Officers(1)  Authorizations(2)  Roles(10)  Notes(20)
                 [Browser reorders based on 'order' CSS property]
```

## Responsive Behavior

### Desktop View
```
┌─────────────────────────────────────────────────────┐
│  Tab1  │  Tab2  │  Tab3  │  Tab4  │  Tab5  │  Tab6  │
└─────────────────────────────────────────────────────┘
```

### Mobile View (with Bootstrap responsive classes)
```
┌──────────┐
│   Tab1   │
├──────────┤
│   Tab2   │
├──────────┤
│   Tab3   │
├──────────┤
│   Tab4   │
├──────────┤
│   Tab5   │
├──────────┤
│   Tab6   │
└──────────┘
```

**Note:** Order is maintained across all breakpoints.

## Code Pattern Comparison

### Plugin Tab Registration
```php
// In ViewCellProvider
$cells[] = [
    'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
    'label' => 'Officers',
    'id' => 'branch-officers',
    'order' => 1,  // ← Controls position
    'cell' => 'Officers.BranchOfficers',
    'validRoutes' => [...]
];
```

### Template Tab Definition
```php
// In view template
<?php $this->KMP->startBlock("tabButtons") ?>
<button ... 
    data-tab-order="10"  // ← Same concept
    style="order: 10;">
    <?= __("Members") ?>
</button>
<?php $this->KMP->endBlock() ?>
```

**Key Point:** Both plugins and templates use the same ordering concept, just applied differently.

## Dynamic Ordering Scenario

Imagine adding a new plugin that provides a "Reports" tab:

### Step 1: Choose Order Value
```
Existing:  Officers(1)  Authorizations(2)  Awards(3)  ...  Roles(10)
                                    ↓
Want to insert between Awards and Roles
                                    ↓
Choose order: 5 (leaves room for future tabs at 4, 6, 7, 8, 9)
```

### Step 2: Plugin Configuration
```php
$cells[] = [
    'order' => 5,  // Will appear after Awards(3), before Roles(10)
    ...
];
```

### Step 3: Result
```
Officers(1)  Authorizations(2)  Awards(3)  Reports(5)  Roles(10)
```

## Best Practice Illustration

### ❌ Bad: Sequential Numbering
```
Order: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10
       ↓
Problem: No room to insert new tabs without renumbering
```

### ✅ Good: Spaced Numbering
```
Order: 1, 5, 10, 15, 20, 25, 30
       ↓
Solution: Easy to insert at 2, 3, 7, 12, 17, 22, etc.
```

### ✅ Better: Range-Based Numbering
```
Order: 1, 2, 3, 10, 20, 30
       ↓     ↓   ↓   ↓   ↓
       Plugin  Primary  Secondary  Admin
       Tabs    Tabs     Tabs       Tabs
```

## Summary

The tab ordering system uses simple CSS flexbox `order` property to:
1. Allow plugins and templates to specify tab positions
2. Automatically arrange tabs in logical order
3. Enable flexible insertion of new tabs
4. Maintain consistent ordering across all views

**Key Advantage:** Template tabs can be positioned anywhere relative to plugin tabs, creating a natural information hierarchy.
