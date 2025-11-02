# Template Gathering Activities - Quick Reference

## Overview
Gathering types can define template activities that are automatically added to gatherings. Activities can be marked as "not removable" to enforce their presence.

## Key Concepts

### Template Activity
An activity associated with a gathering type that will automatically be added to new gatherings of that type.

### Not Removable Flag
A boolean flag that prevents an activity from being removed from a gathering once added via template.

## Database Tables

### `gathering_type_gathering_activities`
Join table for template activities:
- `gathering_type_id` - Which type
- `gathering_activity_id` - Which activity
- `not_removable` - Can it be removed?
- `sort_order` - Display order

### `gatherings_gathering_activities` (modified)
Added field:
- `not_removable` - Inherited from template

## Main Methods

### `GatheringsTable::syncTemplateActivities($gathering)`
**Location**: `/app/src/Model/Table/GatheringsTable.php`

**Purpose**: Syncs template activities from gathering type to gathering

**When Called**:
- After creating a new gathering
- After changing a gathering's type

**Behavior**:
- Adds missing template activities
- Sets `not_removable` flag from template
- Preserves existing activities
- Never removes activities automatically

## User Interface

### Gathering Type Forms
**Templates**: `GatheringTypes/add.php`, `GatheringTypes/edit.php`

**Features**:
- Checkbox list of all available activities
- "Not Removable" checkbox per activity (only enabled when activity is checked)
- JavaScript to manage checkbox states

### Gathering Type View
**Template**: `GatheringTypes/view.php`

**Features**:
- "Template Activities" tab (first tab)
- Table showing configured activities
- Badge indicating "Cannot be removed" status

### Gathering View
**Template**: `Gatherings/view.php`

**Features**:
- "Required" badge on non-removable activities
- Lock icon instead of remove button
- Tooltip explaining why activity can't be removed

## Common Tasks

### Add Template Activities to Gathering Type
1. Edit gathering type
2. Scroll to "Template Activities"
3. Check desired activities
4. Check "Not Removable" for required activities
5. Save

### Create Gathering with Template Activities
1. Create gathering
2. Select gathering type
3. Save
4. Template activities are automatically added

### Change Gathering Type
1. Edit gathering
2. Change gathering type
3. Save
4. New template activities are automatically added

### Remove Activity from Gathering
- **If not removable**: Button is disabled/hidden, shows lock icon
- **If removable**: Click remove button as normal

## Data Flow

```
User creates/edits gathering type
    ↓
Saves with template activities
    ↓
Template activities stored in gathering_type_gathering_activities
    
User creates gathering with that type
    ↓
afterSave() callback triggers
    ↓
syncTemplateActivities() runs
    ↓
Activities added to gatherings_gathering_activities
    ↓
not_removable flag set based on template
```

## Important Notes

1. **Non-Destructive**: Syncing never removes existing activities
2. **Additive**: Only adds missing activities from template
3. **Flag Updates**: If template marks activity as not_removable, existing gatherings are updated
4. **No Auto-Remove**: Changing gathering type doesn't remove activities from old type
5. **Manual Override**: Users must manually remove unwanted activities (if removable)

## Validation Rules

- Unique combination of gathering_type_id + gathering_activity_id
- not_removable must be boolean
- Foreign keys must exist
- sort_order must be integer

## Controller Methods

### `GatheringTypesController::add()`
- Loads `$gatheringActivities` list for selection
- Associates template activities via `gathering_activities._ids` and `_joinData`

### `GatheringTypesController::edit($id)`
- Contains existing template activities
- Loads available activities
- Handles updates to template configuration

### `GatheringTypesController::view($id)`
- Contains `GatheringActivities` with join data sorted by sort_order

## Model Associations

### `GatheringTypesTable`
```php
$this->belongsToMany('GatheringActivities', [
    'through' => 'GatheringTypeGatheringActivities',
    'sort' => ['GatheringTypeGatheringActivities.sort_order' => 'ASC'],
]);
```

### `GatheringActivitiesTable`
```php
$this->belongsToMany('GatheringTypes', [
    'through' => 'GatheringTypeGatheringActivities',
    'sort' => ['GatheringTypeGatheringActivities.sort_order' => 'ASC'],
]);
```

## Form Data Structure

When submitting gathering type form:

```php
[
    'name' => 'Tournament',
    'description' => '...',
    'gathering_activities' => [
        '_ids' => [
            1 => 1,  // Activity ID 1 is selected
            2 => 2,  // Activity ID 2 is selected
        ],
        1 => [
            '_joinData' => [
                'not_removable' => true,
                'sort_order' => 1,
            ]
        ],
        2 => [
            '_joinData' => [
                'not_removable' => false,
                'sort_order' => 2,
            ]
        ],
    ]
]
```

## Testing

### Create Fixture Data
See: `/app/tests/Fixture/GatheringTypeGatheringActivitiesFixture.php`

Example:
```php
[
    'gathering_type_id' => 1,
    'gathering_activity_id' => 1,
    'not_removable' => true,
    'sort_order' => 1,
]
```

### Test Scenarios
1. Create gathering with template → verify activities added
2. Change gathering type → verify new activities added
3. Try removing non-removable activity → verify UI prevents it
4. Edit template → verify doesn't affect existing gatherings immediately

## Troubleshooting

### Template activities not appearing on new gathering
- Check gathering has a `gathering_type_id`
- Verify template activities exist for that type
- Check migration ran successfully
- Look for errors in logs

### Can't remove activity even though it should be removable
- Check `not_removable` flag in `gatherings_gathering_activities`
- Verify gathering type template doesn't mark it as required
- Clear cache if needed

### Activities not syncing when type changes
- Verify `afterSave()` callback is firing
- Check that `gathering_type_id` actually changed (isDirty)
- Look for PHP errors in logs
- Ensure gathering has been saved (has ID)

## Related Documentation
- `/docs/4.6-gatherings-system.md` - Gatherings system architecture
- `/docs/template-gathering-activities-summary.md` - Full implementation details
- CakePHP Association Documentation
- CakePHP Event System Documentation
