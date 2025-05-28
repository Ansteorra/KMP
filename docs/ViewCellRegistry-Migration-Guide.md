# ViewCellRegistry Migration Guide

## Overview

The KMP project is migrating from an event-based view cell system to a registry-based system similar to NavigationRegistry. This change provides better performance, clearer dependency management, and more predictable behavior.

## What's Changed

### Before (Event-Based System)
- Plugins used `CallForCellsHandler` classes extending `CallForCellsHandlerBase`
- View cells were registered via event listeners in plugin bootstrap
- AppController dispatched `VIEW_PLUGIN_EVENT` to collect cells
- Cell discovery happened on every request via event propagation

### After (Registry-Based System)
- Plugins use `ViewCellProvider` classes with static methods
- View cells are registered directly with `ViewCellRegistry` during bootstrap
- AppController calls `ViewCellRegistry::getViewCells()` directly
- Cell discovery is cached and more efficient

## Migration Steps

### 1. Create a ViewCellProvider Class

Create a new provider class for your plugin:

```php
<?php
// plugins/YourPlugin/src/Services/YourPluginViewCellProvider.php

declare(strict_types=1);

namespace YourPlugin\Services;

use App\KMP\StaticHelpers;
use YourPlugin\View\Cell\YourCell;

/**
 * YourPlugin View Cell Provider
 * 
 * Provides view cell configurations for YourPlugin
 */
class YourPluginViewCellProvider
{
    /**
     * Get view cells for YourPlugin
     *
     * @param array $urlParams URL parameters from request
     * @param mixed $user Current user
     * @return array View cell configurations
     */
    public static function getViewCells(array $urlParams, $user = null): array
    {
        // Check if plugin is enabled
        if (!StaticHelpers::pluginEnabled('YourPlugin')) {
            return [];
        }

        $cells = [];

        // Your Cell
        $yourCellConfig = YourCell::getViewConfigForRoute($urlParams, $user);
        if ($yourCellConfig) {
            $cells[] = array_merge($yourCellConfig, [
                'validRoutes' => [
                    ['controller' => 'SomeController', 'action' => 'view', 'plugin' => null],
                    ['controller' => 'AnotherController', 'action' => 'index', 'plugin' => 'SomePlugin'],
                ]
            ]);
        }

        return $cells;
    }
}
```

### 2. Update Plugin Bootstrap

Modify your plugin's bootstrap method:

```php
<?php
// plugins/YourPlugin/src/YourPluginPlugin.php

use App\Services\ViewCellRegistry;
use YourPlugin\Services\YourPluginViewCellProvider;

public function bootstrap(PluginApplicationInterface $app): void
{
    // OLD: Event handler registration
    // $handler = new CallForCellsHandler();
    // EventManager::instance()->on($handler);

    // NEW: Registry registration
    ViewCellRegistry::register(
        'YourPlugin',
        [], // Static cells (usually empty)
        function ($urlParams, $user) {
            return YourPluginViewCellProvider::getViewCells($urlParams, $user);
        }
    );

    // Keep existing navigation registration if you have it
    NavigationRegistry::register(
        'YourPlugin',
        [],
        function ($user, $params) {
            return YourPluginNavigationProvider::getNavigationItems($user, $params);
        }
    );
}
```

### 3. Update Imports

Add the necessary imports to your plugin class:

```php
use App\Services\ViewCellRegistry;
use YourPlugin\Services\YourPluginViewCellProvider;
```

### 4. No Changes to Existing View Cells

Your existing view cell classes can remain unchanged. They should continue to:
- Extend `BasePluginCell`
- Implement `getViewConfigForRoute($urlParams, $user)` method
- Use the same static configuration patterns

### 5. Remove Old Event Handlers

After confirming the new system works, you can remove:
- `CallForCellsHandler` class from your plugin
- Event handler registration from bootstrap
- Related imports

## URL Parameters Format

The new system uses URL parameters instead of route arrays. The parameters include:

```php
$urlParams = [
    'controller' => 'Members',
    'action' => 'view',
    'plugin' => null, // or plugin name
    'pass' => [123], // passed parameters
    'query' => [], // query parameters
    // ... other URL components
];
```

## Example Migration: Activities Plugin

### Before
```php
// plugins/Activities/src/Event/CallForCellsHandler.php
class CallForCellsHandler extends CallForCellsHandlerBase
{
    protected string $pluginName = 'Activities';
    protected array $viewsToTest = [
        PermissionActivitiesCell::class,
        MemberAuthorizationsCell::class,
        MemberAuthorizationDetailsJSONCell::class,
    ];
}
```

### After
```php
// plugins/Activities/src/Services/ActivitiesViewCellProvider.php
class ActivitiesViewCellProvider
{
    public static function getViewCells(array $urlParams, $user = null): array
    {
        if (!StaticHelpers::pluginEnabled('Activities')) {
            return [];
        }

        $cells = [];

        $permissionActivitiesConfig = PermissionActivitiesCell::getViewConfigForRoute($urlParams, $user);
        if ($permissionActivitiesConfig) {
            $cells[] = array_merge($permissionActivitiesConfig, [
                'validRoutes' => [
                    ['controller' => 'Permissions', 'action' => 'view', 'plugin' => null],
                ]
            ]);
        }

        // ... other cells

        return $cells;
    }
}
```

## Benefits of the New System

1. **Performance**: No event dispatching overhead on every request
2. **Clarity**: Explicit registration makes dependencies clear
3. **Debugging**: Easier to trace cell registration and execution
4. **Consistency**: Matches the NavigationRegistry pattern
5. **Type Safety**: Better IDE support and type checking

## Backwards Compatibility

The old event system continues to work during the transition period, but will be removed in a future version. Both systems can coexist temporarily.

## Testing the Migration

1. Verify your plugin cells still appear on the correct pages
2. Check that cell content renders correctly
3. Ensure plugin enable/disable functionality works
4. Test with different user permission levels

## Troubleshooting

### Cells Not Appearing
- Check ViewCellRegistry registration in plugin bootstrap
- Verify callback signature: `function ($urlParams, $user)`
- Ensure plugin is enabled via StaticHelpers::pluginEnabled()

### Incorrect Cell Data
- Verify `getViewConfigForRoute()` receives correct URL parameters
- Check validRoutes array matches expected controllers/actions
- Ensure user parameter is passed correctly

### Import Errors
- Add `use App\Services\ViewCellRegistry;` to plugin class
- Add provider class import: `use YourPlugin\Services\YourPluginViewCellProvider;`

## Support

For questions about this migration, refer to:
- ViewCellRegistry class documentation
- Existing plugin implementations (Officers, Activities, Awards)
- NavigationRegistry pattern for reference
