# Mobile Menu Enhancement - Implementation Summary

## Overview

Enhanced the `PLUGIN_TYPE_MOBILE_MENU` system with three key improvements:
1. Menu items with empty `validRoutes` now appear on all pages
2. Current page links are automatically filtered out
3. Added core "Auth Card" menu item available on all mobile pages except `viewMobileCard`

## Changes Made

### 1. ViewCellRegistry Enhancement

**File**: `/workspaces/KMP/app/src/Services/ViewCellRegistry.php`

**Change**: Modified `cellMatchesRoute()` method to support empty `validRoutes` for `PLUGIN_TYPE_MOBILE_MENU` items.

**Logic**:
```php
// For MOBILE_MENU items with no validRoutes, show everywhere
if (isset($cell['type']) && $cell['type'] === self::PLUGIN_TYPE_MOBILE_MENU) {
    if (!isset($cell['validRoutes']) || !is_array($cell['validRoutes']) || empty($cell['validRoutes'])) {
        // No valid routes means show everywhere
        return true;
    }
}
```

**Effect**: Plugins can now register mobile menu items that appear on all mobile pages by setting `validRoutes` to an empty array.

### 2. Mobile App Layout Enhancement

**File**: `/workspaces/KMP/app/templates/layout/mobile_app.php`

**Changes**:

#### A. Added "Auth Card" Menu Item
```php
// Add core "Auth Card" menu item if not on viewMobileCard page
if (!($currentController === 'Members' && $currentAction === 'viewMobileCard' && $currentPlugin === null)) {
    // Build Auth Card URL with mobile_card_token if available
    $authCardUrl = ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null];
    if ($currentUser && $currentUser->mobile_card_token) {
        $authCardUrl[] = $currentUser->mobile_card_token;
    }
    
    $mobileMenuItems[] = [
        'label' => 'Auth Card',
        'icon' => 'bi-person-vcard',
        'url' => $this->Url->build($authCardUrl),
        'order' => -10,  // Negative order to place it first
        'color' => 'info',
        'badge' => null
    ];
}
```

**Important**: The Auth Card URL includes the user's `mobile_card_token` for passwordless authentication.

#### B. Filter Out Current Page Links
```php
// Filter out current page from menu items
$currentUrl = $this->Url->build([
    'controller' => $currentController,
    'action' => $currentAction,
    'plugin' => $currentPlugin
]);

$mobileMenuItems = array_filter($mobileMenuItems, function($item) use ($currentUrl) {
    // Normalize URLs for comparison
    $itemUrl = parse_url($item['url'], PHP_URL_PATH);
    $pageUrl = parse_url($currentUrl, PHP_URL_PATH);
    return rtrim($itemUrl, '/') !== rtrim($pageUrl, '/');
});
```

**Effect**: 
- Users always have access to their Auth Card from any mobile page
- Current page links are automatically hidden to prevent redundant navigation
- URLs are normalized for accurate comparison (trailing slashes removed)

### 3. Plugin Updates

Updated existing plugin mobile menu items to use the new pattern:

#### Activities Plugin
**File**: `/workspaces/KMP/app/plugins/Activities/src/Services/ActivitiesViewCellProvider.php`

**Before**:
```php
'validRoutes' => [
    ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null],
    ['controller' => 'Authorizations', 'action' => 'mobileRequestAuthorization', 'plugin' => 'Activities'],
]
```

**After**:
```php
'validRoutes' => [] // Empty = show everywhere
```

**Items Updated**:
- Request Authorization
- Approve Authorizations

#### Waivers Plugin
**File**: `/workspaces/KMP/app/plugins/Waivers/src/Services/WaiversViewCellProvider.php`

**Before**:
```php
'validRoutes' => [
    ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null],
    ['controller' => 'Authorizations', 'action' => 'mobileRequestAuthorization', 'plugin' => 'Activities'],
]
```

**After**:
```php
'validRoutes' => [] // Empty = show everywhere
```

**Items Updated**:
- Submit Waiver

## Usage Patterns

### Pattern 1: Show Everywhere (except current page)
```php
$cells[] = [
    'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
    'label' => 'My Feature',
    'icon' => 'bi-icon-name',
    'url' => ['controller' => 'MyController', 'action' => 'myAction', 'plugin' => 'MyPlugin'],
    'order' => 10,
    'color' => 'primary',
    'badge' => null,
    'validRoutes' => [] // Empty array = show everywhere
];
```

**Result**: Menu item appears on all mobile pages except the page it links to (automatically filtered).

### Pattern 2: Show on Specific Pages Only
```php
$cells[] = [
    'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
    'label' => 'My Feature',
    'icon' => 'bi-icon-name',
    'url' => ['controller' => 'MyController', 'action' => 'myAction', 'plugin' => 'MyPlugin'],
    'order' => 10,
    'color' => 'primary',
    'badge' => null,
    'validRoutes' => [
        ['controller' => 'SpecificController', 'action' => 'specificAction', 'plugin' => null],
        ['controller' => 'AnotherController', 'action' => 'anotherAction', 'plugin' => 'SomePlugin'],
    ]
];
```

**Result**: Menu item appears only on the specified pages (and still filtered if it matches current page).

## Benefits

1. **Simplified Plugin Configuration**: Plugins no longer need to maintain lists of all pages where menu items should appear
2. **Automatic Current Page Filtering**: Users never see a link to the page they're currently on
3. **Core Integration**: Core "Auth Card" menu item integrated seamlessly with plugin items
4. **Consistent UX**: All mobile pages now have consistent navigation options
5. **Better Mobile Navigation**: Users can always get back to their Auth Card from any mobile page

## Testing Checklist

- [ ] Auth Card link appears on mobile authorization request page
- [ ] Auth Card link appears on mobile waiver submission page
- [ ] Auth Card link appears on mobile authorization approval page
- [ ] Auth Card link does NOT appear on the Auth Card page itself
- [ ] Request Authorization link appears on Auth Card page
- [ ] Request Authorization link does NOT appear on the Request Authorization page
- [ ] Submit Waiver link appears on Auth Card page
- [ ] Submit Waiver link does NOT appear on the Submit Waiver page
- [ ] All menu items display with correct icons and colors
- [ ] Menu items maintain correct sort order
- [ ] Back button (if present) still works correctly

## Migration Guide for Plugin Developers

If you have existing mobile menu items with specific `validRoutes`, you can now simplify them:

**Old Way** (still works):
```php
'validRoutes' => [
    ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null],
    ['controller' => 'OtherController', 'action' => 'otherAction', 'plugin' => 'OtherPlugin'],
    // ... list all pages where menu should appear
]
```

**New Way** (recommended for most cases):
```php
'validRoutes' => [] // Show everywhere (except current page)
```

**When to use which pattern**:
- Use empty array when menu item should be globally available (most cases)
- Use specific routes when menu item is only relevant to certain contexts (e.g., gathering-specific actions)

## Files Modified

1. `/workspaces/KMP/app/src/Services/ViewCellRegistry.php`
2. `/workspaces/KMP/app/templates/layout/mobile_app.php`
3. `/workspaces/KMP/app/plugins/Activities/src/Services/ActivitiesViewCellProvider.php`
4. `/workspaces/KMP/app/plugins/Waivers/src/Services/WaiversViewCellProvider.php`

## Implementation Date

October 28, 2025
