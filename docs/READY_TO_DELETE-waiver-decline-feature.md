# Waiver Decline Feature Implementation Summary

## Overview
Implemented functionality to allow authorized users to decline/reject invalid waivers within 30 days of upload.

## Database Changes

### Migration: AddDeclineFieldsToGatheringWaivers
**File:** `/workspaces/KMP/app/plugins/Waivers/config/Migrations/20251026000000_AddDeclineFieldsToGatheringWaivers.php`

Added three new fields to `waivers_gathering_waivers` table:
- `declined_at` (datetime): Timestamp when waiver was declined
- `declined_by` (integer): Foreign key to members table for the user who declined
- `decline_reason` (text): Required reason for declining the waiver

Also added:
- Index on `declined_at` for performance
- Foreign key constraint for `declined_by` -> `members.id`

## Model Updates

### GatheringWaiver Entity
**File:** `/workspaces/KMP/app/plugins/Waivers/src/Model/Entity/GatheringWaiver.php`

**New Properties:**
- `declined_at`: DateTime when waiver was declined
- `declined_by`: Member ID who declined the waiver
- `decline_reason`: Reason for declining
- `declined_by_member`: Association to Member entity

**New Virtual Fields:**
- `is_declined`: Returns true if waiver has been declined
- `can_be_declined`: Returns true if waiver meets decline criteria:
  - Not already declined
  - Not expired or deleted
  - Within 30 days of upload date

**Updated Virtual Fields:**
- `status_badge_class`: Shows 'badge-danger' for declined waivers
- `status_display`: Shows 'Declined' for declined waivers

### GatheringWaiversTable
**File:** `/workspaces/KMP/app/plugins/Waivers/src/Model/Table/GatheringWaiversTable.php`

**New Association:**
- `DeclinedByMembers`: BelongsTo association to Members table

## Controller Updates

### GatheringWaiversController
**File:** `/workspaces/KMP/app/plugins/Waivers/src/Controller/GatheringWaiversController.php`

**New Action: `decline()`**
- Method: POST, PUT, PATCH
- Authorization: Checks `canDecline` permission via policy
- Business Rules Enforced:
  - Waiver must pass `can_be_declined` check
  - Decline reason is required
  - Sets `declined_at`, `declined_by`, `decline_reason`
  - Updates status to 'declined'
- Logging: Logs decline action with details
- Flash Messages: Success/error feedback to user

**Updated `view()` action:**
- Added `DeclinedByMembers` to contain array to load decline information

## Authorization Updates

### GatheringWaiverPolicy
**File:** `/workspaces/KMP/app/plugins/Waivers/src/Policy/GatheringWaiverPolicy.php`

**New Policy Method:**
- `canDecline()`: Checks if user has decline permission for the waiver's gathering branch
  - Uses `_hasPolicy()` from BasePolicy
  - Integrates with KMP RBAC system
  - Branch-scoped permission checking

## View Updates

### View Waiver Template
**File:** `/workspaces/KMP/app/plugins/Waivers/templates/GatheringWaivers/view.php`

**Record Actions Section:**
- Added "Decline Waiver" button (shown only if user has permission and waiver can be declined)
- Button triggers decline modal dialog

**Status Display:**
- Shows "Declined" badge if waiver is declined
- Displays decline information:
  - Declined at timestamp
  - Declined by member (with link to profile)
  - Decline reason (in alert box with icon)

**New Modal: Decline Waiver**
- Warning message about irreversible action
- Shows waiver details being declined
- Required textarea for decline reason
- Helper text about 30-day window
- Submit button to confirm decline

### Index Template
**File:** `/workspaces/KMP/app/plugins/Waivers/templates/GatheringWaivers/index.php`

**Status Column Updates:**
- Shows "Declined" badge for declined waivers
- Shows "Can be declined" indicator for eligible waivers

## Business Rules

### When Can a Waiver Be Declined?
1. **Time Window**: Within 30 days of upload (`created` date)
2. **Not Already Declined**: `declined_at` must be null
3. **Status Check**: Status cannot be 'expired' or 'deleted'
4. **Permission**: User must have 'decline' permission for the gathering's branch

### Decline Process
1. User clicks "Decline Waiver" button
2. Modal opens with waiver details and reason field
3. User enters required decline reason
4. System validates:
   - Authorization check via policy
   - Business rules via `can_be_declined` check
   - Decline reason is not empty
5. If valid:
   - Sets `declined_at` to current timestamp
   - Sets `declined_by` to current user ID
   - Saves `decline_reason` text
   - Updates `status` to 'declined'
   - Logs the action
   - Shows success message
6. If invalid:
   - Shows appropriate error message
   - No changes made

## Permission Configuration

To allow users to decline waivers, they need:
- Permission: `canDecline` for `Waivers.GatheringWaivers`
- Scope: Branch-level (checked via gathering's branch)
- Action: Configured through KMP's RBAC system

Example permission setup in KMP:
```php
// In permission configuration or seed data
'Waivers.GatheringWaivers' => [
    'canDecline' => [
        'description' => 'Decline invalid waivers within 30 days',
        'requires_warrant' => true,  // Optional: require warrant
        'branch_scoped' => true,     // Branch-level permission
    ]
]
```

## UI/UX Features

### Visual Indicators
- **Declined Badge**: Red danger badge on declined waivers
- **Decline Button**: Red danger button with X icon
- **Warning Messages**: Clear warnings about irreversibility
- **Time Indicators**: Shows time since upload in modal

### User Feedback
- Success message when waiver declined successfully
- Error messages for various failure conditions:
  - Already declined
  - Expired or deleted status
  - Outside 30-day window
  - Missing decline reason
- Clear display of decline information on declined waivers

### Accessibility
- Proper ARIA labels on modal
- Clear button labels with icons
- Required field indicators
- Helper text for form fields

## Testing Recommendations

### Manual Testing
1. **Happy Path**: Decline a valid waiver within 30 days
2. **Permission Check**: Try declining without proper permissions
3. **Time Window**: Test with waivers older than 30 days
4. **Already Declined**: Try declining the same waiver twice
5. **Empty Reason**: Submit without decline reason
6. **Status Restrictions**: Try declining expired/deleted waivers

### Database Verification
```sql
-- Check declined waivers
SELECT id, gathering_id, status, created, declined_at, declined_by, 
       SUBSTRING(decline_reason, 1, 50) as reason_preview
FROM waivers_gathering_waivers
WHERE declined_at IS NOT NULL;

-- Check waivers eligible for decline
SELECT id, gathering_id, status, created,
       DATEDIFF(NOW(), created) as days_old
FROM waivers_gathering_waivers
WHERE declined_at IS NULL
  AND status NOT IN ('expired', 'deleted')
  AND created >= DATE_SUB(NOW(), INTERVAL 30 DAY);
```

## Future Enhancements

Potential improvements to consider:
1. **Notification System**: Notify uploader when waiver is declined
2. **Decline History**: Track decline attempts and revisions
3. **Bulk Decline**: Allow declining multiple waivers at once
4. **Decline Categories**: Pre-defined decline reason categories
5. **Appeal Process**: Allow uploaded to appeal declined waivers
6. **Reporting**: Dashboard showing decline statistics
7. **Configurable Window**: Make 30-day window configurable per organization

## Notes

- Decline is a soft operation - document is not deleted
- Declined waivers remain in database for audit purposes
- Decline reason is visible to anyone who can view the waiver
- The 30-day window is calculated from `created` timestamp
- Status changes from current status to 'declined'
- Authorization uses the same branch-scoping as other waiver operations
