# Mobile Authorization Request Feature

## Overview

The mobile authorization request feature provides a mobile-optimized interface for requesting activity authorizations through the PWA mobile card. This is the first fully implemented mobile feature using the mobile menu system.

## Architecture

### Controller
- **File**: `plugins/Activities/src/Controller/AuthorizationsController.php`
- **Action**: `mobileRequestAuthorization()`
- **Purpose**: Handles mobile authorization requests with simplified workflow

**Key Features**:
- Authentication validation
- Activity list loading
- Ajax layout for mobile optimization
- Authorization skip (public endpoint for authenticated users)

### Template
- **File**: `plugins/Activities/templates/Authorizations/mobile_request_authorization.php`
- **Layout**: Ajax (minimal chrome)
- **Style**: Embedded mobile-optimized CSS

**Mobile Optimizations**:
- Large touch targets (14px-16px padding)
- Simplified single-page form
- Clear visual hierarchy
- Responsive container (max-width 600px)
- Touch-friendly dropdowns

### Stimulus Controller
- **File**: `plugins/Activities/assets/js/controllers/mobile-request-auth-controller.js`
- **Identifier**: `activities-mobile-request-auth`

**Features**:
- Online/offline detection via `navigator.onLine`
- Dynamic approver loading based on activity selection
- Form validation before submission
- Loading states with spinner
- Touch-optimized interactions

## Online-Only Functionality

The feature **requires an internet connection** and implements several safeguards:

### Detection Methods
1. **Initial Check**: On controller connect, checks `navigator.onLine`
2. **Event Listeners**: Monitors `online` and `offline` events
3. **Submission Guard**: Prevents form submission when offline

### Offline Behavior
When offline, the system:
- Shows warning banner with offline message
- Disables activity dropdown
- Disables approver dropdown
- Disables submit button
- Updates help text to inform user

### Online Restoration
When connection is restored:
- Hides warning banner
- Re-enables activity dropdown
- Allows normal workflow to proceed
- Updates help text appropriately

## User Workflow

### 1. Navigation
User clicks "Request Authorization" from mobile menu (green button with checkmark icon).

### 2. Activity Selection
- User sees dropdown of all active activities
- Help text: "What activity do you want to be authorized for?"
- On selection, triggers approver loading

### 3. Approver Loading
- System fetches approvers via AJAX
- URL: `/activities/activities/approversList/{activityId}/{memberId}`
- Dropdown updates with available approvers
- Shows count: "X approver(s) available"

### 4. Approver Selection
- User selects who should approve their request
- Submit button becomes enabled when both selections made

### 5. Submission
- Form POSTs to `/activities/authorizations/add`
- Parameters: `member_id`, `activity`, `approver_id`
- Loading state shown during submission
- Success redirects back to mobile card with Flash message

## Integration Points

### Menu Registration
- **File**: `plugins/Activities/src/Services/ActivitiesViewCellProvider.php`
- **Type**: `ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU`
- **Order**: 10 (first item in menu)
- **Color**: Success (green)
- **Icon**: `bi-file-earmark-check`

```php
$cells[] = [
    'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
    'label' => 'Request Authorization',
    'icon' => 'bi-file-earmark-check',
    'url' => ['controller' => 'Authorizations', 'action' => 'mobileRequestAuthorization', 'plugin' => 'Activities'],
    'order' => 10,
    'color' => 'success',
    'badge' => null,
    'validRoutes' => [
        ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null],
    ]
];
```

### URL Building
The mobile card template converts URL arrays to strings using `$this->Url->build()`:

```php
foreach ($mobileMenuItems as &$item) {
    if (is_array($item['url'])) {
        $item['url'] = $this->Url->build($item['url']);
    }
}
```

### API Endpoints
1. **Approvers List**: `/activities/activities/approversList/{activityId}/{memberId}`
   - Returns JSON with available approvers
   - Used for dynamic dropdown population

2. **Submit Request**: `/activities/authorizations/add`
   - Accepts form POST with authorization details
   - Existing endpoint reused for mobile

## Mobile UI Design

