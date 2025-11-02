# Template Gathering Activities Implementation Summary

## Overview

This implementation adds support for template gathering activities to the KMP system. Gathering types can now define a set of activities that will be automatically added to gatherings of that type. Some activities can be marked as "not removable" to enforce their presence in gatherings.

## Implementation Date

November 2, 2025

## Features Implemented

### 1. Database Schema

#### New Table: `gathering_type_gathering_activities`
- **Purpose**: Join table linking gathering types to template activities
- **Key Fields**:
  - `gathering_type_id` - FK to `gathering_types`
  - `gathering_activity_id` - FK to `gathering_activities`
  - `not_removable` - Boolean flag to prevent activity removal
  - `sort_order` - Display order of activities
  - Standard audit fields (created, modified, created_by, modified_by)

#### Updated Table: `gatherings_gathering_activities`
- **New Field**: `not_removable` - Boolean flag inherited from template when activity is added

### 2. Model Layer

#### New Files
1. **GatheringTypeGatheringActivitiesTable.php**
   - Location: `/app/src/Model/Table/GatheringTypeGatheringActivitiesTable.php`
   - Manages template activity associations
   - Validates unique activity assignments per gathering type

2. **GatheringTypeGatheringActivity.php**
   - Location: `/app/src/Model/Entity/GatheringTypeGatheringActivity.php`
   - Entity for template activity join data

#### Updated Files
1. **GatheringTypesTable.php**
   - Added `belongsToMany` association to `GatheringActivities`
   - Uses `GatheringTypeGatheringActivities` as through table

2. **GatheringActivitiesTable.php**
   - Added `belongsToMany` association to `GatheringTypes`
   - Enables reverse lookup of which types use an activity

3. **GatheringsTable.php**
   - Added `afterSave()` callback
   - Added `syncTemplateActivities()` method
   - Automatically syncs template activities when:
     - A new gathering is created
     - A gathering's type is changed
   - Preserves existing activities while adding missing ones
   - Sets `not_removable` flag based on template

4. **GatheringsGatheringActivitiesTable.php**
   - Added validation for `not_removable` field

5. **GatheringsGatheringActivity.php**
   - Added `not_removable` property and accessibility

### 3. Controller Layer

#### Updated: GatheringTypesController.php
- **view() method**: Now includes `GatheringActivities` with join data
- **add() method**:
  - Loads available activities for selection
  - Handles associated data for template activities
- **edit() method**:
  - Contains existing template activities
  - Handles updating template activities and flags

### 4. View Layer

#### Updated Templates

**GatheringTypes/view.php**:
- Dedicated "Template Activities" tab with Add/Remove (modal) and badges.
- Note: Add/Edit forms no longer manage template activities.

**GatheringTypes/view.php**:
- Added "Template Activities" tab (now first tab)
- Displays configured template activities
- Shows "Not Removable" badge for locked activities
- Shows calendar color badge

**Gatherings/view.php**:
- Displays "Required" badge on non-removable activities
- Disables remove button for non-removable activities
- Shows lock icon instead of remove button
- Provides helpful tooltip text

### 5. Migrations

1. **20251102000001_CreateGatheringTypeGatheringActivities.php**
   - Creates the template activities join table
   - Adds appropriate indexes and foreign keys

2. **20251102000002_AddNotRemovableToGatheringsGatheringActivities.php**
   - Adds `not_removable` column to gathering activities
   - Adds index for performance

### 6. Fixtures

**GatheringTypeGatheringActivitiesFixture.php**
- Sample data for testing
- Tournament type with required activities
- Practice type with optional activities

## User Workflow

### Creating/Editing a Gathering Type

1. Navigate to Gathering Types → Add/Edit
2. Fill in basic information (name, description, color, clonable)
3. In "Template Activities" section:
   - Check activities to include in template
   - For each selected activity, optionally check "Not Removable"
   - Activities maintain their display order
4. Save the gathering type

### Creating a Gathering

1. When a gathering is created with a specific type:
   - All template activities are automatically added
   - The `not_removable` flag is set based on the template
   - Activities appear in the gathering's activity list

### Editing a Gathering's Type

1. When a gathering's type is changed:
   - Missing template activities are added
   - Existing activities are preserved
   - `not_removable` flags are updated if template requires it
   - No activities are removed automatically

### Managing Gathering Activities

1. In the gathering view:
   - Required activities show a "Required" badge
   - Remove button is replaced with a locked icon
   - Tooltip explains why activity cannot be removed
   - Optional activities can be removed normally

