# Mobile Card Menu - Quick Start Guide

## 5-Minute Integration

Want to add a mobile menu item to your plugin? Follow these simple steps:

### Step 1: Open Your Plugin's ViewCellProvider

```bash
# Example for Activities plugin
app/plugins/Activities/src/Services/ActivitiesViewCellProvider.php
```

### Step 2: Add Menu Item Configuration

In the `getViewCells()` method, add:

```php
$cells[] = [
    'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
    'label' => 'Your Action Name',
    'icon' => 'bi-icon-name',
    'url' => '/your-plugin/mobile-action',
    'order' => 10,
    'color' => 'primary',
    'badge' => null,
    'validRoutes' => [
        ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null],
    ]
];
```

### Step 3: Compile Assets

```bash
cd app && npm run dev
```

### Step 4: Test

1. Access a member's mobile card
2. Look for the FAB button (⋮) at bottom-right
3. Tap to open menu
4. Your menu item should appear!

## Complete Example

### Activities Plugin Integration

**File**: `app/plugins/Activities/src/Services/ActivitiesViewCellProvider.php`

```php
<?php
declare(strict_types=1);

namespace Activities\Services;

use App\KMP\StaticHelpers;
use App\Services\ViewCellRegistry;

class ActivitiesViewCellProvider
{
    public static function getViewCells(array $urlParams, $user = null): array
    {
        if (!StaticHelpers::pluginEnabled('Activities')) {
            return [];
        }

        $cells = [];

        // ... existing tab and JSON cells ...

        // Add mobile menu items
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
            'label' => 'Request Authorization',
            'icon' => 'bi-file-earmark-check',
            'url' => '/activities/mobile-request-authorization',
            'order' => 10,
            'color' => 'success',
            'badge' => null,
            'validRoutes' => [
                ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null],
            ]
        ];

        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
            'label' => 'Approve Authorizations',
            'icon' => 'bi-check-circle',
            'url' => '/activities/mobile-approve-authorizations',
            'order' => 20,
            'color' => 'primary',
            'badge' => null, // TODO: Add pending count
            'validRoutes' => [
                ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null],
            ]
        ];

        return $cells;
    }
}
```

## Configuration Options

### Required Properties

| Property | Example | Description |
|----------|---------|-------------|
| `type` | `ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU` | Must be this constant |
| `label` | `'Submit Waiver'` | Button text |
| `icon` | `'bi-file-earmark-text'` | Bootstrap icon |
| `url` | `'/waivers/mobile-submit'` | Target URL |
| `order` | `30` | Sort order (lower = higher) |
| `color` | `'info'` | Button color |
| `validRoutes` | See example | Where to show |

### Optional Properties

| Property | Example | Description |
|----------|---------|-------------|
| `badge` | `5` | Notification count |

## Icon Selection

Choose from 2000+ Bootstrap Icons: https://icons.getbootstrap.com/

### Popular Choices

```php
'icon' => 'bi-file-earmark-check'    // Document with checkmark
'icon' => 'bi-check-circle'          // Circle with check
'icon' => 'bi-file-earmark-text'     // Document with text
'icon' => 'bi-plus-circle'           // Add/create
'icon' => 'bi-pencil-square'         // Edit
'icon' => 'bi-calendar-event'        // Event/calendar
'icon' => 'bi-person-badge'          // Profile/credentials
'icon' => 'bi-shield-check'          // Security
'icon' => 'bi-clipboard-check'       // Tasks/approval
'icon' => 'bi-bell'                  // Notifications
```

## Color Selection

Choose from Bootstrap color variants:

```php
'color' => 'primary'    // Blue - main actions
'color' => 'secondary'  // Gray - secondary actions
'color' => 'success'    // Green - create/request
'color' => 'danger'     // Red - urgent/delete
'color' => 'warning'    // Orange - attention needed
'color' => 'info'       // Light blue - info/docs
'color' => 'light'      // White - subtle actions
'color' => 'dark'       // Black - contrast actions
```

## Display Order

Suggested order ranges:

- **1-10**: Profile actions
- **10-20**: Authorization/approval
- **20-30**: Documents/waivers
- **30-40**: Events/calendar
- **40-50**: Administrative
- **50+**: Settings/help

