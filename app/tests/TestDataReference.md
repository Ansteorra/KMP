# Test Data Reference

This document maps the stable IDs in `dev_seed_clean.sql` to semantic names for use in tests. These IDs are consistent and can be relied upon when writing tests.

## Overview

All test data is loaded from the seed file located at the project root: `dev_seed_clean.sql`. Tests use database transactions for isolation, so the data remains unchanged between tests.

**Important:** These IDs are hardcoded in the seed file. Do not assume sequential IDs beyond those documented here.

## Using Test Data in Tests

### Option 1: Use BaseTestCase Constants

```php
use App\Test\TestCase\BaseTestCase;

class MyTest extends BaseTestCase
{
    public function testSomething(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $branch = $this->getTableLocator()->get('Branches')->get(self::KINGDOM_BRANCH_ID);
    }
}
```

### Option 2: Query by Attributes

```php
public function testSomething(): void
{
    $membersTable = $this->getTableLocator()->get('Members');
    $admin = $membersTable->findByEmailAddress('admin@amp.ansteorra.org')->firstOrFail();
}
```

## Members

### Primary Test Members

| ID | Email | SCA Name | Status | Notes |
|----|-------|----------|--------|-------|
| 1 | admin@amp.ansteorra.org | Admin von Admin | verified | Super user with full permissions |

**Branch Affiliations:** Member 1 is affiliated with Kingdom branch (ID 1)

**Roles:** Member 1 has Admin role (ID 1) with Super User permission (ID 1)

**Additional Test Members:** The seed file contains 2,883 members total with various statuses, branch affiliations, and permission levels. Query by specific attributes when needed.

## Branches

### Branch Hierarchy

| ID | Name | Type | Parent | Lft | Rght | Notes |
|----|------|------|--------|-----|------|-------|
| 1 | Kingdom of Ansteorra | kingdom | NULL | 1 | 108 | Root branch |

**Complete Tree:** The seed file contains 54 branches in a complete hierarchy using nested set model (lft/rght values). Query by name or type when testing hierarchies.

## Roles

### System Roles

| ID | Name | Description | Notes |
|----|------|-------------|-------|
| 1 | Admin | System administrator role | Has Super User permission |

**Additional Roles:** The seed file contains 76 roles total, including officer roles, activity marshal roles, and custom branch-specific roles.

## Permissions

### Core Permissions

| ID | Name | Description | Flags | Notes |
|----|------|-------------|-------|-------|
| 1 | Is Super User | Grants full system access | is_super_user=1 | Assigned to Admin role |

**Permission Structure:** The seed file contains 1,516 permissions covering:
- Activity-specific permissions (IDs 1001-1066)
- Officer-specific permissions
- RBAC permissions for controllers/actions
- Branch-specific permissions

**Role-Permission Mappings:** 1,897 mappings in `roles_permissions` table link roles to their permissions.

**Member-Role Assignments:** 379 assignments in `member_roles` table link members to their roles.

## Activities

### Activity Types

The seed file contains 66 activities. Common examples:

| ID Range | Activity Types | Notes |
|----------|---------------|-------|
| Various | Armored Combat, Rapier, Archery, Thrown Weapons, etc. | Activity IDs vary; query by name |

**Authorizations:** The `activities_authorizations` table contains authorization records linking members to activities with authorization levels and dates.

**Age Restrictions:** Some activities have minimum age requirements stored in the `activities_activities` table.

## Awards

### Award System

The seed file contains 184 awards across:

| Domain ID | Domain Name | Award Count |
|-----------|-------------|-------------|
| Various | Arts & Sciences, Armored Combat, Service, etc. | 8 domains total |

**Award Levels:** 7 levels including Kingdom, Principality, Baronial, etc.

**Recommendations:** Award recommendations with approval workflows are in `awards_recommendations` table.

## Officers

### Office Structure

The seed file contains:
- **591 office definitions** in `officers_offices` table
- **4 departments** organizing offices
- Officer assignments linked to branches
- Warrant tracking for officer positions

Query offices by name or department when testing officer workflows.

## Gatherings

### Event Data

The seed file contains sample events with:
- Gathering definitions
- Activity associations (which activities are offered)
- Staff assignments
- Attendance tracking
- Schedule system

Query gatherings by name or date range when testing event workflows.

## Warrants

### Warrant System

The seed file contains warrant data including:
- **Warrant rosters** - collections of related warrants
- **Warrant periods** - time periods for warrants
- **Warrant approvals** - multi-approver workflow data
- Active, pending, and expired warrants

Query warrants by status or member when testing approval workflows.

## Waivers

### Waiver Types

From Waivers plugin fixtures:

| ID | Name | Description | Notes |
|----|------|-------------|-------|
| 1 | General Liability Waiver | Standard liability waiver | From fixture |
| 2 | Minor Waiver | Waiver for minors under 18 | From fixture |
| 3 | Photo Release | Permission to photograph | From fixture |

**Note:** Waiver types may use fixtures rather than seed data. Check test setup.

## Application Settings

The seed file contains 170+ application configuration settings in `app_settings` table:

- System configuration
- Feature flags
- Email templates
- Default values

Query settings by key name when testing configuration-dependent features.

## Queue System

The Queue plugin has its own fixtures and test data separate from `dev_seed_clean.sql`. See Queue plugin test fixtures in `plugins/Queue/tests/Fixture/`.

## Querying Strategy

When writing tests:

1. **Use documented IDs** for common entities (Admin member, Kingdom branch, etc.)
2. **Query by attributes** for specific test scenarios (find member by email, branch by name)
3. **Count records** to verify operations (assert record count increased after add)
4. **Use transactions** (automatic via BaseTestCase) to isolate tests

## Updating This Document

When stable IDs are identified during test development:

1. Add them to this document with semantic names
2. Add corresponding constants to `BaseTestCase.php`
3. Update existing tests to use constants instead of hardcoded IDs
4. Document any relationships or dependencies

## Examples

### Finding Records by Attributes

```php
// Find admin member
$admin = $this->getTableLocator()->get('Members')
    ->findByEmailAddress('admin@amp.ansteorra.org')
    ->firstOrFail();

// Find Kingdom branch
$kingdom = $this->getTableLocator()->get('Branches')
    ->find()
    ->where(['type' => 'kingdom'])
    ->firstOrFail();

// Find Super User permission
$permission = $this->getTableLocator()->get('Permissions')
    ->findByName('Is Super User')
    ->firstOrFail();
```

### Testing with Transactions

```php
public function testAddMember(): void
{
    $membersTable = $this->getTableLocator()->get('Members');
    
    $beforeCount = $membersTable->find()->count();
    
    $member = $membersTable->newEntity([
        'email_address' => 'test@example.com',
        'sca_name' => 'Test User',
    ]);
    $membersTable->save($member);
    
    $afterCount = $membersTable->find()->count();
    $this->assertEquals($beforeCount + 1, $afterCount);
    
    // Transaction automatically rolls back after test
}
```

### Using Helper Methods

```php
public function testDeleteMember(): void
{
    // Assert record exists before delete
    $this->assertRecordExists('Members', ['id' => 999]);
    
    // Perform delete
    $this->Members->delete($this->Members->get(999));
    
    // Assert record no longer exists
    $this->assertRecordNotExists('Members', ['id' => 999]);
}
```

## See Also

- `tests/TestCase/BaseTestCase.php` - Base test case with constants and helpers
- `dev_seed_clean.sql` - The actual seed data file
- `docs/7.3-testing-infrastructure.md` - Testing infrastructure documentation
