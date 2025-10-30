# Mobile Card Menu System - Quick Reference

## Overview

The Mobile Card Menu System extends the PWA mobile card with a plugin-based Floating Action Button (FAB) menu that allows plugins to register mobile-optimized features and actions accessible directly from the member's mobile card.

## Architecture

### Components

1. **ViewCellRegistry** - Registers mobile menu items via `PLUGIN_TYPE_MOBILE_MENU` constant
2. **member-mobile-card-menu-controller.js** - Stimulus controller managing the FAB menu
3. **view_mobile_card.php** - Updated template with menu container and styling
4. **Plugin ViewCellProviders** - Register menu items in plugin view cell providers

### Flow

```
Plugin ViewCellProvider
    ↓
ViewCellRegistry (PLUGIN_TYPE_MOBILE_MENU)
    ↓
MembersController (viewMobileCard action)
    ↓
view_mobile_card.php template
    ↓
member-mobile-card-menu-controller (Stimulus)
    ↓
Rendered FAB Menu
```

## Plugin Integration

### Registering a Mobile Menu Item

In your plugin's ViewCellProvider (e.g., `ActivitiesViewCellProvider.php`):

```php
public static function getViewCells(array $urlParams, $user = null): array
{
    if (!StaticHelpers::pluginEnabled('YourPlugin')) {
        return [];
    }

    $cells = [];

    // Add mobile menu item
    $cells[] = [
        'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
        'label' => 'Submit Waiver',              // Button text
        'icon' => 'bi-file-earmark-text',        // Bootstrap icon class
        'url' => '/waivers/mobile-submit',       // Target URL
        'order' => 10,                           // Display order (lower = higher)
        'color' => 'primary',                    // Bootstrap button color
        'badge' => null,                         // Optional: notification count
        'validRoutes' => [
            ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null],
        ]
    ];

    return $cells;
}
```

### Menu Item Properties

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `type` | string | ✅ | Must be `ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU` |
| `label` | string | ✅ | Display text for the menu item |
| `icon` | string | ✅ | Bootstrap icon class (e.g., `bi-file-earmark-text`) |
| `url` | string | ✅ | Destination URL when menu item is clicked |
| `order` | int | ✅ | Sort order (lower numbers appear first) |
| `color` | string | ✅ | Bootstrap button color variant |
| `badge` | int\|null | ❌ | Optional notification badge count |
| `validRoutes` | array | ✅ | Routes where this menu item should appear |

### Available Bootstrap Colors

- `primary` - Blue (default)
- `secondary` - Gray
- `success` - Green
- `danger` - Red
- `warning` - Yellow/Orange
- `info` - Light blue
- `light` - White
- `dark` - Black

### Available Bootstrap Icons

Common icons for mobile menu actions:

- `bi-file-earmark-text` - Document/waiver submission
- `bi-file-earmark-check` - Authorization/approval
- `bi-check-circle` - Approve/confirm
- `bi-plus-circle` - Add/create
- `bi-pencil-square` - Edit
- `bi-calendar-event` - Events/calendar
- `bi-person-badge` - Profile/credentials
- `bi-shield-check` - Security/verification

Full icon list: https://icons.getbootstrap.com/

## Examples

### Example 1: Simple Action Button

```php
$cells[] = [
    'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
    'label' => 'My Profile',
    'icon' => 'bi-person-circle',
    'url' => '/members/mobile-profile',
    'order' => 5,
    'color' => 'secondary',
    'badge' => null,
    'validRoutes' => [
        ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null],
    ]
];
```

### Example 2: Action with Notification Badge

```php
$cells[] = [
    'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
    'label' => 'Approve Authorizations',
    'icon' => 'bi-check-circle',
    'url' => '/activities/mobile-approve-authorizations',
    'order' => 20,
    'color' => 'warning',
    'badge' => 5, // Shows "5" badge on button
    'validRoutes' => [
        ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null],
    ]
];
```

### Example 3: Multiple Menu Items from One Plugin

