# Test Super User Fixture

## Overview

This fixture set provides a baseline super user for testing purposes, solving authentication and permission issues in tests. It creates a complete user with full system access through the super user permission.

## Components

The test super user setup consists of four fixtures that work together:

### 1. TestSuperUserFixture
- **Table**: `members`
- **Purpose**: Creates the test super user member account
- **Credentials**:
  - Email: `testsuper@test.com`
  - Password: `Password123` (MD5 hashed)
  - SCA Name: `Test Super User`
  - Membership Number: `TestSuperUser001`
  - Status: `verified`
  - Membership Expires: `2100-01-01`
  - Background Check Expires: `2100-01-01`

### 2. TestSuperUserRoleFixture
- **Table**: `roles`
- **Purpose**: Creates the "TestSuperUser" role
- **Properties**:
  - Name: `TestSuperUser`
  - Is System: `false`
  - Non-deleted

### 3. TestSuperUserRolePermissionFixture
- **Table**: `roles_permissions`
- **Purpose**: Links the TestSuperUser role to the "Is Super User" permission
- **Relationship**: Role ID 2 → Permission ID 1

### 4. TestSuperUserMemberRoleFixture
- **Table**: `member_roles`
- **Purpose**: Assigns the TestSuperUser role to the test super user member
- **Relationship**: Member ID 2 → Role ID 2
- **Grant Type**: `Direct Grant`

## Usage in Tests

### Basic Usage

Include the fixtures in your test class:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestCase;

class MyControllerTest extends IntegrationTestCase
{
    /**
     * Fixtures
     *
     * @var array
     */
    protected array $fixtures = [
        'app.Branches',
        'app.Permissions',
        'app.Roles',
        'app.Members',
        'app.RolesPermissions',
        'app.MemberRoles',
        // Add the test super user fixtures
        'app.TestSuperUser',
        'app.TestSuperUserRole',
        'app.TestSuperUserRolePermission',
        'app.TestSuperUserMemberRole',
    ];

    /**
     * Test method with authenticated super user
     *
     * @return void
     */
    public function testWithSuperUser(): void
    {
        // Authenticate as the test super user
        $this->session([
            'Auth' => [
                'id' => 2, // Test super user ID
                'email_address' => 'testsuper@test.com',
                'sca_name' => 'Test Super User',
            ]
        ]);

        // Now make your test requests
        $this->get('/members/index');
        $this->assertResponseOk();
    }
}
```

### Using Login Helper

For tests that use the login method:

```php
public function testWithLogin(): void
{
    // Log in as test super user
    $this->post('/users/login', [
        'email_address' => 'testsuper@test.com',
        'password' => 'Password123',
    ]);

    // Make authenticated requests
    $this->get('/members/index');
    $this->assertResponseOk();
}
```

### Accessing the Test Super User in Tests

You can reference the test super user in your test data:

```php
public function testCreateNote(): void
{
    $this->session([
        'Auth' => ['id' => 2] // Test super user
    ]);

    $data = [
        'author_id' => 2, // Test super user as author
        'subject' => 'Test Note',
        'body' => 'Note body',
        'topic_model' => 'Members',
        'topic_id' => 2,
    ];

    $this->post('/notes/add', $data);
    $this->assertResponseSuccess();
}
```

## Database IDs

The test super user fixtures use these predictable IDs:

- **Member ID**: 2 (after admin who is ID 1)
- **Role ID**: 2 (after Admin role which is ID 1)
- **Permission ID**: 1 (Is Super User - existing permission)
- **Branch ID**: 1 (Kingdom - existing branch)

## Fixtures Loading Order

The fixtures must be loaded in this order to maintain referential integrity:

1. `Branches` (provides branch_id = 1)
2. `Permissions` (provides permission_id = 1)
3. `Roles` (provides role_id = 1 for Admin)
4. `TestSuperUserRole` (creates role_id = 2)
5. `Members` (provides member_id = 1 for Admin)
6. `TestSuperUser` (creates member_id = 2)
7. `RolesPermissions` (links Admin role to permissions)
8. `TestSuperUserRolePermission` (links TestSuperUser role to Is Super User)
9. `MemberRoles` (links Admin member to Admin role)
10. `TestSuperUserMemberRole` (links test super user to TestSuperUser role)

## Why This Solves Permission Issues

1. **Complete Permission Chain**: The fixtures establish the full chain:
   - Member → MemberRole → Role → RolePermission → Permission
   
2. **Super User Permission**: The "Is Super User" permission (`is_super_user = 1`) grants full system access, bypassing all authorization checks.

3. **Verified Status**: The member has `status = 'verified'`, ensuring they can log in.

4. **Active Membership**: Membership expires in 2100, ensuring the member is always active during tests.

5. **Background Check**: Background check is valid until 2100, meeting any background check requirements.

6. **Consistent IDs**: Uses predictable, hardcoded IDs that won't change between test runs.

## Testing Authentication Methods

### For CakePHP's Authentication Plugin

```php
use Authentication\IdentityInterface;
use Cake\ORM\TableRegistry;

