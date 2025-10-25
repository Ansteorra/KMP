# getBranchIdsForAction() - Implementation Summary

## Overview

A new helper function has been added to the `Member` entity that allows you to retrieve all branch IDs where a user has permission to perform a specific policy action. This is essential for populating dropdowns and autocomplete fields with only the branches a user is authorized to work with.

## Implementation Details

### Method Signature

```php
public function getBranchIdsForAction(string $action, mixed $resource): ?array
```

### Location

`/workspaces/KMP/app/src/Model/Entity/Member.php` (lines ~950-1110)

### Parameters

- **$action** (string): The policy action/method name without the 'can' prefix
  - Examples: 'edit', 'view', 'delete', 'add'
  - Automatically converts to policy method format (e.g., 'edit' → 'canEdit')

- **$resource** (mixed): The entity or table to check permissions for
  - Can be a string table name: `'Members'`, `'Branches'`, `'Officers.Officers'`
  - Can be an entity instance: `$membersTable->newEmptyEntity()`
  - Can be a table instance: `$membersTable`

### Return Values

- **`null`**: User has global permission (can access ALL branches) or is a super user
- **`array`**: Array of specific branch IDs where user has permission (e.g., `[2, 11, 14]`)
- **`empty array`**: User has NO permission for this action on any branch

## How It Works

1. **Super User Check**: Returns `null` immediately if user is a super user (bypass all restrictions)

2. **Resource Resolution**: Converts string table names or table instances to entity instances

3. **Policy Class Resolution**: Determines the correct policy class for the entity
   - `'Members'` → `App\Policy\MembersPolicy`
   - `'Officers.Officers'` → `Officers\Policy\OfficersPolicy`
   - Entity instances → Policy based on entity namespace

4. **Policy Method Conversion**: Converts action to policy method name
   - `'edit'` → `'canEdit'`
   - `'view'` → `'canView'`
   - etc.

5. **Permission Lookup**: Queries the user's policies for the specific policy class and method

6. **Scope Evaluation**: Returns appropriate value based on permission scoping rule
   - `Permission::SCOPE_GLOBAL` → Returns `null`
   - `Permission::SCOPE_BRANCH_ONLY` → Returns specific branch IDs
   - `Permission::SCOPE_BRANCH_AND_CHILDREN` → Returns branch IDs with hierarchy

## Helper Methods

### `resolvePolicyClass(mixed $resource): ?string`

Protected helper that resolves the policy class name from a resource:
- Handles table instances
- Handles entity instances
- Handles plugin entities
- Returns fully qualified policy class name

### `getPolicyClassFromTableName(string $tableName): string`

Protected helper that converts table names to policy class names:
- Handles plugin tables (e.g., `'Officers.Officers'`)
- Handles standard app tables (e.g., `'Members'`)

## Integration with Existing KMP Architecture

### Uses Existing Systems

1. **PermissionsLoader**: Leverages existing `getPolicies()` method
2. **Authorization Framework**: Integrates with CakePHP's authorization system
3. **Policy Classes**: Works with all existing policy classes
4. **Scoping Rules**: Respects all permission scoping rules

### Compatible With

- All existing policy classes (BasePolicy and subclasses)
- Plugin entities and policies
- Branch hierarchy system
- Role-based access control (RBAC)
- Warrant-based permissions

## Testing

### Test Script

A comprehensive test script has been created at:
`/workspaces/KMP/app/test_getBranchIdsForAction.php`

The test script demonstrates:
- Testing with super users (returns null)
- Testing with regular users (returns specific branch IDs or empty array)
- Testing with different actions (edit, view, add, delete)
- Testing with different entities (Members, Branches, Officers)
- Testing with both string table names and entity instances
- Practical example of building a branch dropdown

### Test Results

✅ Method successfully resolves policy classes
✅ Method correctly handles super users (returns null)
✅ Method correctly handles users without permissions (returns empty array)
✅ Method correctly integrates with PermissionsLoader
✅ Method works with plugin entities
✅ Method works with entity instances and table names

## Documentation

Comprehensive documentation created at:
`/workspaces/KMP/app/docs/getBranchIdsForAction-usage-example.md`

Documentation includes:
- 9 detailed usage examples
- Integration with controllers
- Integration with Stimulus.js
- Best practices
- Troubleshooting guide
- Common patterns

## Example Usage in Controllers

### Basic Dropdown Population

```php
public function add()
{
    $member = $this->Members->newEmptyEntity();
    $branchIds = $this->Authentication->getIdentity()->getBranchIdsForAction('add', 'Members');
    
    if ($branchIds === null) {
        // Global permission
        $branches = $this->fetchTable('Branches')->find('list')->all();
    } elseif (!empty($branchIds)) {
        // Limited permission
        $branches = $this->fetchTable('Branches')
            ->find('list')
            ->where(['id IN' => $branchIds])
            ->all();
    } else {
        // No permission
        $branches = [];
        $this->Flash->error('You do not have permission to add members.');
    }
    
    $this->set(compact('member', 'branches'));
}
```

### Query Filtering

```php
public function index()
{
    $branchIds = $this->Authentication->getIdentity()->getBranchIdsForAction('view', 'Members');
    
    $query = $this->Members->find();
    
    if ($branchIds === null) {
        // No filtering needed
    } elseif (!empty($branchIds)) {
        $query->where(['Members.branch_id IN' => $branchIds]);
    } else {
        // No access - return empty result
        $query->where(['1 = 0']);
    }
    
    $members = $this->paginate($query);
    $this->set(compact('members'));
}
```

## Benefits

1. **UI Enhancement**: Populate dropdowns with only relevant branches
2. **Better UX**: Users don't see branches they can't access
3. **Security**: Enforces authorization at the UI level
4. **Consistency**: Works seamlessly with existing authorization system
5. **Flexibility**: Works with any entity and any action
6. **Performance**: Leverages existing caching in PermissionsLoader

## Backwards Compatibility

✅ This is a new method - no breaking changes
✅ Does not modify any existing functionality
✅ Follows existing KMP patterns and conventions
✅ Uses existing infrastructure (PermissionsLoader, policies)

## Next Steps

### Recommended Usage Patterns

1. **Update form controllers** to use this method for branch dropdowns
2. **Update autocomplete endpoints** to filter by authorized branches
3. **Add to beforeRender** in AppController for global availability
4. **Use in templates** for conditional UI rendering

### Potential Enhancements

1. Add caching at the method level for repeated calls
2. Add method overloads for commonly used entities
3. Create Stimulus.js controller for dynamic branch filtering
4. Add helper method to BranchesTable for easier integration

## Files Changed

1. `/workspaces/KMP/app/src/Model/Entity/Member.php` - Added new methods
2. `/workspaces/KMP/app/docs/getBranchIdsForAction-usage-example.md` - Usage documentation
3. `/workspaces/KMP/app/test_getBranchIdsForAction.php` - Test script

## Validation

✅ No PHP syntax errors
✅ Test script runs successfully
✅ Compatible with existing authorization system
✅ Follows CakePHP and KMP conventions
✅ Comprehensive documentation provided
