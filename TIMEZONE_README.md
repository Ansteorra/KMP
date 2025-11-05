# Timezone Implementation Summary

## Quick Start

The KMP application now supports timezone-aware date/time display and input. All dates/times are stored in UTC and converted to user's timezone for display.

## Files Created/Modified

### New Files
1. **`app/config/Migrations/20251105000000_AddTimezoneToMembers.php`** - Migration to add timezone column to members table
2. **`app/config/Migrations/20251105000001_AddTimezoneToGatherings.php`** - Migration to add timezone column to gatherings table
3. **`app/src/KMP/TimezoneHelper.php`** - Core PHP timezone conversion helper class
4. **`app/src/View/Helper/TimezoneHelper.php`** - View helper for templates
5. **`app/assets/js/timezone-utils.js`** - JavaScript timezone utilities
6. **`app/assets/js/controllers/timezone-input-controller.js`** - Stimulus controller for automatic form handling
7. **`docs/10.3-timezone-handling.md`** - Comprehensive documentation
8. **`app/templates/element/timezone_examples.php`** - Usage examples element

### Modified Files
1. **`app/src/Model/Entity/Member.php`** - Added timezone to accessible fields
2. **`app/src/Model/Entity/Gathering.php`** - Added timezone to accessible fields
3. **`app/src/Model/Table/MembersTable.php`** - Added timezone validation
4. **`app/src/Model/Table/GatheringsTable.php`** - Added timezone validation
5. **`app/src/View/AppView.php`** - Registered TimezoneHelper
6. **`app/assets/js/index.js`** - Imported timezone-utils.js and timezone-input-controller
7. **`app/src/Application.php`** - Added KMP.DefaultTimezone initialization in bootstrap

## Setup Instructions

### 1. Run Database Migration
```bash
cd /workspaces/KMP/app
bin/cake migrations migrate
```

The default timezone (`KMP.DefaultTimezone = America/Chicago`) will be automatically initialized on first application run via `Application.php` bootstrap.

### 2. Compile Assets
```bash
cd /workspaces/KMP/app
npm run dev    # or npm run prod for production
```

### 3. (Optional) Set Application Default Timezone
Navigate to AppSettings and set `KMP.DefaultTimezone` to your preferred timezone (default is `America/Chicago`).

## Usage Examples

### In Templates (Most Common)
```php
<!-- Display datetime in user's timezone -->
<?= $this->Timezone->format($gathering->start_date) ?>

<!-- Display datetime in gathering's timezone (event location) -->
<?= $this->Timezone->format($gathering->start_date, null, false, null, $gathering) ?>

<!-- For datetime-local inputs (gathering forms) -->
<?= $this->Form->control('start_date', [
    'type' => 'datetime-local',
    'value' => $this->Timezone->forInput($gathering->start_date, null, null, $gathering)
]) ?>

<!-- Show date range with gathering timezone -->
<?= $this->Timezone->smartRange($gathering->start_date, $gathering->end_date) ?>

<!-- Add timezone notice -->
<?= $this->Timezone->notice() ?>

<!-- Timezone selector for gatherings -->
<?= $this->Form->control('timezone', [
    'type' => 'select',
    'options' => $this->Timezone->getTimezoneOptions(),
    'empty' => '(Use user timezone)',
    'label' => 'Event Timezone'
]) ?>
```

### In Controllers
```php
use App\KMP\TimezoneHelper;

// Converting user input to UTC before saving (with gathering timezone context)
$data = $this->request->getData();
$timezone = TimezoneHelper::getGatheringTimezone($gathering, $this->Authentication->getIdentity());
$data['start_date'] = TimezoneHelper::toUtc($data['start_date'], $timezone);

// Or for general use (user timezone)
$data['start_date'] = TimezoneHelper::toUtc(
    $data['start_date'],
    TimezoneHelper::getUserTimezone($this->Authentication->getIdentity())
);
```

### In JavaScript
```javascript
// Format UTC datetime for display
const displayed = KMP_Timezone.formatDateTime("2025-03-15T14:30:00Z", "America/Chicago");

// Convert for datetime-local input
const inputValue = KMP_Timezone.toLocalInput("2025-03-15T14:30:00Z");

// Convert local input to UTC for submission
const utcValue = KMP_Timezone.toUTC("2025-03-15T09:30");
```

## Timezone Priority

When displaying dates/times:
1. **Gathering's timezone** (from `gatherings.timezone` field) - when viewing event-specific dates
2. **User's timezone** (from `members.timezone` field)
3. **App default timezone** (from `KMP.DefaultTimezone` AppSetting)
4. **UTC** (fallback)

**Note:** Gathering timezone is used only when explicitly provided (e.g., when viewing a gathering's details or schedule). This ensures event times are displayed consistently in the event's location timezone.

## Key Principles

✅ **DO:**
- Store all dates/times in UTC in the database
- Use `$this->Timezone->format()` for all datetime display in templates
- Convert user input to UTC before saving with `TimezoneHelper::toUtc()`
- Pass current user to timezone helpers for personalized display

❌ **DON'T:**
- Store local times in the database
- Display UTC times directly to users
- Forget to convert user input back to UTC before saving
- Hard-code timezone assumptions

## Testing Checklist

- [ ] Migration runs successfully
- [ ] Seed creates AppSetting
- [ ] User can set timezone in profile
- [ ] Datetimes display in user's timezone
- [ ] Form inputs work correctly
- [ ] Saved data is in UTC in database
- [ ] JavaScript utilities work on forms
- [ ] Timezone notice appears where appropriate

## Documentation

See **`docs/10.3-timezone-handling.md`** for complete documentation including:
- Detailed usage examples
- Implementation checklist
- Troubleshooting guide
- Best practices
- Testing strategies

## Support

For questions or issues with timezone handling:
1. Check the comprehensive docs at `docs/10.3-timezone-handling.md`
2. Review the code comments in `TimezoneHelper.php`
3. Test with different timezones to verify behavior
