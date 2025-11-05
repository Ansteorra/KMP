# Gathering Timezone UI Implementation

## Summary

Added timezone support to gathering forms and views, allowing users to see and set event-specific timezones throughout the gathering interface.

## Files Modified

### 1. Gathering Forms - Timezone Selector Added

**Files:**
- `app/templates/Gatherings/add.php`
- `app/templates/Gatherings/edit.php`

**Changes:**
- Added timezone selector dropdown after location field
- Uses `$this->Timezone->getTimezoneOptions()` for timezone list
- Empty option shows "(Use User Timezone: [timezone])" for clarity
- Help text explains timezone purpose
- Positioned logically after location field (since timezone relates to location)

**UI Location:** Between Location and Description fields

```php
<?= $this->Form->control('timezone', [
    'type' => 'select',
    'options' => $this->Timezone->getTimezoneOptions(),
    'empty' => sprintf('(Use User Timezone: %s)', $this->Timezone->getUserTimezone()),
    'class' => 'form-select',
    'label' => 'Event Timezone'
]) ?>
```

### 2. Gathering View - Timezone Display Added

**File:** `app/templates/Gatherings/view.php`

**Changes:**
- Updated Start Date and End Date to use `$this->Timezone->format()` with gathering context
- Added conditional "Event Timezone" row when timezone is set
- Shows timezone identifier (e.g., "America/Chicago")
- Shows timezone abbreviation (e.g., "CDT")
- Includes informational note about all times being in event timezone

**Display Format:**
```
Event Timezone    America/Chicago (CDT)
                 ℹ️ All times for this event are shown in America/Chicago
```

### 3. Quick View Modal - Timezone Info Added

**File:** `app/templates/Gatherings/quick_view.php`

**Changes:**
- Updated date display to use gathering timezone
- Added timezone badge below date information when set
- Shows timezone identifier and abbreviation
- Compact format suitable for modal display

**Display Format:**
```
📅 Date
March 15, 2025
🕐 America/Chicago (CDT)
```

### 4. Public Content - Timezone Display Added

**File:** `app/templates/element/gatherings/public_content.php`

**Changes:**
- Updated date display in hero banner to use gathering timezone
- Added timezone meta item in quick meta section
- Shows alongside date, location, and branch info
- Properly formatted with icon for visual consistency

**Display Format (Hero Banner):**
```
📅 Mar 15 - Mar 17, 2025    🕐 America/Chicago (CDT)    📍 Location    🏛️ Branch
```

### 5. iCalendar Service - Timezone Support

**File:** `app/src/Services/ICalendarService.php`

**Changes:**
- Updated to use gathering timezone for single-day events
- Adds `TZID` parameter to DTSTART/DTEND when timezone is set
- Falls back to UTC if no timezone specified
- Added timezone information to event description
- Properly formats timezone for RFC 5545 compliance

**iCalendar Output (with timezone):**
```ics
DTSTART;TZID=America/Chicago:20250315T090000
DTEND;TZID=America/Chicago:20250315T210000
DESCRIPTION:...Timezone: America/Chicago...
```

### 6. View Helper Enhancement

**File:** `app/src/View/Helper/TimezoneHelper.php`

**Changes:**
- Updated `getAbbreviation()` method signature
- Now accepts optional `$timezone` parameter
- Allows getting abbreviation for specific timezone without member context
- Maintains backward compatibility with existing usage

**Method Signature:**
```php
public function getAbbreviation(?DateTime $datetime = null, ?string $timezone = null, $member = null): string
```

## User Experience Flow

### Creating/Editing a Gathering

1. User fills in gathering details (name, dates, location)
2. User selects timezone from dropdown (optional)
   - Common US timezones listed first
   - Defaults to empty (uses user's timezone)
   - Shows current user timezone in empty option label
3. User saves gathering

### Viewing a Gathering

**Authenticated View:**
- Event dates shown in gathering timezone (if set)
- Timezone information prominently displayed in details table
- Clear indicator that event times use this timezone

**Quick View Modal (Calendar):**
- Compact timezone badge with abbreviation
- Date formatted in event timezone
- Visually consistent with other metadata

**Public Landing Page:**
- Timezone shown in hero banner metadata
- Integrated with date, location, branch information
- Clear visual hierarchy

**iCalendar Download:**
- Properly formatted timezone in .ics file
- Calendar apps respect event timezone
- Timezone noted in description

## Benefits

1. **Location Accuracy**: Events display in their actual location timezone
2. **User Clarity**: Users know exactly what timezone times are in
3. **Cross-Timezone Events**: Traveling members see event in correct local time
4. **Calendar Integration**: Downloaded events maintain proper timezone
5. **Flexibility**: Optional field allows fallback to user preference

## Testing Checklist

- [x] Timezone selector appears in add form
- [x] Timezone selector appears in edit form
- [x] Timezone list includes common US timezones
- [x] Empty option shows current user timezone
- [ ] Test creating gathering with timezone
- [ ] Test creating gathering without timezone
- [ ] Verify timezone displays in view page when set
- [ ] Verify timezone doesn't show in view when not set
- [ ] Check quick view shows timezone badge
- [ ] Check public landing shows timezone
- [ ] Download iCalendar and verify timezone in file
- [ ] Import .ics into calendar app and verify times
- [ ] Test with different user timezones

## Technical Notes

### Display Priority in Templates

When displaying gathering dates/times, the 5th parameter passes the gathering entity:

```php
// This uses gathering timezone (if set), then user timezone, then app default
$this->Timezone->format($gathering->start_date, 'F j, Y', false, null, $gathering)
```

### Database Storage

All dates remain stored as **DATE** type in UTC in the database. The timezone field only affects display and iCalendar generation, not storage.

### Backward Compatibility

- All changes are fully backward compatible
- Gatherings without timezone continue to work (use user timezone)
- Existing templates work without modification
- Optional parameter usage maintains existing behavior

## Future Enhancements

Potential improvements for future consideration:

1. **Auto-detect timezone from location**: When user selects location via Google Maps, auto-populate timezone
2. **Timezone in schedule**: Apply gathering timezone to scheduled activities
3. **Multi-timezone display**: Show times in both event and user timezone side-by-side
4. **Timezone warnings**: Alert when event timezone differs significantly from user's
5. **Historical timezone**: Handle timezone rule changes for past events

## Documentation References

- Main documentation: `/docs/10.3-timezone-handling.md`
- Gathering timezone section: See "Gathering Timezone (Event Location)"
- Quick reference: `/TIMEZONE_README.md`
- Enhancement overview: `/GATHERING_TIMEZONE_ENHANCEMENT.md`