```php
public static function getViewCells(array $urlParams, $user = null): array
{
    $cells = [];

    // Request new authorization
    $cells[] = [
        'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
        'label' => 'Request Authorization',
        'icon' => 'bi-file-earmark-check',
        'url' => '/activities/mobile-request',
        'order' => 10,
        'color' => 'success',
        'badge' => null,
        'validRoutes' => [
            ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null],
        ]
    ];

    // Approve pending authorizations (with badge count)
    $cells[] = [
        'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
        'label' => 'Approve Authorizations',
        'icon' => 'bi-check-circle',
        'url' => '/activities/mobile-approve',
        'order' => 20,
        'color' => 'primary',
        'badge' => $this->getPendingCount($user), // Dynamic badge
        'validRoutes' => [
            ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null],
        ]
    ];

    return $cells;
}
```

## Menu Display Order Guidelines

Suggested order ranges by category:

- **1-10**: Primary profile actions (view profile, edit profile)
- **10-20**: Authorization/approval actions
- **20-30**: Waiver/document actions
- **30-40**: Event/calendar actions
- **40-50**: Administrative actions
- **50+**: Settings and help

## UI/UX Features

### Floating Action Button (FAB)

- Fixed position at bottom-right of screen
- 56x56px circular button
- Rotates 90° when menu is open
- Smooth hover and click animations
- Accessible with ARIA labels

### Menu Panel

- Slides up from bottom with animation
- Full-width buttons with icons and labels
- Notification badges on right side
- Auto-closes when item clicked
- Can be closed by clicking FAB again
- Responsive on all mobile devices

### Styling

The menu uses mobile-optimized styling:

- Large touch targets (min 44x44px)
- Clear visual hierarchy
- Smooth animations (300ms)
- High contrast for readability
- Shadow effects for depth
- Respects Bootstrap theme colors

## Creating Mobile-Optimized Target Pages

When creating the target pages for menu items, follow these guidelines:

### 1. Use Mobile-First Layout

```php
// In your controller action
$this->viewBuilder()->setLayout('mobile'); // Or ajax for minimal layout
```

### 2. Large Touch Targets

Ensure all interactive elements are at least 44x44px for easy touch interaction.

### 3. Simple Forms

- Minimize input fields
- Use native HTML5 input types (date, email, tel, etc.)
- Show clear validation messages
- Use large submit buttons

### 4. PWA-Ready

Consider offline functionality and service worker integration for full PWA experience.

## Troubleshooting

### Menu Not Appearing

1. Check plugin is enabled: `StaticHelpers::pluginEnabled('YourPlugin')`
2. Verify route matches: `['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null]`
3. Check ViewCellProvider is registered in plugin bootstrap
4. Verify menu items JSON is valid in browser console

### Menu Items Not Rendering

1. Check JavaScript console for parsing errors
2. Verify all required properties are present
3. Ensure `order` is a number, not string
4. Check icon class is valid Bootstrap icon

### Styling Issues

1. Ensure Bootstrap CSS is loaded
2. Verify no CSS conflicts with custom styles
3. Check mobile viewport is set correctly
4. Test on actual mobile device, not just browser DevTools

## Testing

### Manual Testing Checklist

- [ ] FAB button appears on mobile card
- [ ] FAB rotates when clicked
- [ ] Menu slides in smoothly
- [ ] All menu items display correctly
- [ ] Icons render properly
- [ ] Badges show when present
- [ ] Links navigate correctly
- [ ] Menu closes after item click
- [ ] Menu closes when FAB clicked again
- [ ] Responsive on various screen sizes
- [ ] Works offline (if PWA features added)

### Browser Testing

Test on:
- iOS Safari
- Android Chrome
- Mobile Firefox
- Desktop browsers (responsive mode)

## Future Enhancements

Potential improvements:

1. **Dynamic Badges**: Real-time updates via WebSocket
2. **Contextual Menus**: Show different items based on user role/permissions
3. **Sub-menus**: Nested menu structure for complex workflows
4. **Quick Actions**: Swipe gestures on menu items
5. **Persistent Menu State**: Remember open/closed state
6. **Haptic Feedback**: Vibration on mobile devices
7. **Voice Commands**: Accessibility feature for navigation

## Related Documentation

- [Plugin Architecture](../5-plugins.md)
- [ViewCellRegistry](../11-extending-kmp.md#viewcellregistry)
- [Mobile PWA Documentation](../10-javascript-development.md#mobile-pwa)
- [Stimulus Controllers](../10-javascript-development.md#stimulus-controllers)
