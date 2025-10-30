# KMP Navigation System Guide

This guide explains the KMP navigation system architecture and how to implement navigation for your plugin.

## Overview

KMP uses a hierarchical navigation system with two main types of items:
- **Parent Sections** - Top-level category headers
- **Links** - Clickable navigation items organized using `mergePath`

Navigation is built dynamically through Navigation Provider services registered in each plugin.

## Navigation Item Types

### Parent Section

Parent sections create top-level category headers in the navigation menu.

```php
[
    "type" => "parent",
    "label" => "Your Plugin",           // Display name in menu
    "icon" => "bi-puzzle",              // Bootstrap icon class
    "id" => "navheader_your_plugin",    // Unique identifier
    "order" => 500,                     // Sort order (higher = lower in menu)
]
```

**Key Properties:**
- `type`: Must be `"parent"`
- `label`: Text shown in navigation
- `icon`: Bootstrap Icons class (format: `bi-{name}`)
- `id`: Unique ID (convention: `navheader_{plugin_name}`)
- `order`: Determines position in main navigation

### Navigation Link

Links are clickable menu items organized hierarchically using `mergePath`.

```php
[
    "type" => "link",
    "mergePath" => ["Parent Name"],     // Path in navigation hierarchy
    "label" => "Menu Item",             // Display text
    "order" => 10,                      // Order within mergePath
    "url" => [                          // CakePHP URL array
        "controller" => "Items",
        "action" => "index",
        "plugin" => "YourPlugin",
        "model" => "YourPlugin.Items",
    ],
    "icon" => "bi-list",
    "activePaths" => [                  // Optional: paths that highlight this item
        "your-plugin/Items/view/*",
        "your-plugin/Items/edit/*",
    ]
]
```

**Key Properties:**
- `type`: Must be `"link"`
- `mergePath`: Array defining position in hierarchy
- `label`: Text shown in menu
- `order`: Sort order within the mergePath level
- `url`: CakePHP URL array for routing
- `icon`: Bootstrap Icons class
- `activePaths`: Optional array of URL patterns for active state

## Navigation Hierarchy Examples

### Single-Level Navigation

Plugin with items directly under parent section:

```php
// Parent section
[
    "type" => "parent",
    "label" => "My Plugin",
    "icon" => "bi-puzzle",
    "id" => "navheader_my_plugin",
    "order" => 500,
]

// Child link
[
    "type" => "link",
    "mergePath" => ["My Plugin"],       // Under "My Plugin" parent
    "label" => "Items",
    "order" => 10,
    "url" => ["controller" => "Items", "action" => "index", "plugin" => "MyPlugin"],
    "icon" => "bi-list",
]
```

**Result:**
```
My Plugin
  └─ Items
```

### Multi-Level Navigation

Nested navigation with sub-items:

```php
// Parent section
[
    "type" => "parent",
    "label" => "My Plugin",
    "icon" => "bi-puzzle",
    "id" => "navheader_my_plugin",
    "order" => 500,
]

// Top-level link
[
    "type" => "link",
    "mergePath" => ["My Plugin"],
    "label" => "Items",
    "order" => 10,
    "url" => ["controller" => "Items", "action" => "index", "plugin" => "MyPlugin"],
    "icon" => "bi-list",
]

// Nested under "Items"
[
    "type" => "link",
    "mergePath" => ["My Plugin", "Items"],    // Under "My Plugin" → "Items"
    "label" => "Add New",
    "order" => 0,
    "url" => ["controller" => "Items", "action" => "add", "plugin" => "MyPlugin"],
    "icon" => "bi-plus-circle",
]

// Another nested item
[
    "type" => "link",
    "mergePath" => ["My Plugin", "Items"],
    "label" => "Archived",
    "order" => 10,
    "url" => ["controller" => "Items", "action" => "index", "?" => ["status" => "archived"]],
    "icon" => "bi-archive",
]
```

**Result:**
```
My Plugin
  └─ Items
      ├─ Add New
      └─ Archived
```

### Integration with Existing Sections

Add your plugin's items to existing navigation sections:

