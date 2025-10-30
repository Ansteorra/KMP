# Test Super User - Quick Reference Card

## 🚀 Quick Setup

### Add to your test class:

```php
use SuperUserAuthenticatedTrait;

protected array $fixtures = [
    'app.Branches',
    'app.Permissions',
    'app.Roles',
    'app.Members',
    'app.RolesPermissions',
    'app.MemberRoles',
    'app.TestSuperUser',              // ← Add these 4
    'app.TestSuperUserRole',          // ← 
    'app.TestSuperUserRolePermission',// ← 
    'app.TestSuperUserMemberRole',    // ← 
];
```

Done! All tests now run as super user with full permissions.

## 📋 Test Super User Details

| Property | Value |
|----------|-------|
| **Email** | `testsuper@test.com` |
| **Password** | `Password123` (MD5) |
| **ID** | `2` |
| **SCA Name** | `Test Super User` |
| **Role** | `TestSuperUser` (ID: 2) |
| **Permission** | `Is Super User` (ID: 1) |
| **Branch** | Kingdom (ID: 1) |
| **Status** | `verified` |
| **Membership Expires** | `2100-01-01` |
| **Background Check Expires** | `2100-01-01` |

## 🎯 Usage Patterns

### Pattern 1: Automatic (Recommended)
```php
class MyTest extends TestCase
{
    use SuperUserAuthenticatedTrait;
    
    // All tests auto-authenticated!
    public function testSomething(): void
    {
        $this->get('/protected');
        $this->assertResponseOk();
    }
}
```

### Pattern 2: Manual
```php
class MyTest extends TestCase
{
    use IntegrationTestTrait;
    use TestAuthenticationHelper;
    
    public function testSomething(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/protected');
        $this->assertResponseOk();
    }
}
```

### Pattern 3: Session-based
```php
public function testSomething(): void
{
    $this->session([
        'Auth' => [
            'id' => 2,
            'email_address' => 'testsuper@test.com',
            'sca_name' => 'Test Super User',
        ]
    ]);
    $this->get('/protected');
    $this->assertResponseOk();
}
```

## 🔧 Helper Methods

```php
// Authentication
$this->authenticateAsSuperUser();
$this->authenticateAsAdmin();
$this->authenticateAsMember(3);
$this->logout();

// Assertions
$this->assertAuthenticated();
$this->assertNotAuthenticated();
$this->assertAuthenticatedAs(2);

// Getters
$id = $this->getAuthenticatedMemberId(); // Returns 2
$email = $this->getAuthenticatedMemberEmail(); // Returns 'testsuper@test.com'
```

## 📦 Required Fixtures (in order)

1. `app.Branches` - Provides Kingdom branch
2. `app.Permissions` - Provides "Is Super User" permission
3. `app.Roles` - Provides Admin role
4. `app.TestSuperUserRole` - Creates TestSuperUser role
5. `app.Members` - Provides Admin member
6. `app.TestSuperUser` - Creates test super user member
7. `app.RolesPermissions` - Links roles to permissions
8. `app.TestSuperUserRolePermission` - Links TestSuperUser to "Is Super User"
9. `app.MemberRoles` - Links members to roles
10. `app.TestSuperUserMemberRole` - Links test super user to TestSuperUser role

## ⚠️ Common Issues

### "Seed file not found" Error
✅ **Solution**: Update fixtures to make optional seeds optional:
```php
$this->getData('SeedName', null, true); // 3rd param = optional
```

### Getting 302 Redirects
✅ **Solution**: Ensure permissions are loaded on member entity (see `SuperUserAuthenticatedTrait`)

### Wrong IDs
✅ **Solution**: Check fixture loading order, ensure dependencies load first

### Permission Denied
✅ **Solution**: Verify "Is Super User" permission exists and has `is_super_user = 1`

## 📖 Full Documentation

See `/app/tests/Fixture/TEST_SUPER_USER_README.md` for:
- Detailed usage examples
- Troubleshooting guide
- Integration patterns
- Policy handling

## 🎓 Example Test Class

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\TestCase;

class RolesControllerTest extends TestCase
{
    use SuperUserAuthenticatedTrait;

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

    public function testIndex(): void
    {
        $this->get('/roles');
        $this->assertResponseOk();
    }

    public function testAdd(): void
    {
        $data = ['name' => 'New Role', 'is_system' => false];
        $this->post('/roles/add', $data);
        $this->assertResponseSuccess();
    }

    public function testEdit(): void
    {
        $this->post('/roles/edit/1', ['name' => 'Updated Role']);
        $this->assertResponseSuccess();
    }

    public function testDelete(): void
    {
        $this->delete('/roles/delete/2');
        $this->assertResponseSuccess();
    }
}
```

---

**That's it!** Add the fixtures + trait = No more permission errors! 🎉
