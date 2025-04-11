# Branch Management

This document details the Branch Management system in the Kingdom Management Portal (KMP), which handles the hierarchical structure of SCA Kingdom organizational units.

## Overview

In SCA kingdoms, branches represent geographic and organizational divisions, such as kingdoms, principalities, regions, baronies, and local groups. The Branch Management system in KMP provides functionality for creating, organizing, and managing these branches and their relationships.

## Key Concepts

### Branch Types

KMP supports various branch types, which are configurable via application settings:

- Kingdom: The top-level organizational unit
- Principality: A major division within a kingdom
- Region: A geographic division (often administrative)
- Local Group: Local branches such as baronies, shires, cantons, etc.

### Branch Hierarchy

Branches in KMP are organized in a hierarchical structure:

- The Kingdom is the top-level branch
- Principalities, regions, and local groups can be children of the kingdom or other branches
- Each branch (except the kingdom) has a parent branch
- A branch can have multiple child branches

### Branch Information

Each branch contains various information:

- Name and type
- Geographic location
- Contact information
- Web presence (website, social media links)
- Associated officers

## Database Structure

### Branches Table

The `branches` table stores branch information:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the branch |
| `name` | Branch name |
| `parent_id` | ID of the parent branch (null for kingdom) |
| `type` | Branch type (Kingdom, Principality, etc.) |
| `domain` | Website domain |
| `links` | JSON array of web links (social media, etc.) |
| `created` | When the branch was created |
| `modified` | When the branch was last updated |

## Components

### BranchesController

The `BranchesController` handles branch-related operations:

- Listing branches
- Creating new branches
- Editing branch information
- Viewing branch details
- Managing branch hierarchy

### BranchesTable

The `BranchesTable` class manages branch data and relationships:

```php
// Initialization in BranchesTable
public function initialize(array $config): void
{
    parent::initialize($config);
    
    $this->setTable('branches');
    $this->setDisplayField('name');
    $this->setPrimaryKey('id');
    
    // Tree behavior for hierarchical data
    $this->addBehavior('Tree', [
        'parent' => 'parent_id',
    ]);
    
    // Timestamp behavior for created/modified fields
    $this->addBehavior('Timestamp');
    
    // Associations
    $this->hasMany('Members');
    $this->hasMany('ChildBranches', [
        'className' => 'Branches',
        'foreignKey' => 'parent_id',
    ]);
    $this->belongsTo('ParentBranch', [
        'className' => 'Branches',
        'foreignKey' => 'parent_id',
    ]);
    // Additional associations for officers, etc.
}
```

### Branch Entity

The `Branch` entity class represents branch objects:

```php
class Branch extends Entity
{
    protected $_accessible = [
        'name' => true,
        'parent_id' => true,
        'type' => true,
        'domain' => true,
        'links' => true,
        // Other fields
    ];
    
    // Custom getter for formatted links
    protected function _getFormattedLinks()
    {
        if (empty($this->links)) {
            return [];
        }
        
        return json_decode($this->links, true);
    }
}
```

## Branch Hierarchy Management

KMP uses CakePHP's Tree behavior to manage the branch hierarchy:

```php
// Example: Moving a branch to a new parent
public function moveUnder($id, $parentId)
{
    $branch = $this->Branches->get($id);
    $branch->parent_id = $parentId;
    
    if ($this->Branches->save($branch)) {
        // Successfully moved
    }
}

// Example: Getting all child branches
public function getChildren($parentId)
{
    $children = $this->Branches->find('children', [
        'for' => $parentId
    ])->all();
    
    return $children;
}
```

## Branch Links Management

Branch links (for websites, social media, etc.) are stored as JSON and managed through a custom UI component:

```javascript
// Simplified version of the branch links controller
class BranchLinks extends Controller {
    static targets = ["new", "formValue", "displayList", "linkType"];
    
    initialize() {
        this.items = [];
    }
    
    // Add a new link
    add(event) {
        let url = this.newTarget.value;
        let type = this.linkTypeTarget.dataset.value;
        
        this.items.push({ "url": url, "type": type });
        this.formValueTarget.value = JSON.stringify(this.items);
        
        // Update UI
    }
    
    // Remove a link
    remove(event) {
        let index = event.currentTarget.dataset.index;
        this.items.splice(index, 1);
        this.formValueTarget.value = JSON.stringify(this.items);
        
        // Update UI
    }
}
```

## Integration with Officers

The Branch Management system integrates with the Officers plugin:

- Branches can have associated officers
- Officers are assigned to specific branches and offices
- Branch officers can be filtered and displayed in branch views

```php
// Example: Getting officers for a branch
public function getOfficers($branchId)
{
    $officers = $this->Officers->find()
        ->where(['branch_id' => $branchId])
        ->contain(['Offices', 'Members'])
        ->all();
        
    return $officers;
}
```

## Branch Selection and Filtering

The system provides functionality for selecting and filtering branches:

- Branch dropdown selectors with hierarchical display
- Branch search functionality
- Branch filtering by type and parent

```php
// Example: Building a hierarchical branch list
public function getBranchList()
{
    $kingdoms = $this->Branches->find()
        ->where(['type' => 'Kingdom'])
        ->contain([
            'ChildBranches' => [
                'sort' => ['name' => 'ASC'],
                'strategy' => 'deep'
            ]
        ])
        ->all();
    
    $branchList = [];
    foreach ($kingdoms as $kingdom) {
        $branchList[$kingdom->id] = $kingdom->name;
        $this->addChildrenToBranchList($branchList, $kingdom->child_branches, 1);
    }
    
    return $branchList;
}

private function addChildrenToBranchList(&$list, $branches, $depth)
{
    foreach ($branches as $branch) {
        $prefix = str_repeat('—', $depth) . ' ';
        $list[$branch->id] = $prefix . $branch->name;
        
        if (!empty($branch->child_branches)) {
            $this->addChildrenToBranchList($list, $branch->child_branches, $depth + 1);
        }
    }
}
```

## Branch Reports

The system provides various reports related to branches:

- Branch membership reports
- Branch officer reports
- Branch hierarchy reports

```php
// Example: Branch membership report
public function membershipReport($branchId)
{
    $branch = $this->Branches->get($branchId);
    
    $members = $this->Members->find()
        ->where(['branch_id' => $branchId])
        ->all();
    
    $childBranchMembers = $this->Members->find()
        ->innerJoin(
            ['ChildBranches' => 'branches'],
            'Members.branch_id = ChildBranches.id'
        )
        ->where([
            'ChildBranches.lft >' => $branch->lft,
            'ChildBranches.rght <' => $branch->rght
        ])
        ->all();
    
    $this->set(compact('branch', 'members', 'childBranchMembers'));
}
```

## Branch Permissions

Branch-related operations are controlled through permissions:

- `manage_branches`: Create, edit, and delete branches
- `view_branches`: View branch information
- `manage_branch_hierarchy`: Modify the branch hierarchy

These permissions are enforced through policies and the authorization system.

## Next Steps

- To understand how the Officers plugin works with branches, see [Plugin System](./plugins.md)
- For information about the broader authorization system, see [Authentication and Authorization](./auth.md)
- For more details about members and their relationship to branches, see [Core Components](./core-components.md)