```php
// Add to Config section
[
    "type" => "link",
    "mergePath" => ["Config"],          // Existing "Config" parent
    "label" => "My Plugin Settings",
    "order" => 100,
    "url" => ["controller" => "Settings", "action" => "index", "plugin" => "MyPlugin"],
    "icon" => "bi-gear",
]

// Add to user's personal menu
[
    "type" => "link",
    "mergePath" => ["Members", $userName],   // Under user's menu
    "label" => "My Items",
    "order" => 50,
    "url" => ["controller" => "Items", "action" => "mine", "plugin" => "MyPlugin"],
    "icon" => "bi-person-lines-fill",
]
```

## Advanced Features

### Dynamic Badges

Display notification counts or status indicators:

```php
[
    "type" => "link",
    "mergePath" => ["Action Items"],
    "label" => "Pending Reviews",
    "order" => 30,
    "url" => ["controller" => "Items", "action" => "pending", "plugin" => "MyPlugin"],
    "icon" => "bi-exclamation-circle",
    "badgeClass" => "bg-danger",        // Bootstrap badge class (bg-primary, bg-danger, etc.)
    "badgeValue" => [                   // Dynamic value configuration
        "class" => "MyPlugin\\Model\\Table\\ItemsTable",
        "method" => "getPendingCount",  // Method to call for count
        "argument" => $user->id         // Argument passed to method
    ],
]
```

The `badgeValue` configuration tells KMP to:
1. Load the specified class
2. Call the method with the provided argument
3. Display the returned value as a badge

**Table method example:**
```php
// In src/Model/Table/ItemsTable.php
public function getPendingCount($userId): int
{
    return $this->find()
        ->where([
            'status' => 'pending',
            'assigned_to' => $userId
        ])
        ->count();
}
```

### Active Path Highlighting

Highlight navigation items when viewing related pages:

```php
[
    "type" => "link",
    "mergePath" => ["My Plugin"],
    "label" => "Items",
    "url" => ["controller" => "Items", "action" => "index", "plugin" => "MyPlugin"],
    "icon" => "bi-list",
    "activePaths" => [
        "my-plugin/Items/index",        // Exact match
        "my-plugin/Items/view/*",       // Wildcard for any ID
        "my-plugin/Items/edit/*",       // Edit pages
        "my-plugin/Items/add",          // Add page
    ]
]
```

### Conditional Navigation

Show items based on user state:

```php
public static function getNavigationItems($user, array $params = []): array
{
    $items = [];

    // Always show parent
    $items[] = [
        "type" => "parent",
        "label" => "My Plugin",
        "icon" => "bi-puzzle",
        "id" => "navheader_my_plugin",
        "order" => 500,
    ];

    // Only authenticated users
    if ($user !== null) {
        $items[] = [
            "type" => "link",
            "mergePath" => ["My Plugin"],
            "label" => "My Items",
            "order" => 10,
            "url" => ["controller" => "Items", "action" => "mine", "plugin" => "MyPlugin"],
            "icon" => "bi-person",
        ];
    }

    // Only users with specific warrant
    if ($user !== null && $this->hasWarrant($user, 'plugin_admin')) {
        $items[] = [
            "type" => "link",
            "mergePath" => ["My Plugin"],
            "label" => "Admin",
            "order" => 100,
            "url" => ["controller" => "Admin", "action" => "index", "plugin" => "MyPlugin"],
            "icon" => "bi-shield-lock",
        ];
    }

    return $items;
}
```

### Status-Based Navigation

Create dynamic navigation based on data statuses:

```php
// Generate navigation for each status
$statuses = ['pending', 'approved', 'rejected'];
$order = 0;

foreach ($statuses as $status) {
    $items[] = [
        "type" => "link",
        "mergePath" => ["My Plugin", "Items"],
        "label" => ucfirst($status),
        "order" => $order++,
        "url" => [
            "controller" => "Items",
            "action" => "index",
            "plugin" => "MyPlugin",
            "?" => ["status" => $status]    // Query parameter
        ],
        "icon" => "bi-filter",
    ];
}
```

## Navigation Provider Implementation

### Create Provider Class