public function testWithAuthenticationPlugin(): void
{
    $membersTable = TableRegistry::getTableLocator()->get('Members');
    $member = $membersTable->get(2); // Test super user
    
    $this->session([
        'Auth' => $member
    ]);
    
    // Your test code
}
```

### For Authorization Checks

The test super user will pass all authorization checks because:
- Has the "Is Super User" permission
- Has an active membership
- Has a valid background check
- Is verified

## Troubleshooting

### Fixture Not Found
If you get "Fixture not found" errors, ensure:
1. All four fixtures are included in your `$fixtures` array
2. Dependencies (Branches, Permissions, Roles, Members) are loaded first
3. Fixtures are in the correct directory: `app/tests/Fixture/`

### Wrong IDs
If IDs don't match, check:
1. Fixture loading order - dependencies must load first
2. Other fixtures that might create records before these
3. Auto-increment is working correctly in your test database

### Permission Denied
If still getting permission errors:
1. Verify the test super user is authenticated in your test
2. Check that the "Is Super User" permission exists (from PermissionsFixture)
3. Verify the roles_permissions link was created
4. Check your policy classes handle super users correctly

## Example Test Class Template

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestCase;

class ExampleControllerTest extends IntegrationTestCase
{
    protected array $fixtures = [
        'app.AppSettings',
        'app.Branches',
        'app.Permissions',
        'app.Roles',
        'app.Members',
        'app.RolesPermissions',
        'app.MemberRoles',
        'app.TestSuperUser',
        'app.TestSuperUserRole',
        'app.TestSuperUserRolePermission',
        'app.TestSuperUserMemberRole',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Authenticate as test super user for all tests
        $this->session([
            'Auth' => [
                'id' => 2,
                'email_address' => 'testsuper@test.com',
                'sca_name' => 'Test Super User',
            ]
        ]);
    }

    public function testIndex(): void
    {
        $this->get('/your/route');
        $this->assertResponseOk();
    }

    public function testAdd(): void
    {
        $this->post('/your/route', ['data' => 'value']);
        $this->assertResponseSuccess();
    }
}
```

## Integration with Existing Tests

To integrate with existing tests that already have fixture setups:

1. Add the four test super user fixtures to the existing `$fixtures` array
2. Update authentication to use member ID 2 instead of ID 1 (if you want to use the test super user instead of admin)
3. Or keep existing authentication and use the test super user for specific tests

## Benefits

- **No More Permission Errors**: Super user permission grants full access
- **Consistent Test Data**: Same IDs across all test runs
- **Easy Authentication**: Simple session setup with predictable credentials
- **Complete Setup**: All relationships established automatically
- **Reusable**: Use across all test classes that need authenticated users
- **Maintainable**: Centralized in fixture files, not scattered in test methods

## Related Files

- `/app/tests/Fixture/TestSuperUserFixture.php`
- `/app/tests/Fixture/TestSuperUserRoleFixture.php`
- `/app/tests/Fixture/TestSuperUserRolePermissionFixture.php`
- `/app/tests/Fixture/TestSuperUserMemberRoleFixture.php`
- `/app/config/Seeds/Lib/SeedHelpers.php` (updated with test lookups)

## Support

For issues or questions:
1. Check fixture loading order
2. Verify all dependencies are loaded
3. Review the authorization policies in your app
4. Check the session authentication setup

## Future Enhancements

Consider adding:
- Additional test users with specific permission sets
- Test user with expired membership for testing access control
- Test user with specific branch affiliations for testing branch hierarchy
- Test user with background check expiration for testing background check requirements
