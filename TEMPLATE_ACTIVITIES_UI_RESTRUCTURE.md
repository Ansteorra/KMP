# Template Activities UI Restructure - Summary

## Overview
Restructured the UI for managing template activities on gathering types to match the pattern used for managing activities on gatherings. Template activities are now managed via a tab on the gathering type view page instead of during type creation/editing.

## Changes Made

### 1. Controller Updates - `GatheringTypesController.php`

#### New Methods Added:
- **`addActivity($id)`**: Adds a template activity to a gathering type
  - Accepts `activity_id` and `not_removable` flag
  - Validates that activity isn't already added
  - Creates link in `gathering_type_gathering_activities` table
  - Redirects back to view page with flash message

- **`removeActivity($gatheringTypeId, $activityId)`**: Removes a template activity from a gathering type
  - Finds and deletes the join table record
  - Redirects back to view page with flash message

#### Modified Methods:
- **`view($id)`**: 
  - Now loads `$availableActivities` for the add activity modal
  - Passes to view template

- **`add()`**:
  - Simplified to only handle gathering type properties
  - Removed template activities handling
  - No longer loads `$gatheringActivities`
  - Redirects to view page after successful creation (was index)

- **`edit($id)`**:
  - Simplified to only handle gathering type properties
  - Removed template activities handling
  - No longer contains activities or loads `$gatheringActivities`

### 2. Template Updates

#### `templates/GatheringTypes/add.php`
- **Removed**:
  - Entire "Template Activities" fieldset
  - JavaScript for managing activity checkboxes
  - `$gatheringActivities` from doc block
- **Result**: Clean, simple form with just gathering type properties

#### `templates/GatheringTypes/edit.php`
- **Removed**:
  - Entire "Template Activities" fieldset
  - JavaScript for managing activity checkboxes
  - Activity selection and not_removable checkbox logic
  - `$gatheringActivities` from doc block
- **Result**: Clean form matching add.php structure

#### `templates/GatheringTypes/view.php`
- **Added**:
  - "Add Template Activity" button (only visible to users with edit permission)
  - "Remove" action button per activity row (only visible to users with edit permission)
  - Add Activity Modal with:
    - Activity dropdown selector
    - "Not Removable" checkbox with explanation
    - Cancel/Add buttons
  - `$availableActivities` to doc block
  
- **Modified**:
  - Template Activities tab now shows table with remove buttons
  - Authorization checks on action buttons

## User Workflow

### Before (Old Workflow):
1. Create gathering type → Select template activities during creation → Save
2. Edit gathering type → Modify template activities list → Save

### After (New Workflow):
1. Create gathering type → Fill in basic properties → Save
2. View gathering type → Go to Template Activities tab → Add/remove activities as needed

## Benefits of New Approach

1. **Consistency**: Matches the pattern used for managing activities on gatherings
2. **Clarity**: Type creation is simpler and focused on core properties
3. **Flexibility**: Activities can be added/removed without editing the type
4. **User Experience**: Modal-based activity management is more intuitive
5. **Authorization**: Per-action authorization checks are clearer

## Technical Details

### Routes
- `POST /gathering-types/addActivity/{id}`: Add template activity to type
- `POST /gathering-types/removeActivity/{typeId}/{activityId}`: Remove template activity from type

### Database
No schema changes required - uses existing `gathering_type_gathering_activities` join table with:
- `gathering_type_id`
- `gathering_activity_id`
- `not_removable` boolean flag
- Audit fields (created, modified, created_by_id, modified_by_id)

### Authorization
- Both new actions use `edit` permission check on the gathering type
- UI elements are conditionally rendered based on `$user->checkCan("edit", $gatheringType)`

## Testing Recommendations

1. **Create new gathering type**:
   - Verify form only shows basic properties
   - Verify redirect to view page after creation
   
2. **Add template activity**:
   - Click "Add Template Activity" button
   - Select activity and optionally check "Not Removable"
   - Verify activity appears in table
   - Verify activity is marked as not_removable if checked
   
3. **Remove template activity**:
   - Click remove button on activity row
   - Verify confirmation prompt
   - Verify activity is removed from list
   
4. **Create new gathering with type**:
   - Create gathering using type with template activities
   - Verify template activities are automatically added to gathering
   - Verify not_removable flag is correctly applied

5. **Authorization checks**:
   - Test as user without edit permission
   - Verify "Add Template Activity" button is hidden
   - Verify "Remove" buttons are hidden
   - Verify direct access to actions is denied

## Files Modified

- `/app/src/Controller/GatheringTypesController.php`
- `/app/templates/GatheringTypes/add.php`
- `/app/templates/GatheringTypes/edit.php`
- `/app/templates/GatheringTypes/view.php`

## Compatibility Notes

- No database migrations needed
- No changes to existing gathering type records
- Existing template activities continue to work
- Automatic syncing to gatherings unchanged