Create `src/Services/YourPluginNavigationProvider.php`:

```php
<?php
declare(strict_types=1);

namespace YourPlugin\Services;

use App\KMP\StaticHelpers;

class YourPluginNavigationProvider
{
    /**
     * Get Navigation Items
     *
     * @param mixed $user Current user identity (null if not logged in)
     * @param array $params Request parameters and context
     * @return array Navigation items
     */
    public static function getNavigationItems($user, array $params = []): array
    {
        // Check if plugin is enabled
        $enabled = StaticHelpers::getAppSetting('YourPlugin.Enabled', 'yes');
        $showInNav = StaticHelpers::getAppSetting('YourPlugin.ShowInNavigation', 'yes');
        
        if ($enabled !== 'yes' || $showInNav !== 'yes') {
            return [];
        }

        $items = [];

        // Parent section
        $items[] = [
            "type" => "parent",
            "label" => "Your Plugin",
            "icon" => "bi-puzzle",
            "id" => "navheader_your_plugin",
            "order" => 500,
        ];

        // Main navigation
        $items[] = [
            "type" => "link",
            "mergePath" => ["Your Plugin"],
            "label" => "Items",
            "order" => 10,
            "url" => [
                "controller" => "Items",
                "action" => "index",
                "plugin" => "YourPlugin",
                "model" => "YourPlugin.Items",
            ],
            "icon" => "bi-list",
            "activePaths" => [
                "your-plugin/Items/view/*",
                "your-plugin/Items/edit/*",
            ]
        ];

        // Conditional items
        if ($user !== null) {
            $items[] = [
                "type" => "link",
                "mergePath" => ["Your Plugin", "Items"],
                "label" => "Add New",
                "order" => 0,
                "url" => [
                    "controller" => "Items",
                    "action" => "add",
                    "plugin" => "YourPlugin",
                ],
                "icon" => "bi-plus-circle",
            ];
        }

        return $items;
    }
}
```

### Register Navigation

In your plugin's main class (`src/YourPluginPlugin.php`):

```php
<?php
declare(strict_types=1);

namespace YourPlugin;

use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;
use App\Services\NavigationRegistry;
use YourPlugin\Services\YourPluginNavigationProvider;

class YourPluginPlugin extends BasePlugin
{
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        // Register navigation
        NavigationRegistry::register(
            'your-plugin',
            function ($user, $params) {
                return YourPluginNavigationProvider::getNavigationItems($user, $params);
            }
        );
    }
}
```

## Bootstrap Icons

KMP uses Bootstrap Icons for navigation. Use the format `bi-{icon-name}`.

### Common Icons

| Purpose | Icon | Class |
|---------|------|-------|
| List/Index | 📋 | `bi-list` |
| View/Details | 👁️ | `bi-eye` |
| Add/Create | ➕ | `bi-plus-circle` |
| Edit | ✏️ | `bi-pencil` |
| Delete | 🗑️ | `bi-trash` |
| Settings | ⚙️ | `bi-gear` |
| User | 👤 | `bi-person` |
| Admin | 🛡️ | `bi-shield-lock` |
| Archive | 📦 | `bi-archive` |
| Download | ⬇️ | `bi-download` |
| Upload | ⬆️ | `bi-upload` |
| Search | 🔍 | `bi-search` |
| Filter | 🔀 | `bi-filter` |
| Star/Favorite | ⭐ | `bi-star` |
| Home | 🏠 | `bi-house` |
| Calendar | 📅 | `bi-calendar` |
| Globe | 🌐 | `bi-globe` |
| Puzzle | 🧩 | `bi-puzzle` |

Browse all icons: https://icons.getbootstrap.com/

## Best Practices

### 1. Always Create a Parent Section

Every plugin should have its own parent section:

```php
$items[] = [
    "type" => "parent",
    "label" => "Your Plugin",
    "icon" => "bi-puzzle",
    "id" => "navheader_your_plugin",
    "order" => 500,
];
```

### 2. Use Unique Parent IDs

Follow the convention: `navheader_{plugin_name_lowercase}`

### 3. Space Out Order Values

Use increments of 10 (10, 20, 30, 40) to allow future insertions:

```php
"order" => 10,   // First item
"order" => 20,   // Second item
"order" => 30,   // Third item
```

### 4. Include Model Parameter

Add `model` to URL arrays for authorization context:

```php
"url" => [
    "controller" => "Items",
    "action" => "index",
    "plugin" => "YourPlugin",
    "model" => "YourPlugin.Items",    // Important for authorization
]
```

### 5. Use activePaths for Highlighting

Include view, edit, and related paths:

```php
"activePaths" => [
    "your-plugin/Items/view/*",
    "your-plugin/Items/edit/*",
    "your-plugin/Items/related/*",
]
```

### 6. Check Plugin Enabled Status

Always check if plugin is enabled:

```php
if (StaticHelpers::pluginEnabled('YourPlugin') == false) {
    return [];
}
```

### 7. Support Configuration Settings

Allow users to hide navigation:

```php
$showInNav = StaticHelpers::getAppSetting('YourPlugin.ShowInNavigation', 'yes');
if ($showInNav !== 'yes') {
    return [];
}
```

### 8. Use Conditional Logic

Show items based on user authentication and permissions:

```php
if ($user !== null) {
    // Add authenticated-only items
}

if ($this->hasWarrant($user, 'admin')) {
    // Add admin-only items
}
```

## Testing Navigation

### Manual Testing

1. Log in to KMP
2. Check that your plugin's parent section appears
3. Verify all navigation items are visible
4. Click each item to ensure routing works
5. Check active state highlighting on item pages
6. Test with different user roles

### Common Issues

**Navigation not appearing:**
- Check plugin is registered in `config/plugins.php`
- Verify plugin is enabled: `StaticHelpers::pluginEnabled('YourPlugin')`
- Clear cache: `bin/cake cache clear_all`
- Check navigation registration in plugin bootstrap

**Items in wrong order:**
- Review `order` values (higher = lower in menu)
- Check `mergePath` matches parent label exactly
- Verify parent section exists

**Wrong icons:**
- Use `bi-{name}` format (not `bi bi-{name}`)
- Browse available icons at https://icons.getbootstrap.com/

**Active state not working:**
- Check `activePaths` patterns match actual URLs
- Use wildcards `*` for dynamic segments
- Include all related paths (view, edit, etc.)

## Examples from KMP Plugins

### Awards Plugin

```php
// Parent section
[
    "type" => "parent",
    "label" => "Award Recs.",
    "icon" => "bi-patch-exclamation-fill",
    "id" => "navheader_award_recs",
    "order" => 40,
]

// Main link
[
    "type" => "link",
    "mergePath" => ["Award Recs."],
    "label" => "Recommendations",
    "order" => 30,
    "url" => [
        "controller" => "Recommendations",
        "plugin" => "Awards",
        "action" => "index",
        "model" => "Awards.Recommendations",
    ],
    "icon" => "bi-megaphone",
    "activePaths" => ["awards/Recommendations/view/*"]
]

// Nested link
[
    "type" => "link",
    "mergePath" => ["Award Recs.", "Recommendations"],
    "label" => "New Recommendation",
    "order" => 20,
    "url" => [
        "controller" => "Recommendations",
        "plugin" => "Awards",
        "action" => "add",
    ],
    "icon" => "bi-plus",
]
```

### Activities Plugin

```php
// User's personal menu
[
    "type" => "link",
    "mergePath" => ["Members", $user->sca_name],
    "label" => "My Auth Queue",
    "order" => 20,
    "url" => [...],
    "icon" => "bi-person-fill-check",
    "badgeValue" => [
        "class" => "Activities\\Model\\Table\\ActivitiesTable",
        "method" => "pendingAuthCount",
        "argument" => $user->id
    ],
]
```

## Summary

KMP's navigation system uses:
- **Parent sections** for top-level organization
- **mergePath arrays** for hierarchical structure
- **Bootstrap Icons** for visual consistency
- **Dynamic badges** for notifications
- **activePaths** for context highlighting
- **Conditional logic** for user-based visibility

Follow these patterns for consistent, maintainable navigation in your plugins.
