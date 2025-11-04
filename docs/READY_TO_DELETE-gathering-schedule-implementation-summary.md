# Gathering Schedule Feature - Implementation Summary

## Overview
Implemented a comprehensive scheduling system for gatherings that allows hosts to create detailed event schedules with specific start/end times, custom titles, descriptions, and pre-registration flags.

## Features Implemented

### 1. Database Schema

#### New Table: `gathering_scheduled_activities`
- **Purpose**: Store scheduled activities for gatherings
- **Key Fields**:
  - `gathering_id` - FK to gatherings
  - `gathering_activity_id` - FK to gathering_activities (nullable for "other" activities)
  - `start_datetime` - When activity begins
  - `end_datetime` - When activity ends
  - `display_title` - Custom title for display
  - `description` - Custom description
  - `pre_register` - Whether pre-registration is required
  - `is_other` - Whether this is an "other" activity (not from activity list)
  - Standard audit fields (created, modified, created_by, modified_by)

### 2. Model Layer

#### New Files
1. **GatheringScheduledActivitiesTable.php**
   - Location: `/app/src/Model/Table/GatheringScheduledActivitiesTable.php`
   - Manages scheduled activities
   - Validates time ranges (end must be after start)
   - Validates activity selection (required unless is_other)
   - Custom finders: `findOrdered()`, `findByGathering()`

2. **GatheringScheduledActivity.php**
   - Location: `/app/src/Model/Entity/GatheringScheduledActivity.php`
   - Entity for scheduled activities
   - Virtual fields: `date_range`, `activity_name`, `duration_hours`

#### Updated Files
1. **GatheringsTable.php**
   - Added `hasMany` association to `GatheringScheduledActivities`
   - Ordered by start_datetime ascending

2. **Gathering.php**
   - Added `gathering_scheduled_activities` property
   - Protected from mass assignment (managed via controller)

### 3. Controller Actions

Added to `GatheringsController`:

1. **addScheduledActivity($id)**
   - POST endpoint for AJAX modal
   - Creates new scheduled activity
   - Returns JSON response
   - Authorization: requires `edit` permission on gathering

2. **editScheduledActivity($gatheringId, $id)**
   - POST/PUT/PATCH endpoint for AJAX modal
   - Updates existing scheduled activity
   - Validates activity belongs to gathering
   - Returns JSON response
   - Authorization: requires `edit` permission on gathering

3. **deleteScheduledActivity($gatheringId, $id)**
   - POST/DELETE endpoint
   - Deletes scheduled activity
   - Redirects to gathering view
   - Authorization: requires `edit` permission on gathering

4. **view($id)** - Updated
   - Now contains `GatheringScheduledActivities` with related data

### 4. View Layer

#### New Files
1. **scheduleTab.php**
   - Location: `/app/templates/element/gatherings/scheduleTab.php`
   - Displays schedule in tabular format
   - Shows: time, activity type, title, description, pre-registration
   - Includes add/edit/delete buttons for authorized users
   - Uses Stimulus controller for interactivity
   - Tab order: 4 (between Description and Activities)

2. **addScheduleModal.php**
   - Location: `/app/templates/element/gatherings/addScheduleModal.php`
   - Modal form for adding scheduled activities
   - Fields: start/end datetime, is_other checkbox, activity select, title, description, pre-register
   - AJAX submission

3. **editScheduleModal.php**
   - Location: `/app/templates/element/gatherings/editScheduleModal.php`
   - Modal form for editing scheduled activities
   - Same fields as add modal
   - AJAX submission

#### Updated Files
1. **view.php**
   - Added schedule tab button (order 4)
   - Included schedule tab element
   - Added schedule modals to modals block

### 5. JavaScript (Stimulus)

**gathering-schedule-controller.js**
- Location: `/app/assets/js/controllers/gathering-schedule-controller.js`
- Manages schedule UI interactions
- Handles modal opening/closing
- Manages form state (is_other checkbox disables activity select)
- AJAX form submission
- Flash message display
- Auto-reloads page on success

### 6. Migration

