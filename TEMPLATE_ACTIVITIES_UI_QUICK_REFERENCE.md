# Template Activities UI - Quick Reference

## User Guide

### Adding Template Activities to a Gathering Type

1. **Create the gathering type**:
   - Navigate to Gathering Types → Add Gathering Type
   - Fill in Name, Description, Color, and Clonable checkbox
   - Click Submit

2. **Add template activities**:
   - You'll be redirected to the gathering type view page
   - Click on the "Template Activities" tab (already selected)
   - Click the "Add Template Activity" button
   - Select an activity from the dropdown
   - Optionally check "Not Removable" to lock it on gatherings
   - Click "Add Activity"

3. **Remove template activities**:
   - In the Template Activities tab, find the activity to remove
   - Click the trash icon in the Actions column
   - Confirm the removal

### How Template Activities Work

- **Automatic Addition**: When you create a new gathering with a gathering type, all template activities are automatically added to that gathering
- **Not Removable Flag**: If a template activity is marked "Not Removable", it will be locked on the gathering and cannot be removed
- **Existing Gatherings**: Existing gatherings are not backfilled. Template activities are added only when a new gathering is created or when a gathering's type changes.
- **Remove from Type**: Removing a template activity from the type does NOT remove it from existing gatherings
## Developer Guide

### Controller Actions

```php
// Add template activity to gathering type
POST /gathering-types/addActivity/{id}
Parameters:
  - activity_id (required): ID of the activity to add
  - not_removable (optional): Boolean, default false

// Remove template activity from gathering type
POST /gathering-types/removeActivity/{typeId}/{activityId}
Parameters: None (IDs in URL)
```

### Code Examples

#### Adding a Template Activity (via modal form)
```php
<?= $this->Form->create(null, [
    'url' => ['action' => 'addActivity', $gatheringType->id]
]) ?>
    <?= $this->Form->control('activity_id', [
        'type' => 'select',
        'options' => $availableActivities,
        'required' => true
    ]) ?>
    <?= $this->Form->checkbox('not_removable') ?>
    <?= $this->Form->button(__('Add Activity')) ?>
<?= $this->Form->end() ?>
```

#### Removing a Template Activity
```php
<?= $this->Form->postLink(
    '<i class="bi bi-trash-fill"></i>',
    ['action' => 'removeActivity', $gatheringType->id, $activity->id],
    [
        'confirm' => __('Are you sure?'),
        'escape' => false
    ]
) ?>
```

#### Checking Not Removable Status
```php
<?php if ($activity->_joinData && $activity->_joinData->not_removable): ?>
    <span class="badge bg-warning text-dark">
        <i class="bi bi-lock-fill"></i> <?= __('Cannot be removed') ?>
    </span>
<?php else: ?>
    <span class="text-muted"><?= __('Can be removed') ?></span>
<?php endif; ?>
```

### Authorization

Both actions require `edit` permission on the gathering type:

```php
$this->Authorization->authorize($gatheringType, "edit");
```

UI elements check permissions before displaying:

```php
<?php if ($user->checkCan("edit", $gatheringType)) : ?>
    <button>Add Template Activity</button>
<?php endif; ?>
```

### Database Structure

Join table: `gathering_type_gathering_activities`
- `id` (primary key)
- `gathering_type_id` (foreign key)
- `gathering_activity_id` (foreign key)
- `not_removable` (boolean)
- `created` (datetime)
- `modified` (datetime)
- `created_by_id` (foreign key)
- `modified_by_id` (foreign key)

### Tab Ordering

The Template Activities tab uses order value 10 to position it appropriately in the tab list.

## Common Tasks

### Task: Add a non-removable template activity
1. Create/view gathering type
2. Click "Add Template Activity"
3. Select activity from dropdown
4. **Check "Not Removable"**
5. Click "Add Activity"

### Task: Change an activity from removable to non-removable
Currently, you need to:
1. Remove the activity
2. Re-add it with "Not Removable" checked

*Note: A future enhancement could add an "Edit" button to modify the not_removable flag directly*

### Task: See which gatherings have which activities
1. View the gathering type
2. Click the "Gatherings" tab to see all gatherings using this type
3. Click the view button for a specific gathering
4. Click the "Activities" tab to see its activities (template activities will have lock icons if not_removable)

## Troubleshooting

### Activity doesn't appear after adding
- Check that the flash message showed success
- Refresh the page
- Verify the activity ID was valid

### Can't remove an activity
- Verify you have edit permission on the gathering type
- Check that you're clicking the trash icon (not viewing the not_removable badge)

### Template activities not syncing to gatherings
- The sync happens automatically in `GatheringsTable::afterSave()`
- Check that gatherings exist for the type
- Verify the gathering type ID is correctly set on the gathering
- Check the `gathering_type_id` field was changed or gathering was newly created
