# getBranchIdsForAction() Quick Reference

## Quick Syntax

```php
$branchIds = $currentUser->getBranchIdsForAction($action, $resource);
```

## Return Values Cheat Sheet

| Return Value | Meaning | Action |
|--------------|---------|--------|
| `null` | Global permission or super user | Load ALL branches |
| `[2, 11, 14, ...]` | Limited to specific branches | Load only these branches |
| `[]` (empty array) | No permission | Show error or hide UI |

## Common Usage Pattern

```php
$branchIds = $currentUser->getBranchIdsForAction('edit', 'Members');

if ($branchIds === null) {
    // Global access
    $branches = $branchesTable->find('list')->all();
} elseif (!empty($branchIds)) {
    // Limited access
    $branches = $branchesTable->find('list')
        ->where(['id IN' => $branchIds])
        ->all();
} else {
    // No access
    $branches = [];
    $this->Flash->error('Permission denied');
}
```

## Action Names

| Action String | Policy Method | Common Use |
|---------------|---------------|------------|
| `'edit'` | `canEdit()` | Editing existing records |
| `'view'` | `canView()` | Viewing records |
| `'add'` | `canAdd()` | Creating new records |
| `'delete'` | `canDelete()` | Deleting records |
| `'index'` | `canIndex()` | Listing records |

## Resource Formats

```php
// String table name
$branchIds = $user->getBranchIdsForAction('edit', 'Members');

// Entity instance
$member = $membersTable->newEmptyEntity();
$branchIds = $user->getBranchIdsForAction('edit', $member);

// Plugin table
$branchIds = $user->getBranchIdsForAction('edit', 'Officers.Officers');
```

## Copy-Paste Templates

### Controller Action with Branch Dropdown

```php
public function add()
{
    $entity = $this->TableName->newEmptyEntity();
    $user = $this->Authentication->getIdentity();
    
    // Get authorized branches
    $branchIds = $user->getBranchIdsForAction('add', 'TableName');
    
    // Build branch list
    $branches = $this->_getBranchesFromIds($branchIds);
    
    $this->set(compact('entity', 'branches'));
}

private function _getBranchesFromIds($branchIds)
{
    $branchesTable = $this->fetchTable('Branches');
    
    if ($branchIds === null) {
        return $branchesTable->find('list')->orderBy(['name' => 'ASC'])->all();
    }
    
    if (empty($branchIds)) {
        $this->Flash->error('You do not have permission for this action.');
        return [];
    }
    
    return $branchesTable->find('list')
        ->where(['id IN' => $branchIds])
        ->orderBy(['name' => 'ASC'])
        ->all();
}
```

### Query Filtering

```php
public function index()
{
    $user = $this->Authentication->getIdentity();
    $branchIds = $user->getBranchIdsForAction('view', 'TableName');
    
    $query = $this->TableName->find();
    
    // Apply branch filtering
    if ($branchIds === null) {
        // No filtering for global access
    } elseif (!empty($branchIds)) {
        $query->where(['branch_id IN' => $branchIds]);
    } else {
        // No access - empty result
        $query->where(['1 = 0']);
    }
    
    $entities = $this->paginate($query);
    $this->set(compact('entities'));
}
```

### AJAX Autocomplete Endpoint

```php
public function autocomplete()
{
    $this->request->allowMethod(['get']);
    $user = $this->Authentication->getIdentity();
    
    $branchIds = $user->getBranchIdsForAction('edit', 'TableName');
    $query = $this->fetchTable('Branches')->find();
    
    // Apply search
    if ($term = $this->request->getQuery('term')) {
        $query->where(['name LIKE' => "%{$term}%"]);
    }
    
    // Apply authorization
    if ($branchIds === null) {
        // Global - no filter
    } elseif (!empty($branchIds)) {
        $query->where(['id IN' => $branchIds]);
    } else {
        $query->where(['1 = 0']);
    }
    
    $branches = $query->limit(20)->all();
    
    $this->set(compact('branches'));
    $this->viewBuilder()->setOption('serialize', ['branches']);
}
```

### Conditional Template Rendering

```php
// In template or element
<?php
$editBranches = $currentUser->getBranchIdsForAction('edit', 'Members');
if ($editBranches !== null || !empty($editBranches)): ?>
    <?= $this->Form->control('branch_id', [
        'options' => $this->_getBranchOptions($editBranches),
        'label' => 'Branch'
    ]); ?>
<?php else: ?>
    <p class="text-danger">You do not have permission to edit members.</p>
<?php endif; ?>
```

## Troubleshooting

### Problem: Always getting empty array

**Check:**
1. User has appropriate role assignments
2. Role has permissions with policy mappings
3. Permission policies reference correct policy class and method
4. Branch assignments are set in member_roles table

### Problem: Getting null when expecting specific branches

**Check:**
1. User is not a super user
2. Permission scoping_rule is not set to SCOPE_GLOBAL

### Problem: Wrong branches returned

**Check:**
1. branch_id values in member_roles table
2. Permission scoping_rule (BRANCH_ONLY vs BRANCH_AND_CHILDREN)
3. Branch hierarchy if using BRANCH_AND_CHILDREN

## See Also

- Full documentation: `/workspaces/KMP/app/docs/getBranchIdsForAction-usage-example.md`
- Test script: `/workspaces/KMP/app/test_getBranchIdsForAction.php`
- Implementation: `/workspaces/KMP/app/src/Model/Entity/Member.php`
