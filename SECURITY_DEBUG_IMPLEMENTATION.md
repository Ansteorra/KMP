# Security Debug Information Feature - Implementation Summary

## Overview

Implemented a comprehensive security debugging system that displays user policies and authorization check logs in the application footer. This feature is only active in debug mode to ensure zero performance impact in production.

## Implemented Components

### 1. Authorization Tracking (`src/Services/AuthorizationService.php`)

**Enhanced the AuthorizationService class to track all authorization checks:**

```php
protected static array $authCheckLog = [];

public function checkCan(?KmpIdentityInterface $user, string $action, $resource, ...$optionalArgs): bool
{
    // ... existing authorization logic ...
    
    // Only log in debug mode
    if (\Cake\Core\Configure::read('debug')) {
        $this->logAuthCheck($user, $action, $resource, $resultBool, $optionalArgs);
    }
    
    return $resultBool;
}
```

**Key methods added:**
- `logAuthCheck()` - Records authorization check details
- `getAuthCheckLog()` - Static method to retrieve logged checks
- `clearAuthCheckLog()` - Static method to clear the log
- `getResourceInfo()` - Formats resource information for display

**What is tracked:**
- Timestamp of the check
- User ID performing the check
- Action being checked (e.g., 'view', 'edit', 'delete')
- Resource being accessed (entity type and ID)
- Result (granted/denied)
- Number of additional arguments

### 2. Security Debug Helper (`src/View/Helper/SecurityDebugHelper.php`)

**Created a new view helper to render security information:**

**Main methods:**
- `displaySecurityInfo($user)` - Main display method that shows both policies and checks
- `displayUserPolicies($user)` - Renders table of user's policies with branch scope
- `displayAuthorizationChecks()` - Renders table of authorization checks

**Display features:**
- Bootstrap-styled responsive tables
- Color-coded results (green for granted, red for denied)
- Compact display for large policy lists
- Formatted scoping rules with badges
- Short class names for readability

### 3. Stimulus Controller (`assets/js/controllers/security-debug-controller.js`)

**JavaScript controller for interactive UI:**

```javascript
class SecurityDebugController extends Controller {
    static targets = ["panel", "toggleBtn"]
    
    toggle(event) {
        // Toggle visibility and update button text
    }
    
    show() {
        // Display panel with smooth scroll
    }
    
    hide() {
        // Hide panel
    }
}
```

**Features:**
- Toggle button functionality
- Smooth animations
- Auto-scroll to panel when opened
- Button text updates ('Show' ↔ 'Hide')

### 4. Footer Integration (`templates/element/copyrightFooter.php`)

**Added debug UI to the footer element:**

```php
<?php if (Configure::read('debug')) : ?>
    <li class="nav-item text-nowrap mx-2">
        <a href="#" 
           class="btn btn-sm btn-outline-warning" 
           data-action="click->security-debug#toggle"
           data-security-debug-target="toggleBtn">
            Show Security Info
        </a>
    </li>
<?php endif; ?>

<!-- Security info panel -->
<?php if (Configure::read('debug')) : ?>
    <div data-controller="security-debug">
        <div data-security-debug-target="panel" style="display: none;">
            <?php echo $this->SecurityDebug->displaySecurityInfo($currentUser); ?>
        </div>
    </div>
<?php endif; ?>
```

**Key aspects:**
- Only visible when debug mode is enabled
- Integrated with existing footer design
- Uses Bootstrap button styling
- Connected to Stimulus controller

### 5. Helper Registration (`src/View/AppView.php`)

**Registered the SecurityDebug helper:**

```php
$helpers = [
    'Tools.Format',
    'Tools.Time',
    'Templating.Icon',
    'Templating.IconSnippet',
    'Timezone',
    'SecurityDebug',  // Added
];
```

### 6. Asset Compilation (`assets/js/index.js`)

**Imported the security debug controller:**

```javascript
import './controllers/qrcode-controller.js';
import './controllers/timezone-input-controller.js';
import './controllers/security-debug-controller.js';  // Added
```

### 7. Documentation (`docs/7.4-security-debug-information.md`)

**Created comprehensive documentation covering:**
- Feature overview and architecture
- Usage instructions
- Troubleshooting guide
- Performance considerations
- Security implications
- Integration details

### 8. Tests (`tests/TestCase/Services/SecurityDebugTest.php`)

**Created test suite to verify:**
- Logging only occurs in debug mode
- Log captures both granted and denied checks
- Log can be cleared
- Resource information is formatted correctly
- No logging when debug mode is disabled

## How It Works

### Debug Mode Detection

```php
// Multiple checks ensure production safety:
1. Configure::read('debug') check in AuthorizationService
2. Configure::read('debug') check in footer template
3. Helper returns empty string if not debug mode
```

