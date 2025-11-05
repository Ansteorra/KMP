# Gathering Timezone Enhancement

## Summary

Enhanced the KMP timezone system to support **event-specific timezones** for gatherings. This allows events to be displayed in their location's timezone regardless of where users are viewing from, while still supporting individual user timezone preferences for non-event-specific date displays.

## Changes Made

### 1. Database Migration
**File:** `app/config/Migrations/20251105000001_AddTimezoneToGatherings.php`

- Added `timezone` column to `gatherings` table
- VARCHAR(50), nullable
- Stores IANA timezone identifier (e.g., "America/Chicago")
- When null, falls back to user/app timezone

### 2. Entity Updates
**File:** `app/src/Model/Entity/Gathering.php`

- Added `timezone` property to docblock
- Added `timezone` to `$_accessible` array

### 3. Table Validation
**File:** `app/src/Model/Table/GatheringsTable.php`

- Added timezone validation rule
- Validates against PHP `DateTimeZone` class
- Allows empty (uses fallback)
- Max length 50 characters

### 4. Core Helper Enhancement
**File:** `app/src/KMP/TimezoneHelper.php`

Added three new methods:

#### `getGatheringTimezone($gathering, $member, $default)`
Resolves timezone with gathering priority:
1. Gathering's timezone field
2. Member's timezone field
3. Application default
4. UTC fallback

#### `getContextTimezone($gathering, $member, $default)`
Alias for `getGatheringTimezone()` - context-aware timezone resolution

#### Updated `toUserTimezone($datetime, $member, $fallbackTimezone, $gathering)`
- Added optional 4th parameter: `$gathering`
- When gathering is provided, uses gathering timezone instead of user timezone
- Maintains backward compatibility (parameter is optional)

### 5. View Helper Enhancement
**File:** `app/src/View/Helper/TimezoneHelper.php`

Updated all display methods to accept optional `$gathering` parameter:

- `format($datetime, $format, $includeTimezone, $member, $gathering)`
- `date($datetime, $format, $member, $gathering)`
- `time($datetime, $format, $member, $gathering)`
- `forInput($datetime, $format, $member, $gathering)`

When `$gathering` is provided, these methods display times in the event's timezone.

### 6. Documentation Updates
**Files:** 
- `docs/10.3-timezone-handling.md`
- `TIMEZONE_README.md`

- Updated timezone priority to include gathering timezone as highest priority
- Added examples for gathering timezone usage
- Added controller examples for gathering timezone conversion
- Updated component lists to include gathering migration

## Updated Timezone Priority

### Previous Priority (User-Centric)
1. User's Timezone
2. Application Default
3. UTC

### New Priority (Context-Aware)
1. **Gathering's Timezone** (when viewing event-specific dates)
2. User's Timezone
3. Application Default
4. UTC

## Usage Examples

### In Templates - Displaying Event Times

```php
<!-- Display in gathering's timezone -->
<?= $this->Timezone->format($gathering->start_date, 'l, F j, Y g:i A T', true, null, $gathering) ?>
// Output: "Saturday, March 15, 2025 9:00 AM CDT" (event's timezone)

<!-- Schedule activities in gathering's timezone -->
<?php foreach ($gathering->gathering_scheduled_activities as $activity): ?>
    <?= $this->Timezone->format($activity->start_datetime, 'g:i A', false, null, $gathering) ?>
<?php endforeach; ?>
```

### In Forms - Gathering Edit

```php
<!-- Timezone selector for gathering -->
<?= $this->Form->control('timezone', [
    'type' => 'select',
    'options' => $this->Timezone->getTimezoneOptions(),
    'empty' => '(Use user timezone)',
    'label' => 'Event Timezone',
    'help' => 'Set the timezone for this event based on its location'
]) ?>

<!-- Start date in gathering's timezone -->
<?= $this->Form->control('start_date', [
    'type' => 'datetime-local',
    'value' => $this->Timezone->forInput($gathering->start_date, null, null, $gathering)
]) ?>
```

### In Controllers - Converting Input

```php
// Get appropriate timezone (gathering or user)
$timezone = TimezoneHelper::getGatheringTimezone($gathering, $this->Authentication->getIdentity());

// Convert from event timezone to UTC
$data['start_date'] = TimezoneHelper::toUtc($data['start_date'], $timezone);
$data['end_date'] = TimezoneHelper::toUtc($data['end_date'], $timezone);
```

## Backward Compatibility

✅ **Fully backward compatible**
- All new parameters are optional
- Existing code continues to work without changes
- When `$gathering` is not provided, behavior is identical to before
- Null gathering timezone falls back to user timezone

## Benefits

1. **Event Consistency**: Events display in their location's timezone consistently
2. **User Flexibility**: Users can still set their own timezone for non-event views
3. **Location Accuracy**: Multi-timezone events (traveling) can specify exact timezone
4. **Clear Communication**: Event times are unambiguous when gathering timezone is set
5. **Flexible Fallback**: Events without timezone still work (use user/app default)

## Testing Checklist

- [ ] Run migration: `bin/cake migrations migrate`
- [ ] Create gathering with specific timezone (e.g., "America/Los_Angeles")
- [ ] Verify gathering times display in event's timezone
- [ ] Test with user in different timezone (e.g., "America/New_York")
- [ ] Confirm event times stay consistent regardless of user timezone
- [ ] Test gathering without timezone (should use user timezone)
- [ ] Test scheduled activities inherit gathering timezone
- [ ] Test form input conversion with gathering timezone

## Deployment Steps

```bash
# 1. Run migrations to add timezone columns
cd /workspaces/KMP/app
bin/cake migrations migrate

# 2. Assets already compiled (no changes needed)

# 3. No configuration changes needed (uses existing StaticHelpers)
```

## Next Steps for Gathering Templates

Now that gathering timezone support is in place, update gathering templates:

1. **Gatherings/add.php & edit.php** - Add timezone selector
2. **Gatherings/view.php** - Display times in gathering timezone
3. **element/gatherings/scheduleTab.php** - Show activities in gathering timezone
4. **element/gatherings/addScheduleModal.php** - Convert inputs using gathering timezone
5. **element/gatherings/calendar_*.php** - Display events in their respective timezones

See `TIMEZONE_ROLLOUT_CHECKLIST.md` for complete template update list.