### Color Scheme
- **Primary Action**: Green (#198754) - success color
- **Secondary Action**: Gray (#6c757d)
- **Warning**: Yellow (#ffc107) for online-required message
- **Error**: Red (#dc3545) for offline state

### Touch Targets
All interactive elements meet mobile accessibility guidelines:
- Buttons: 16px padding, 18px font
- Inputs: 14px padding, 16px font
- Minimum height: 48px (touch target size)

### Visual Feedback
- Hover states with transform: `translateY(-2px)`
- Box shadows on hover: `0 4px 12px rgba()`
- Focus rings: `box-shadow: 0 0 0 3px rgba()`
- Loading spinner during async operations

### Responsive Design
- Container: max-width 600px, centered
- Padding: 16px around content
- Cards: white background, 12px border-radius
- Shadows: subtle `0 2px 8px rgba(0, 0, 0, 0.1)`

## Testing Considerations

### Manual Testing
1. **Online Workflow**:
   - Navigate to mobile card
   - Open menu, click "Request Authorization"
   - Select activity, verify approvers load
   - Select approver, submit request
   - Verify success message and redirect

2. **Offline Detection**:
   - Enable airplane mode or disconnect network
   - Verify warning banner appears
   - Verify form elements disabled
   - Re-enable network
   - Verify form becomes usable

3. **Validation**:
   - Verify submit disabled until both selections made
   - Verify loading state during submission
   - Verify error handling for failed requests

### Automated Testing
Consider adding Playwright tests for:
- Mobile viewport navigation
- Online/offline state transitions
- Form submission workflow
- Dynamic approver loading
- Error condition handling

## Future Enhancements

### Possible Additions
1. **Badge Count**: Show pending requests count on menu button
2. **Recent Requests**: Show user's recent authorization requests
3. **Status Tracking**: Real-time updates on request approval
4. **Offline Queue**: Cache requests when offline, submit when online
5. **Push Notifications**: Notify when authorization approved/denied
6. **Quick Actions**: Pre-fill common activity/approver combinations

### Performance Optimizations
1. **Caching**: Cache approver lists by activity ID
2. **Prefetch**: Load common activities on menu open
3. **Service Worker**: Background sync for offline submissions
4. **Image Optimization**: Lazy load any future profile images

## Related Documentation

- [Mobile Card Menu System](mobile-card-menu-system.md) - Overall menu architecture
- [Mobile Card PWA](../templates/Members/view_mobile_card.php) - Main mobile interface
- [Activities Plugin](5.6-activities-plugin.md) - Full plugin documentation
- [ViewCellRegistry](../src/Services/ViewCellRegistry.php) - Plugin integration system

## Troubleshooting

### Menu Item Not Appearing
1. Verify Activities plugin is enabled
2. Check ViewCellRegistry has PLUGIN_TYPE_MOBILE_MENU constant
3. Confirm assets compiled successfully (`npm run dev`)
4. Check browser console for JavaScript errors

### Approvers Not Loading
1. Verify network connectivity
2. Check ApproversList endpoint exists and returns JSON
3. Verify member_id and activity_id are valid
3. Check browser network tab for API errors

### Form Submission Fails
1. Verify CSRF token is present (CakePHP auto-includes)
2. Check authentication is valid
3. Verify authorization add endpoint accepts POST
4. Check server logs for validation errors

### Offline Detection Not Working
1. Verify browser supports `navigator.onLine`
2. Check service worker is registered
3. Test actual network disconnect vs. browser developer tools
4. Verify event listeners are attached in controller

## Example Code Snippets

### Adding a New Mobile Feature
To add another mobile feature following this pattern:

```php
// In plugin's ViewCellProvider
$cells[] = [
    'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
    'label' => 'My Feature',
    'icon' => 'bi-icon-name',
    'url' => ['controller' => 'MyController', 'action' => 'mobileAction', 'plugin' => 'MyPlugin'],
    'order' => 30,
    'color' => 'info',
    'badge' => null,
    'validRoutes' => [
        ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null],
    ]
];
```

```php
// In controller
public function mobileAction()
{
    $currentUser = $this->Authentication->getIdentity();
    if (!$currentUser) {
        $this->Flash->error(__('You must be logged in.'));
        return $this->redirect(['controller' => 'Members', 'action' => 'login', 'plugin' => null]);
    }
    $this->Authorization->skipAuthorization();
    
    // Load data
    $data = $this->MyModel->find()->all();
    
    $this->set(compact('data'));
    $this->viewBuilder()->setLayout('ajax');
}
```

### Creating Mobile Template
```php
<!-- Mobile-optimized template -->
<style>
    .mobile-container {
        max-width: 600px;
        margin: 0 auto;
        padding: 16px;
    }
    .mobile-btn {
        padding: 16px;
        font-size: 18px;
        width: 100%;
    }
</style>

<div class="mobile-container" data-controller="my-mobile-controller">
    <div class="mobile-card">
        <h2>My Feature</h2>
        <!-- Your mobile-optimized content -->
    </div>
</div>
```

### Creating Mobile Controller
```javascript
import { Controller } from "@hotwired/stimulus"

class MyMobileController extends Controller {
    static targets = ["form", "submit"]
    
    connect() {
        this.checkOnlineStatus()
        window.addEventListener('online', this.checkOnlineStatus.bind(this))
        window.addEventListener('offline', this.checkOnlineStatus.bind(this))
    }
    
    checkOnlineStatus() {
        const isOnline = navigator.onLine
        this.submitTarget.disabled = !isOnline
    }
    
    disconnect() {
        window.removeEventListener('online', this.checkOnlineStatus.bind(this))
        window.removeEventListener('offline', this.checkOnlineStatus.bind(this))
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["my-mobile"] = MyMobileController

export default MyMobileController
```

## Conclusion

The mobile authorization request feature demonstrates the complete pattern for building mobile-optimized features in KMP. It showcases:
- Plugin-based extensibility
- Online-only functionality enforcement
- Touch-optimized UI design
- Progressive enhancement with offline detection
- Reuse of existing API endpoints

This pattern can be replicated for other mobile features, creating a consistent and maintainable mobile experience across the application.
