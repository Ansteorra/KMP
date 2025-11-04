# Gathering Clone Enhancement - Implementation Summary

## Overview
Enhanced the gathering clone functionality to support cloning staff members and event schedules in addition to the existing activity cloning capability.

## Changes Made

### 1. Controller Enhancement (`src/Controller/GatheringsController.php`)

#### Updated `clone()` Method
- **Added Staff Cloning Logic**:
  - Checks for `clone_staff` parameter in request data
  - Clones all staff members with complete data:
    - `member_id` (if linked to a member)
    - `sca_name` (custom name or from member)
    - `role`
    - `is_steward`
    - `show_on_public_page`
    - `email`
    - `phone`
    - `contact_notes`
    - `sort_order`

- **Added Schedule Cloning Logic**:
  - Checks for `clone_schedule` parameter in request data
  - Calculates date offset between original and new gathering start dates
  - Clones scheduled activities with adjusted times:
    - Preserves `gathering_activity_id` link
    - Adjusts `start_datetime` by date offset
    - Adjusts `end_datetime` by date offset
    - Preserves `location`
    - Preserves `custom_description`
  - Uses `\Cake\I18n\Date::parse()` for proper type handling

- **Enhanced Feedback**:
  - Success message now includes counts of all cloned items
  - Format: "Gathering cloned successfully with {X} activities, {Y} staff members, and {Z} scheduled activities."

- **Bug Fixes**:
  - All redirects now use `public_id` instead of internal `id`

### 2. Template Enhancement (`templates/element/gatherings/cloneModal.php`)

#### Added Clone Options Section
- Created "Clone Options" heading for better organization
- Added three checkboxes (all checked by default):

1. **Clone Activities** (existing, enhanced):
   - Shows activity count from `$gathering->gathering_activities`
   - Help text: "Copies all activities associated with this gathering"

2. **Clone Staff** (new):
   - Shows staff count from `$gathering->gathering_staff`
   - Help text: "Copies all staff members including stewards, their roles, and contact information"

3. **Clone Schedule** (new):
   - Shows scheduled activity count from `$gathering->gathering_scheduled_activities`
   - Safe null checking for gatherings without schedules
   - Help text: "Copies the event schedule with times adjusted to match the new start date"

#### Updated Original Gathering Details Card
- Added "Staff" count display
- Added "Scheduled Activities" count display
- Provides clear visibility of what will be cloned

## Technical Details

### Date Offset Calculation
```php
$originalStart = \Cake\I18n\Date::parse($originalGathering->start_date);
$newStart = \Cake\I18n\Date::parse($newGathering->start_date);
$daysDiff = $originalStart->diffInDays($newStart, false);

// For each scheduled activity:
$newStartDateTime = $scheduledActivity->start_datetime->addDays($daysDiff);
$newEndDateTime = $scheduledActivity->end_datetime->addDays($daysDiff);
```

### Data Flow
1. User clicks "Clone" button on gathering view
2. Modal opens showing clone options with counts
3. User adjusts name, dates, and checkboxes
4. Form submits to `GatheringsController::clone()`
5. Controller loads original gathering with associations:
   - `GatheringActivities`
   - `GatheringStaff` → `Members`
   - `GatheringScheduledActivities` → `GatheringActivities`
6. Controller creates new gathering
7. Controller clones selected components based on checkboxes
8. Controller redirects to new gathering using `public_id`
9. Flash message confirms success with counts

## Testing Recommendations

1. **Clone with All Options**:
   - Select all checkboxes
   - Verify all activities, staff, and schedule items are copied
   - Check that scheduled activity times are adjusted correctly

2. **Clone with Partial Options**:
   - Test with only activities
   - Test with only staff
   - Test with only schedule
   - Test with various combinations

3. **Date Offset Validation**:
   - Clone a multi-day event with different start dates
   - Verify scheduled activities maintain correct time relationships
   - Test with single-day events
   - Test with events spanning multiple days

4. **Edge Cases**:
   - Clone gathering with no staff
   - Clone gathering with no schedule
   - Clone gathering with no activities
   - Clone gathering where scheduled activity spans midnight

5. **Data Integrity**:
   - Verify steward status is preserved
   - Verify `show_on_public_page` is preserved
   - Verify all contact information is copied
   - Verify custom descriptions are preserved

## Security Considerations

- Authorization check happens before cloning
- All staff data is preserved including PII (appropriate since user has access to original)
- Staff `show_on_public_page` settings are preserved as-is
- Steward status validation will occur on save (via `beforeSave` hook)

## User Experience

- All clone options default to checked (opt-out rather than opt-in)
- Clear counts shown for each option
- Help text explains what each option does
- Original gathering details provide context
- Success message confirms what was cloned

## Files Modified

1. `/app/src/Controller/GatheringsController.php`
   - Enhanced `clone()` method

2. `/app/templates/element/gatherings/cloneModal.php`
   - Added clone_staff checkbox
   - Added clone_schedule checkbox
   - Enhanced with counts and help text
   - Updated original gathering details

## Dependencies

- Requires `GatheringStaff` table and entity
- Requires `GatheringScheduledActivities` table and entity
- Requires `PublicIdBehavior` for redirects
- Uses `Cake\I18n\Date` for date handling
- Uses `Cake\I18n\DateTime` for datetime field handling

## Future Enhancements

Potential improvements for consideration:

1. **Preview What Will Be Cloned**:
   - Show expandable list of staff names
   - Show expandable list of scheduled activities with times

2. **Selective Cloning**:
   - Allow user to select specific staff members to clone
   - Allow user to select specific scheduled activities to clone

3. **Date Adjustment Preview**:
   - Show before/after times for scheduled activities
   - Highlight any potential conflicts (e.g., activities outside gathering dates)

4. **Validation Warnings**:
   - Warn if schedule extends beyond new gathering dates
   - Warn if cloning would exceed practical limits

## Conclusion

The gathering clone enhancement provides a complete, one-click solution for duplicating complex gatherings including their staff assignments and event schedules. The date offset calculation ensures scheduled activities maintain their temporal relationships while adapting to new gathering dates.