## Technical Details

### Activity Syncing Logic

The `syncTemplateActivities()` method in `GatheringsTable`:

1. **Fetches** template activities for the gathering type
2. **Gets** existing activities for the gathering
3. **Calculates** max sort order for new activities
4. **Iterates** through template activities:
   - If activity doesn't exist: adds it with proper flags
   - If activity exists: updates `not_removable` if template requires it
5. **Preserves** all existing activities (never removes)

### Data Flow

```
GatheringType (with template activities)
    ↓
Gathering created/type changed
    ↓
afterSave() callback fires
    ↓
syncTemplateActivities() runs
    ↓
GatheringsGatheringActivities updated
    ↓
UI reflects not_removable status
```

## Files Created

1. `/app/config/Migrations/20251102000001_CreateGatheringTypeGatheringActivities.php`
2. `/app/config/Migrations/20251102000002_AddNotRemovableToGatheringsGatheringActivities.php`
3. `/app/src/Model/Table/GatheringTypeGatheringActivitiesTable.php`
4. `/app/src/Model/Entity/GatheringTypeGatheringActivity.php`
5. `/app/tests/Fixture/GatheringTypeGatheringActivitiesFixture.php`

## Files Modified

1. `/app/src/Model/Table/GatheringTypesTable.php`
2. `/app/src/Model/Table/GatheringActivitiesTable.php`
3. `/app/src/Model/Table/GatheringsTable.php`
4. `/app/src/Model/Table/GatheringsGatheringActivitiesTable.php`
5. `/app/src/Model/Entity/GatheringType.php`
6. `/app/src/Model/Entity/GatheringsGatheringActivity.php`
7. `/app/src/Controller/GatheringTypesController.php`
8. `/app/templates/GatheringTypes/add.php`
9. `/app/templates/GatheringTypes/edit.php`
10. `/app/templates/GatheringTypes/view.php`
11. `/app/templates/Gatherings/view.php`

## Database Schema Changes

### New Table
```sql
CREATE TABLE gathering_type_gathering_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gathering_type_id INT NOT NULL,
    gathering_activity_id INT NOT NULL,
    not_removable BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order INT NOT NULL DEFAULT 0,
    created DATETIME NOT NULL,
    modified DATETIME,
    created_by INT,
    modified_by INT,
    UNIQUE KEY (gathering_type_id, gathering_activity_id),
    FOREIGN KEY (gathering_type_id) REFERENCES gathering_types(id) ON DELETE CASCADE,
    FOREIGN KEY (gathering_activity_id) REFERENCES gathering_activities(id) ON DELETE CASCADE
);
```

### Modified Table
```sql
ALTER TABLE gatherings_gathering_activities
ADD COLUMN not_removable BOOLEAN NOT NULL DEFAULT FALSE AFTER sort_order;
```

## Testing Recommendations

1. **Unit Tests**:
   - Test `syncTemplateActivities()` method
   - Test template activity validation
   - Test not_removable flag inheritance

2. **Integration Tests**:
   - Create gathering with type that has template activities
   - Change gathering type and verify activity sync
   - Attempt to remove non-removable activity (should fail)

3. **UI Tests**:
   - Verify template activities display in gathering type forms
   - Verify JavaScript enable/disable logic
   - Verify locked activities show proper badges and disabled buttons

## Future Enhancements

1. **Bulk Update**: Add ability to update all existing gatherings when template changes
2. **Activity Inheritance**: Show which activities came from template vs. manually added
3. **Template Cloning**: Copy template activities when cloning gathering types
4. **Validation**: Prevent gathering type deletion if it has required activities in use
5. **Reporting**: Analytics on template activity usage across gatherings

## Security Considerations

- Authorization checks exist on gathering type edit operations
- Database constraints prevent orphaned template activities
- Foreign key cascades ensure data integrity
- User context maintained through footprint behavior

## Performance Considerations

- Indexes on foreign keys for efficient joins
- afterSave callback only runs when needed (type changed)
- Lazy loading of template activities in views
- Efficient bulk operations for activity syncing

## Compliance with Best Practices

✅ Follows CakePHP 5.x conventions
✅ Uses Stimulus.js patterns where applicable
✅ Maintains audit trail (created_by, modified_by)
✅ Implements soft deletion compatibility
✅ Includes proper validation and business rules
✅ Uses consistent naming conventions
✅ Includes inline documentation
✅ Follows project directory structure
