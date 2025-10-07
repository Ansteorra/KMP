# Controller Test Fix - CakePHP 5 Pattern

## Issue Fixed

**Error**: `Undefined type 'Cake\TestSuite\IntegrationTestCase'`

**File**: `/workspaces/KMP/app/plugins/Template/tests/TestCase/Controller/HelloWorldControllerTest.php`

## Root Cause

The test was using the CakePHP 4 testing pattern which used `IntegrationTestCase` class. In CakePHP 5, this class was removed in favor of using traits.

## CakePHP 4 vs CakePHP 5 Testing

### CakePHP 4 Pattern (OLD - Don't Use)
```php
use Cake\TestSuite\IntegrationTestCase;

class MyControllerTest extends IntegrationTestCase
{
    public function testIndex()
    {
        $this->get('/my-path');
        $this->assertResponseOk();
    }
}
```

### CakePHP 5 Pattern (NEW - Correct)
```php
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class MyControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = [
        'plugin.MyPlugin.MyModel',
        'app.Members',
    ];

    public function testIndex()
    {
        $this->get('/my-path');
        $this->assertResponseOk();
    }
}
```

## Fix Applied

### Before (Incorrect)
```php
<?php
declare(strict_types=1);

namespace Template\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestCase;

class HelloWorldControllerTest extends IntegrationTestCase
{
    // ...
}
```

### After (Correct)
```php
<?php
declare(strict_types=1);

namespace Template\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class HelloWorldControllerTest extends TestCase
{
    use IntegrationTestTrait;
    
    protected array $fixtures = [
        // 'plugin.Template.HelloWorldItems',
        // 'app.Members',
        // 'app.Branches',
    ];
    
    // ...
}
```

## KMP Testing Patterns

### Basic Integration Test
For testing unauthenticated or simple controller actions:

```php
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class MyControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = [
        'plugin.MyPlugin.Items',
    ];

    public function testPublicAction()
    {
        $this->get('/my-plugin/items');
        $this->assertResponseOk();
        $this->assertResponseContains('Expected Text');
    }
}
```

### Authenticated Integration Test
For testing controller actions that require authentication:

```php
use Cake\TestSuite\TestCase;
use App\Test\TestCase\Controller\AuthenticatedTrait;

class MyControllerTest extends TestCase
{
    use AuthenticatedTrait;  // Includes IntegrationTestTrait + auth setup

    protected array $fixtures = [
        'plugin.MyPlugin.Items',
        'app.Members',
        'app.Branches',
        'app.Warrants',
    ];

    public function testAuthenticatedAction()
    {
        // User is automatically logged in by AuthenticatedTrait
        $this->get('/my-plugin/items/add');
        $this->assertResponseOk();
    }

    public function testPost()
    {
        $data = ['name' => 'Test Item'];
        $this->post('/my-plugin/items/add', $data);
        $this->assertResponseSuccess();
        $this->assertRedirect(['action' => 'index']);
    }
}
```

## AuthenticatedTrait Features

The `App\Test\TestCase\Controller\AuthenticatedTrait` provides:

1. **Automatic Authentication**: Logs in member ID 1 with full permissions
2. **CSRF Protection**: Enables CSRF tokens for form testing
3. **Security Tokens**: Enables security tokens
4. **Session Setup**: Configures test session with authenticated user

### AuthenticatedTrait Implementation
```php
namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;

trait AuthenticatedTrait
{
    use IntegrationTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        
        $membersTable = $this->getTableLocator()->get('Members');
        $member = $membersTable->get(1);
        $member->warrantableReview();
        $membersTable->save($member, ['checkRules' => false, 'callbacks' => false]);
        
        $this->session([
            'Auth' => $member,
        ]);
    }
}
```

## Common Test Assertions

### Response Assertions
```php
$this->assertResponseOk();           // 200 status
$this->assertResponseSuccess();      // 2xx status
$this->assertResponseError();        // 4xx/5xx status
$this->assertResponseCode(200);      // Specific code
```

### Content Assertions
```php
$this->assertResponseContains('text');
$this->assertResponseNotContains('text');
$this->assertResponseRegExp('/pattern/');
```

### Redirect Assertions
```php
$this->assertRedirect(['action' => 'index']);
$this->assertRedirectContains('/path');
$this->assertNoRedirect();
```

### Header Assertions
```php
$this->assertHeader('Content-Type', 'application/json');
$this->assertHeaderContains('Content-Type', 'json');
```

### Session Assertions
```php
$this->assertSession('value', 'Session.key');
$this->assertSessionHasKey('key');
```

### Flash Message Assertions
```php
$this->assertFlashMessage('Success!');
$this->assertFlashElement('Flash/success');
```

## Running Tests

### Run All Tests
```bash
cd /workspaces/KMP/app
vendor/bin/phpunit
```

### Run Plugin Tests
```bash
vendor/bin/phpunit plugins/YourPlugin/tests
```

### Run Specific Test Class
```bash
vendor/bin/phpunit plugins/YourPlugin/tests/TestCase/Controller/ItemsControllerTest.php
```

### Run Specific Test Method
```bash
vendor/bin/phpunit --filter testIndex plugins/YourPlugin/tests
```

### Run with Coverage
```bash
vendor/bin/phpunit --coverage-html coverage/
```

## Documentation Updated

The following documentation files have been updated with the correct CakePHP 5 testing pattern:

1. ✅ `tests/TestCase/Controller/HelloWorldControllerTest.php` - Fixed to use TestCase + IntegrationTestTrait
2. ✅ `USAGE_GUIDE.md` - Updated with correct testing examples
3. ✅ Added examples of AuthenticatedTrait usage

## Migration Guide for Existing Tests

If you have existing tests using the old pattern, update them:

1. **Replace** `use Cake\TestSuite\IntegrationTestCase;`
   **With**: 
   ```php
   use Cake\TestSuite\IntegrationTestTrait;
   use Cake\TestSuite\TestCase;
   ```

2. **Replace** `class MyTest extends IntegrationTestCase`
   **With**: 
   ```php
   class MyTest extends TestCase
   {
       use IntegrationTestTrait;
   ```

3. **Add** fixtures array if testing with database:
   ```php
   protected array $fixtures = [
       'plugin.MyPlugin.Items',
   ];
   ```

4. **For authenticated tests**, use `AuthenticatedTrait` instead of `IntegrationTestTrait`:
   ```php
   use App\Test\TestCase\Controller\AuthenticatedTrait;
   
   class MyTest extends TestCase
   {
       use AuthenticatedTrait;
   ```

## Summary

✅ **Fixed**: Undefined IntegrationTestCase error  
✅ **Updated**: Test to use CakePHP 5 pattern (TestCase + IntegrationTestTrait)  
✅ **Documented**: Correct testing patterns in USAGE_GUIDE.md  
✅ **Added**: AuthenticatedTrait usage examples  
✅ **Verified**: No compilation errors  

The Template plugin now uses the correct CakePHP 5 testing pattern and serves as a proper reference for plugin testing!
