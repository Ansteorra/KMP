# Tab Activation Fix - Technical Note

## Problem

After implementing CSS-based tab ordering, the wrong tab was being activated on page load. The JavaScript `detail-tabs-controller.js` was activating tabs based on DOM order (using `this.tabBtnTargets[0]`), but with CSS flexbox ordering, the first tab in the DOM is not necessarily the first tab visually.

## Example Scenario

**DOM Order:**
```html
<button data-tab-order="10">Roles</button>        <!-- DOM position 0 -->
<button data-tab-order="20">Notes</button>        <!-- DOM position 1 -->
<button data-tab-order="1">Officers</button>      <!-- DOM position 2 (plugin) -->
```

**Visual Order (after CSS ordering):**
```
Officers (order: 1) | Roles (order: 10) | Notes (order: 20)
```

**Problem:** The controller was activating "Roles" (DOM position 0) when it should activate "Officers" (lowest order value).

## Solution

Updated `detail-tabs-controller.js` to respect the `data-tab-order` attribute:

### Added Method: `getFirstTabByOrder()`

```javascript
getFirstTabByOrder() {
    if (this.tabBtnTargets.length === 0) {
        return null;
    }

    // Sort tabs by their order attribute (lower number = first)
    const sortedTabs = [...this.tabBtnTargets].sort((a, b) => {
        const orderA = parseInt(a.dataset.tabOrder || '999', 10);
        const orderB = parseInt(b.dataset.tabOrder || '999', 10);
        return orderA - orderB;
    });

    return sortedTabs[0];
}
```

### Updated: `tabBtnTargetConnected()`

**Before:**
```javascript
if (!this.foundFirst) {
    this.tabBtnTargets[0].click();  // Wrong - uses DOM order
    window.scrollTo(0, 0);
}
```

**After:**
```javascript
if (!this.foundFirst) {
    // Get the first tab based on CSS order, not DOM order
    const firstTab = this.getFirstTabByOrder();
    if (firstTab) {
        firstTab.click();
        this.foundFirst = true;
    } else {
        // Fallback to DOM order if no order specified
        this.tabBtnTargets[0].click();
    }
    window.scrollTo(0, 0);
}
```

### Updated: `tabBtnClicked()`

**Before:**
```javascript
var firstTabId = this.tabBtnTargets[0].id;  // Wrong - uses DOM order
```

**After:**
```javascript
// Get first tab based on order, not DOM position
const firstTab = this.getFirstTabByOrder();
const firstTabId = firstTab ? firstTab.id : this.tabBtnTargets[0].id;
```

This ensures URL state management correctly identifies the "default" tab.

## Additional Fixes

### Removed Hardcoded Active Classes

Updated templates to remove hardcoded `active` classes and `aria-selected="true"`:

**Branches/view.php:**
- Removed `active` class from Members tab button
- Removed `active` class from Members tab content
- Changed `aria-selected="true"` to `aria-selected="false"`

This allows the JavaScript controller to handle activation based on order.

## Testing Checklist

- [x] JavaScript compiled successfully
- [ ] Members view loads without errors
- [ ] First tab (by order) is activated on page load
- [ ] URL state management works correctly
- [ ] Tab switching functions properly
- [ ] Branches view loads correctly
- [ ] Plugin tabs appear in correct order
- [ ] Mixed plugin/template tabs order correctly

## Files Modified

1. `/workspaces/KMP/app/assets/js/controllers/detail-tabs-controller.js`
   - Added order-based first tab selection
   - Updated activation logic

2. `/workspaces/KMP/app/templates/Branches/view.php`
   - Removed hardcoded active classes

## Impact

- **Positive**: Tabs now activate in the correct visual order
- **No Breaking Changes**: Backwards compatible with tabs without order attributes
- **Performance**: Minimal - single sort operation on tab connection

## Future Considerations

- Consider adding validation in development mode to warn about missing order attributes
- Could add visual indicators in admin mode showing tab order values
- May want to add animation for first tab activation

## Related Documentation

- `/docs/tab-ordering-system.md` - Main documentation
- `/docs/tab-ordering-implementation-summary.md` - Implementation details
- `/docs/tab-ordering-quick-reference.md` - Developer quick reference
