# Scheduled Activities Timezone Implementation

## Overview
Made gathering scheduled activities timezone-aware by implementing timezone conversion for datetime inputs and displays.

## Changes Completed ✅

### 1. Controller Updates
**File**: `app/src/Controller/GatheringsController.php`

**addScheduledActivity() method:**
- Added timezone conversion for `start_datetime` and `end_datetime` inputs
- Converts from gathering/user timezone to UTC before saving
- Uses `TimezoneHelper::toUtc()` for conversion
- Gets timezone via `TimezoneHelper::getGatheringTimezone()`

**editScheduledActivity() method:**
- Added timezone conversion for `start_datetime` and `end_datetime` inputs
- Converts from gathering/user timezone to UTC before saving
- Uses same timezone priority as add method

### 2. Template Updates

**File**: `app/templates/element/gatherings/scheduleTab.php`
- Updated display times to use `$this->Timezone->format()` with gathering timezone
- Updated data attributes for edit modal to use `$this->Timezone->forInput()` for timezone conversion
- Times now display in gathering's timezone instead of UTC

**File**: `app/templates/element/gatherings/public_content.php`
- Updated schedule activity times to use `$this->Timezone->format()` with gathering timezone
- Public schedule now shows times in gathering's timezone

**File**: `app/templates/element/gatherings/addScheduleModal.php`
- Added info alert showing which timezone is being used
- Displays: "All times in [timezone]"
- Helps users understand what timezone they're entering

**File**: `app/templates/element/gatherings/editScheduleModal.php`
- Added info alert showing which timezone is being used
- Displays: "All times in [timezone]"
- Consistent with add modal

### 3. Data Flow

```
User Input (datetime-local in browser)
    ↓
Edit Modal: forInput() converts UTC → Gathering TZ for editing
    ↓
Form Submission: Browser sends local datetime string
    ↓
Controller: toUtc() converts Gathering TZ → UTC
    ↓
Database: Stores as UTC DATETIME
    ↓
Display: format() converts UTC → Gathering TZ for display
```

## Timezone Priority

Scheduled activities use the same timezone priority as the parent gathering:
1. Gathering's timezone (if set)
2. User's timezone (if gathering timezone not set)
3. Application default timezone
4. UTC (fallback)

## Technical Details

### Database Schema
The `gathering_scheduled_activities` table already has:
- `start_datetime` DATETIME column (UTC)
- `end_datetime` DATETIME column (UTC, nullable)
- `has_end_time` boolean flag

No migration was needed - only code changes.

### Forms
The forms already use `datetime-local` input type, which is perfect for timezone-aware input.

### Controller Conversion
Both add and edit methods now:
1. Get the gathering object
2. Determine the timezone using `getGatheringTimezone()`
3. Convert `start_datetime` and `end_datetime` from that timezone to UTC
4. Save to database in UTC

### Display Conversion
All display templates now:
1. Use `$this->Timezone->format()` for time display
2. Pass the `$gathering` object as the 5th parameter
3. Times automatically convert from UTC to gathering timezone

### Edit Form Population
The edit button's data attributes use:
1. `$this->Timezone->forInput()` to convert UTC to gathering timezone
2. Format as 'Y-m-d\TH:i' for datetime-local input
3. JavaScript populates the form with these converted values

## User Experience

### Adding Scheduled Activities
1. User sees info: "All times in America/Chicago" (or their gathering timezone)
2. User enters times in that timezone using datetime-local picker
3. Controller converts to UTC before saving
4. Database stores UTC
5. Display shows times in gathering timezone

### Editing Scheduled Activities
1. Edit button clicked
2. Data attributes have times already converted to gathering timezone
3. Form shows times in gathering timezone
4. User edits in gathering timezone
5. Controller converts back to UTC on save

### Viewing Schedules
1. Schedule tab shows times in gathering timezone
2. Public landing page shows times in gathering timezone
3. All times consistently shown in the same timezone

## Files Modified

1. ✅ `/app/src/Controller/GatheringsController.php` (addScheduledActivity, editScheduledActivity methods)
2. ✅ `/app/templates/element/gatherings/scheduleTab.php` (display times, edit button data attributes)
3. ✅ `/app/templates/element/gatherings/public_content.php` (schedule display times)
4. ✅ `/app/templates/element/gatherings/addScheduleModal.php` (timezone info alert)
5. ✅ `/app/templates/element/gatherings/editScheduleModal.php` (timezone info alert)

## Testing Checklist

- [ ] Add new scheduled activity with specific time
- [ ] Verify time saves correctly in database (as UTC)
- [ ] View scheduled activity - verify time displays in gathering timezone
- [ ] Edit scheduled activity - verify form shows time in gathering timezone
- [ ] Save edited activity - verify time converts to UTC correctly
- [ ] View in schedule tab - verify times display in gathering timezone
- [ ] View in public landing page - verify times display in gathering timezone
- [ ] Test with gathering that has explicit timezone set
- [ ] Test with gathering that has no timezone (should use user timezone)
- [ ] Test with different user timezones
- [ ] Verify multi-day events with times work correctly
- [ ] Verify single-day events with times work correctly

## Integration with Parent Gathering

Scheduled activities now respect the parent gathering's timezone:
- When gathering has `timezone` field set, scheduled activities use that
- When gathering has no timezone, falls back to user timezone
- Consistent timezone display across all gathering views
- Users always work in the gathering's timezone context

## Notes

- No database migration needed - existing DATETIME columns work perfectly
- Forms already had `datetime-local` inputs - only needed conversion logic
- All timezone conversion happens server-side for security and consistency
- JavaScript only handles form population, not conversion
- Duration calculation (duration_hours) works correctly with UTC values
