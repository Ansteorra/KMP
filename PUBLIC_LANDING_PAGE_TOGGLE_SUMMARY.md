# Public Landing Page Toggle - Implementation Summary

## Overview
Added the ability to enable/disable the public landing page for gatherings. This allows gathering stewards to control whether the public can access the event page.

## Changes Made

### 1. Database Migration
**File**: `config/Migrations/20251103215023_AddPublicPageEnabledToGatherings.php`

- Added `public_page_enabled` boolean column to `gatherings` table
- Default value: `true` (public pages enabled by default for backwards compatibility)
- Not nullable with comment explaining purpose

### 2. Entity Updates
**File**: `src/Model/Entity/Gathering.php`

- Added `@property bool $public_page_enabled` to PHPDoc
- Added `'public_page_enabled' => true` to `$_accessible` array

### 3. Controller Updates
**File**: `src/Controller/GatheringsController.php`

#### `publicLanding()` Method
- Added check for `$gathering->public_page_enabled`
- Throws `NotFoundException` with message if public page is disabled
- Message: "The public page for this gathering is not available."

#### `clone()` Method
- Added `public_page_enabled` to cloned gathering data
- Preserves the public page setting from original gathering

### 4. Form Templates

#### Add Form (`templates/Gatherings/add.php`)
- Added checkbox for "Enable Public Landing Page"
- Default: checked (true)
- Help text explains what the option does
- Positioned after description field

#### Edit Form (`templates/Gatherings/edit.php`)
- Added identical checkbox as in add form
- Allows toggling the setting for existing gatherings

### 5. View Template
**File**: `templates/Gatherings/view.php`

#### Public Landing Page Status Row
- Added new row in record details table
- Shows status badge:
  - **Enabled**: Green badge with checkmark + link to view public page
  - **Disabled**: Gray badge with X + explanatory text

#### Share Event Dropdown
- Button disabled when public page is disabled
- Dropdown content changes based on status:
  - **Enabled**: Shows "View Public Page", "Copy Link", "Show QR Code" options
  - **Disabled**: Shows informational message explaining public page is disabled

#### QR Code Modal
- Only renders when `$gathering->public_page_enabled` is true
- Prevents unnecessary code from loading when feature is disabled

## User Experience

### When Public Page is Enabled (Default)
- Users can share the event via multiple methods
- Public landing page is accessible to anyone with the link
- QR code can be generated and shared
- Status shows green "Enabled" badge

### When Public Page is Disabled
- Public cannot access the landing page (404 error with message)
- "Share Event" button is disabled
- Status shows gray "Disabled" badge with explanation
- QR code modal is not rendered
- Edit form allows re-enabling the feature

## Security & Privacy

### Access Control
- Public page access is controlled at the controller level
- NotFoundException prevents information leakage
- Authenticated users can still view gathering details via normal view page

### Default Behavior
- New gatherings default to **enabled** for backwards compatibility
- Existing gatherings (via migration) default to **enabled**
- Cloned gatherings inherit the setting from original

## Use Cases

### When to Disable Public Landing Page
1. **Internal Events**: Private practices or meetings
2. **Draft/Planning Stage**: Gathering not ready for public announcement
3. **Invite-Only Events**: Controlled attendance list
4. **Post-Event**: After gathering concludes, disable public access

### When to Keep Public Landing Page Enabled
1. **Public Events**: Tournaments, feasts, workshops
2. **Recruitment**: Events designed to attract new members
3. **Large Gatherings**: Events with external attendance
4. **Multi-Group Events**: When sharing across branches

## Technical Details

### Migration Details
```php
$table->addColumn('public_page_enabled', 'boolean', [
    'default' => true,
    'null' => false,
    'comment' => 'Whether the public landing page is enabled for this gathering',
]);
```

### Controller Check
```php
if (!$gathering->public_page_enabled) {
    throw new NotFoundException(__('The public page for this gathering is not available.'));
}
```

### Form Checkbox
```php
<?= $this->Form->checkbox('public_page_enabled', [
    'checked' => true,  // Default for new gatherings
    'id' => 'public_page_enabled',
    'class' => 'form-check-input'
]) ?>
```

## Testing Recommendations

1. **Create New Gathering**:
   - Verify checkbox is checked by default
   - Create gathering and verify public page works
   - Uncheck box, create gathering, verify public page is blocked

2. **Edit Existing Gathering**:
   - Disable public page on existing gathering
   - Verify "Share Event" button is disabled
   - Verify public URL returns 404 with message
   - Re-enable and verify access is restored

3. **Clone Gathering**:
   - Clone gathering with public page enabled
   - Verify clone also has public page enabled
   - Clone gathering with public page disabled
   - Verify clone also has public page disabled

4. **UI Indicators**:
   - Check status badge shows correct state
   - Verify QR code modal only appears when enabled
   - Test share dropdown functionality in both states

5. **Public Access**:
   - In incognito/private browsing mode, test public URL
   - When enabled: should see full event page
   - When disabled: should see 404 error page

## Files Modified

1. `config/Migrations/20251103215023_AddPublicPageEnabledToGatherings.php` - NEW
2. `src/Model/Entity/Gathering.php` - Updated
3. `src/Controller/GatheringsController.php` - Updated (2 methods)
4. `templates/Gatherings/add.php` - Updated
5. `templates/Gatherings/edit.php` - Updated
6. `templates/Gatherings/view.php` - Updated

## Backwards Compatibility

- Existing gatherings default to `public_page_enabled = true`
- No breaking changes to existing functionality
- Feature is opt-out rather than opt-in
- All existing public landing page URLs continue to work

## Future Enhancements

Potential improvements to consider:

1. **Bulk Toggle**: Allow toggling public page for multiple gatherings
2. **Schedule Enable**: Auto-enable public page on a specific date
3. **Access Log**: Track who accesses the public page
4. **Custom Message**: Allow custom message when public page is disabled
5. **Preview Mode**: Allow preview of public page even when disabled
6. **Analytics**: Track views/shares of public landing page