## Adding Badges

### Static Badge
```php
'badge' => 5,  // Shows "5" on button
```

### Dynamic Badge
```php
'badge' => $this->getPendingCount($user),
```

### No Badge
```php
'badge' => null,
```

## Target Page Guidelines

Create mobile-optimized target pages:

### 1. Use Mobile Layout
```php
// In your controller
$this->viewBuilder()->setLayout('mobile');
```

### 2. Large Touch Targets
Buttons and links should be at least 44x44px.

### 3. Simple Forms
Minimize fields, use native HTML5 inputs:
```html
<input type="date" ...>
<input type="email" ...>
<input type="tel" ...>
```

### 4. Clear Actions
One primary action per page:
```html
<button class="btn btn-primary btn-lg w-100">
    Submit Request
</button>
```

## Common Patterns

### Pattern 1: Simple Link
```php
$cells[] = [
    'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
    'label' => 'View Guidelines',
    'icon' => 'bi-book',
    'url' => '/help/mobile-guidelines',
    'order' => 50,
    'color' => 'secondary',
    'badge' => null,
    'validRoutes' => [
        ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null],
    ]
];
```

### Pattern 2: Action with Count
```php
$pendingCount = $this->getPendingApprovals($user);

$cells[] = [
    'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
    'label' => 'Pending Approvals',
    'icon' => 'bi-clock-history',
    'url' => '/approvals/mobile-pending',
    'order' => 15,
    'color' => 'warning',
    'badge' => $pendingCount,
    'validRoutes' => [
        ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null],
    ]
];
```

### Pattern 3: Conditional Display
```php
// Only show to users with specific permission
if ($user && $user->checkCan('ApproveAuthorizations')) {
    $cells[] = [
        'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
        'label' => 'Approve Authorizations',
        'icon' => 'bi-check-circle',
        'url' => '/activities/mobile-approve',
        'order' => 20,
        'color' => 'primary',
        'badge' => null,
        'validRoutes' => [
            ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null],
        ]
    ];
}
```

## Testing Checklist

After adding your menu item:

- [ ] Menu item appears on mobile card
- [ ] Icon displays correctly
- [ ] Button color matches configuration
- [ ] Badge shows if configured
- [ ] Link navigates to correct URL
- [ ] Works on mobile devices
- [ ] Works on desktop responsive view
- [ ] Menu closes after clicking item

## Troubleshooting

### Menu Item Not Showing

1. **Check plugin is enabled**
   ```php
   if (!StaticHelpers::pluginEnabled('YourPlugin')) {
       return [];
   }
   ```

2. **Verify route matches**
   ```php
   'validRoutes' => [
       ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null],
   ]
   ```

3. **Check ViewCellProvider is registered**
   In your plugin's bootstrap:
   ```php
   ViewCellRegistry::register(
       'YourPlugin',
       [],
       function ($urlParams, $user) {
           return YourPluginViewCellProvider::getViewCells($urlParams, $user);
       }
   );
   ```

4. **Compile assets**
   ```bash
   cd app && npm run dev
   ```

### Icon Not Rendering

- Verify icon name is correct: https://icons.getbootstrap.com/
- Must include `bi-` prefix: `'bi-file-earmark-check'`
- Check console for CSS loading errors

### Badge Not Showing

- Verify badge is a number: `'badge' => 5` (not `'5'`)
- Check badge value is > 0
- Badge is null or 0 won't display (by design)

## Next Steps

1. **Add your menu item** following this guide
2. **Create target page** for mobile users
3. **Test on real device** for best UX
4. **Add badge counts** if applicable
5. **Check permissions** for security

## Need Help?

- 📖 [Full Documentation](./mobile-card-menu-system.md)
- 🎨 [Visual Examples](./mobile-card-menu-visual-examples.md)
- 📋 [Implementation Summary](./mobile-card-menu-implementation-summary.md)

## Example Plugins

See real implementations in:

- **Activities Plugin**: Request/Approve authorizations
- **Waivers Plugin**: Submit waivers

Files:
- `app/plugins/Activities/src/Services/ActivitiesViewCellProvider.php`
- `app/plugins/Waivers/src/Services/WaiversViewCellProvider.php`