**20251103000000_CreateGatheringScheduledActivities.php**
- Creates table with all fields and indexes
- Foreign keys with appropriate cascade behavior:
  - gathering_id: CASCADE delete
  - gathering_activity_id: SET_NULL delete
  - created_by/modified_by: SET_NULL delete
- Indexes for performance on gathering_id, gathering_activity_id, start/end datetime

### 7. Authorization

- Uses existing `GatheringPolicy` infrastructure
- Schedule management requires `edit` permission on the gathering
- Same authorization as other gathering management features
- Inherits from `BasePolicy` using Roles → Permissions → Policies pattern

## Usage Examples

### Example 1: Armored Combat Event
```
Saturday, Nov 3 - 1:00 PM to 3:00 PM
Activity Type: Armored Combat Activity
Title: Baronial Armored Championship
Description: Round robin tourney with bring your best
Pre-Registration: Required
```

### Example 2: Other Activity
```
Saturday, Nov 3 - 3:00 PM to 4:00 PM
Activity Type: Other
Title: Peerage Circle
Description: Private meeting under the pavilion
Pre-Registration: Not Required
```

## Tab Ordering

The schedule tab is positioned at order 4, fitting into the overall tab structure:
- Order 1: Description
- **Order 4: Schedule** (NEW)
- Order 5: Activities
- Order 6: Location
- Order 7: Attendance
- Order 10+: Plugin tabs (Waivers, etc.)

## Key Design Decisions

1. **Optional Activity Reference**: Scheduled activities can reference a gathering activity OR be marked as "other" for flexibility

2. **AJAX Modals**: Add/Edit use AJAX to avoid page reload, providing better UX

3. **Authorization**: Leverages existing gathering edit permissions - users who can edit a gathering can manage its schedule

4. **Validation**: Strong validation ensures end time is after start time, and activity selection is appropriate based on is_other flag

5. **Display**: Shows duration in hours, formatted dates/times, and clear badges for pre-registration status

## Files Created

1. `/app/config/Migrations/20251103000000_CreateGatheringScheduledActivities.php`
2. `/app/src/Model/Table/GatheringScheduledActivitiesTable.php`
3. `/app/src/Model/Entity/GatheringScheduledActivity.php`
4. `/app/assets/js/controllers/gathering-schedule-controller.js`
5. `/app/templates/element/gatherings/scheduleTab.php`
6. `/app/templates/element/gatherings/addScheduleModal.php`
7. `/app/templates/element/gatherings/editScheduleModal.php`

## Files Modified

1. `/app/src/Model/Table/GatheringsTable.php` - Added association
2. `/app/src/Model/Entity/Gathering.php` - Added property
3. `/app/src/Controller/GatheringsController.php` - Added actions, updated view()
4. `/app/templates/Gatherings/view.php` - Added tab and modals

## Database Changes

```sql
-- Migration ran successfully
CREATE TABLE gathering_scheduled_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gathering_id INT NOT NULL,
    gathering_activity_id INT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    display_title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    pre_register BOOLEAN NOT NULL DEFAULT FALSE,
    is_other BOOLEAN NOT NULL DEFAULT FALSE,
    created DATETIME NOT NULL,
    modified DATETIME NULL,
    created_by INT NULL,
    modified_by INT NULL,
    -- Indexes and foreign keys as defined in migration
);
```

## Testing Recommendations

1. **Create Schedule Entry**: Test adding a scheduled activity with a gathering activity reference
2. **Create "Other" Entry**: Test adding an "other" activity without a gathering activity reference
3. **Validation**: Test that end time must be after start time
4. **Validation**: Test that activity is required when not "other"
5. **Edit**: Test editing scheduled activities
6. **Delete**: Test deleting scheduled activities
7. **Permissions**: Test that only users with edit permission can manage schedules
8. **Display**: Verify duration calculations and date formatting
9. **Cross-day**: Test activities that span midnight or multiple days

## Future Enhancements

Potential future improvements:
- Public display of schedule for attendees
- iCal/calendar export
- Conflict detection (overlapping activities)
- Capacity limits per scheduled activity
- Actual pre-registration system with attendee tracking
- Recurring scheduled activities
- Drag-and-drop schedule builder
