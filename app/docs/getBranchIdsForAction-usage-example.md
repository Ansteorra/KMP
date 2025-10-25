# getBranchIdsForAction() Usage Examples

## Overview

The `getBranchIdsForAction()` method in the Member entity helps you determine which branches a user has permission to perform a specific action against. This is particularly useful for populating dropdowns, autocompletes, or filtering queries based on user permissions.

## Method Signature

```php
public function getBranchIdsForAction(string $action, mixed $resource): ?array
```

### Parameters

- **$action** (string): The policy action/method name (e.g., 'edit', 'view', 'delete', 'add')
- **$resource** (mixed): Entity instance, table name string, or table instance

### Return Values

- **null**: User has global permission (all branches) or is a super user
- **array**: Specific branch IDs where the user has permission
- **empty array**: User has no permission for this action

## Basic Usage Examples

### Example 1: Populating a Branch Dropdown

```php
// In a controller
public function add()
{
    $member = $this->Members->newEmptyEntity();
    
    // Get branches where current user can add members
    $branchIds = $this->Authentication->getIdentity()->getBranchIdsForAction('add', 'Members');
    
    // Build branch list for dropdown
    if ($branchIds === null) {
        // Global permission - show all branches
        $branches = $this->fetchTable('Branches')
            ->find('list')
            ->orderBy(['name' => 'ASC'])
            ->all();
    } elseif (!empty($branchIds)) {
        // Limited permission - show only authorized branches
        $branches = $this->fetchTable('Branches')
            ->find('list')
            ->where(['id IN' => $branchIds])
            ->orderBy(['name' => 'ASC'])
            ->all();
    } else {
        // No permission
        $branches = [];
        $this->Flash->error('You do not have permission to add members in any branch.');
    }
    
    $this->set(compact('member', 'branches'));
}
```

### Example 2: Filtering a Query by Authorized Branches

```php
// In a controller index action
public function index()
{
    $currentUser = $this->Authentication->getIdentity();
    
    // Get branches where user can view members
    $branchIds = $currentUser->getBranchIdsForAction('view', 'Members');
    
    $query = $this->Members->find();
    
    if ($branchIds === null) {
        // User has global permission - no filtering needed
    } elseif (!empty($branchIds)) {
        // Filter to authorized branches
        $query->where(['Members.branch_id IN' => $branchIds]);
    } else {
        // No permission - return empty result
        $query->where(['1 = 0']);
    }
    
    $members = $this->paginate($query);
    $this->set(compact('members'));
}
```

### Example 3: Dynamic Autocomplete Filtering

```php
// In an AJAX autocomplete endpoint
public function branchAutocomplete()
{
    $this->request->allowMethod(['get']);
    $currentUser = $this->Authentication->getIdentity();
    
    // Get branches user can edit officers in
    $branchIds = $currentUser->getBranchIdsForAction('edit', 'Officers.Officers');
    
    $query = $this->fetchTable('Branches')
        ->find()
        ->select(['id', 'name']);
    
    // Apply search term if provided
    if ($this->request->getQuery('term')) {
        $query->where(['name LIKE' => '%' . $this->request->getQuery('term') . '%']);
    }
    
    // Apply authorization filter
    if ($branchIds === null) {
        // Global access - no filtering
    } elseif (!empty($branchIds)) {
        $query->where(['id IN' => $branchIds]);
    } else {
        // No access
        $query->where(['1 = 0']);
    }
    
    $branches = $query->limit(20)->all();
    
    $this->set('branches', $branches);
    $this->viewBuilder()->setOption('serialize', ['branches']);
}
```

### Example 4: Multi-Action Permission Check

```php
// Check multiple actions at once
public function beforeRender(\Cake\Event\EventInterface $event)
{
    parent::beforeRender($event);
    
    $currentUser = $this->Authentication->getIdentity();
    
    // Get permission scopes for different actions
    $canEditBranches = $currentUser->getBranchIdsForAction('edit', 'Members');
    $canDeleteBranches = $currentUser->getBranchIdsForAction('delete', 'Members');
    $canViewBranches = $currentUser->getBranchIdsForAction('view', 'Members');
    
    $this->set(compact('canEditBranches', 'canDeleteBranches', 'canViewBranches'));
}
```

### Example 5: Using with Entity Instance

```php
// Pass an entity instance instead of table name
public function edit($id = null)
{
    $member = $this->Members->get($id);
    $currentUser = $this->Authentication->getIdentity();
    
    // Get branches where user can edit this type of entity
    $branchIds = $currentUser->getBranchIdsForAction('edit', $member);
    
    // Build branch dropdown for reassignment
    $branches = $this->fetchTable('Branches')
        ->find('list')
        ->where(function ($exp) use ($branchIds) {
            if ($branchIds === null) {
                return $exp; // No filtering
            } elseif (!empty($branchIds)) {
                return $exp->in('id', $branchIds);
            }
            return $exp->eq('1', '0'); // No results
        })
        ->all();
    
    $this->set(compact('member', 'branches'));
}
```