### Authorization Flow

```
1. Controller/Policy performs authorization check
   ↓
2. AuthorizationService->checkCan() is called
   ↓
3. If debug mode: Log check details
   ↓
4. Return authorization result
   ↓
5. On page render: Display logged checks in footer
```

### User Policies Display

```
1. Get current user from Identity helper
   ↓
2. Call $user->getPolicies() to get all policies
   ↓
3. Format policies with:
   - Policy class and method names
   - Scoping rules (Global/Branch Only/Branch + Children)
   - Associated branch IDs
   ↓
4. Render as Bootstrap table
```

## Usage

### For Developers

**During Development:**
1. Navigate to any page in the application
2. Scroll to the footer
3. Click "Show Security Info" button
4. Review:
   - Your assigned policies and their scope
   - All authorization checks performed
   - Which checks passed/failed

**Debugging Authorization Issues:**
1. Reproduce the authorization problem
2. Check the authorization log
3. Identify denied checks
4. Verify user has required policies
5. Check branch scope matches

### For System Administrators

**Security Auditing:**
- Review which policies users have
- Understand authorization flow for specific actions
- Identify overly permissive or restrictive policies
- Verify branch scoping is correct

## Key Features

### Performance

✅ **Zero impact in production**
- Only tracks when debug mode is enabled
- Conditionally compiled code
- Lightweight data structures
- In-memory storage only

✅ **Minimal overhead in debug**
- Simple array logging
- No database queries
- No file I/O
- Cleared per request

### Security

✅ **Multiple safeguards**
- Debug mode must be enabled
- No sensitive data exposed
- Shows structure, not content
- Production-safe by design

✅ **Information provided**
- Policy assignments
- Authorization decisions
- Scope information
- Check sequence

### Usability

✅ **Developer-friendly**
- Clear visual presentation
- Color-coded results
- Searchable HTML tables
- Context-aware display

✅ **Non-intrusive**
- Footer placement
- Toggle visibility
- Smooth animations
- Responsive design

## Display Format

### User Policies Table

```
┌──────────────────┬─────────────────┬─────────────────┬──────────────┐
│ Policy Class     │ Method          │ Scope           │ Branches     │
├──────────────────┼─────────────────┼─────────────────┼──────────────┤
│ MemberPolicy     │ canView         │ 🔵 Global       │ All branches │
│                  │ canEdit         │ 🔵 Branch Only  │ 1, 5, 12     │
└──────────────────┴─────────────────┴─────────────────┴──────────────┘
```

### Authorization Check Log

```
┌───┬─────────┬──────────────┬────────────┬──────┐
│ # │ Action  │ Resource     │ Result     │ Args │
├───┼─────────┼──────────────┼────────────┼──────┤
│ 1 │ view    │ Member #123  │ ✓ Granted  │ 0    │
│ 2 │ edit    │ Member #123  │ ✗ Denied   │ 1    │
└───┴─────────┴──────────────┴────────────┴──────┘
```

## Files Modified/Created

### Modified Files
1. `app/src/Services/AuthorizationService.php` - Added tracking
2. `app/src/View/AppView.php` - Registered helper
3. `app/templates/element/copyrightFooter.php` - Added UI
4. `app/assets/js/index.js` - Imported controller

### New Files
1. `app/src/View/Helper/SecurityDebugHelper.php` - Display helper
2. `app/assets/js/controllers/security-debug-controller.js` - UI controller
3. `app/tests/TestCase/Services/SecurityDebugTest.php` - Test suite
4. `docs/7.4-security-debug-information.md` - Documentation

## Testing

### Manual Testing
1. Enable debug mode: `Configure::write('debug', true)`
2. Log in to the application
3. Navigate to any page
4. Verify "Show Security Info" button appears in footer
5. Click button to view security information
6. Verify policies are displayed
7. Verify authorization checks are logged
8. Disable debug mode
9. Verify button is hidden

### Automated Testing
Run the test suite:
```bash
vendor/bin/phpunit tests/TestCase/Services/SecurityDebugTest.php
```

Tests verify:
- Logging only in debug mode
- Log structure and content
- Clear functionality
- Resource formatting

## Future Enhancements

Potential improvements for future iterations:
1. Filter authorization log by action type
2. Export log to JSON/CSV for analysis
3. Stack traces for authorization checks
4. Performance metrics (time per check)
5. Policy rule explanation/reasoning
6. Historical comparison across requests
7. Search/filter functionality in tables

## Conclusion

This implementation provides a powerful debugging tool for the KMP authorization system while maintaining production safety through multiple layers of protection. The feature integrates seamlessly with the existing architecture and provides clear, actionable information for developers and administrators.