## Advanced Usage

### Example 6: Plugin Entities

```php
// Works with plugin entities
$branchIds = $currentUser->getBranchIdsForAction('edit', 'Officers.Officers');

// Or with entity instance
$officer = $this->fetchTable('Officers.Officers')->newEmptyEntity();
$branchIds = $currentUser->getBranchIdsForAction('edit', $officer);
```

### Example 7: Conditional Form Display

```php
// In a template
<?php
$editableBranches = $currentUser->getBranchIdsForAction('edit', 'Members');

if ($editableBranches === null || !empty($editableBranches)) {
    // Show edit form
    echo $this->Form->create($member);
    
    // Build branch dropdown
    $branchOptions = $this->fetchTable('Branches')
        ->find('list')
        ->where(function ($exp) use ($editableBranches) {
            if ($editableBranches !== null && !empty($editableBranches)) {
                return $exp->in('id', $editableBranches);
            }
            return $exp;
        })
        ->all();
    
    echo $this->Form->control('branch_id', [
        'options' => $branchOptions,
        'label' => 'Branch'
    ]);
} else {
    // Show read-only view
    echo '<p>You do not have permission to edit members.</p>';
}
?>
```

### Example 8: Service Layer Integration

```php
// In a service class
namespace App\Service;

class MemberService
{
    public function getEditableMembersForUser($user)
    {
        $branchIds = $user->getBranchIdsForAction('edit', 'Members');
        
        $membersTable = TableRegistry::getTableLocator()->get('Members');
        $query = $membersTable->find();
        
        if ($branchIds === null) {
            // Global access
            return $query->all();
        } elseif (!empty($branchIds)) {
            // Limited access
            return $query->where(['branch_id IN' => $branchIds])->all();
        }
        
        // No access
        return [];
    }
}
```

## Integration with Stimulus.js Controllers

### Example 9: Dynamic Branch Selection

```javascript
// In a Stimulus controller
import { Controller } from "@hotwired/stimulus"

class BranchSelectorController extends Controller {
    static values = {
        action: String,
        entity: String
    }
    
    async loadBranches() {
        // Server endpoint that uses getBranchIdsForAction internally
        const response = await fetch(
            `/api/branches/authorized?action=${this.actionValue}&entity=${this.entityValue}`
        );
        const data = await response.json();
        
        // Populate select dropdown
        this.updateBranchDropdown(data.branches);
    }
    
    updateBranchDropdown(branches) {
        const select = this.element.querySelector('select[name="branch_id"]');
        select.innerHTML = '';
        
        branches.forEach(branch => {
            const option = document.createElement('option');
            option.value = branch.id;
            option.textContent = branch.name;
            select.appendChild(option);
        });
    }
}
```

## Best Practices

1. **Cache Results**: If checking multiple times in the same request, store the result
   ```php
   $editableBranches = $currentUser->getBranchIdsForAction('edit', 'Members');
   // Use $editableBranches multiple times
   ```

2. **Handle All Cases**: Always handle null, empty array, and non-empty array cases
   ```php
   if ($branchIds === null) {
       // Global access
   } elseif (!empty($branchIds)) {
       // Limited access
   } else {
       // No access
   }
   ```

3. **Combine with Authorization Checks**: Use alongside `can()` for complete security
   ```php
   $branchIds = $user->getBranchIdsForAction('edit', $member);
   if (!empty($branchIds) && $user->can('edit', $member)) {
       // Perform action
   }
   ```

4. **Use Consistent Action Names**: Match your action names to policy method names
   - 'edit' → canEdit()
   - 'view' → canView()
   - 'delete' → canDelete()
   - 'add' → canAdd()

## Troubleshooting

### Empty Array Returned
If you're getting an empty array when you expect results:
- Check that the policy class exists and follows naming conventions
- Verify the user has the appropriate role assignments
- Confirm the permission exists and is linked to a policy method
- Check branch_id assignments in member_roles table

### Null vs Empty Array
- **null**: User has GLOBAL permission or is super user (all branches)
- **empty array []**: User has NO permission for this action

### Policy Class Resolution
The method automatically resolves:
- `'Members'` → `App\Policy\MembersPolicy`
- `'Officers.Officers'` → `Officers\Policy\OfficersPolicy`
- Entity instances → Appropriate policy class based on entity namespace
