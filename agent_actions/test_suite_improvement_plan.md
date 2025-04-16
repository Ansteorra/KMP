# KMP Test Suite Improvement Plan

## Current Issues

After analyzing the PHPUnit test results, the following key issues were identified:

1. **Bootstrap Configuration:** The bootstrap.php file had an invalid call to `addPlugin()` causing the test suite to fail at startup
2. **Missing Database Schema:** The test environment is missing essential tables like `app_settings`, `members`, etc.
3. **Incomplete Fixtures:** Many fixtures are missing proper schema definitions
4. **Inconsistent Table References:** Some tests reference fixtures that don't exist
5. **Incomplete Test Implementation:** Many tests are marked as incomplete

## Completed Fixes

1. ✅ **Fixed bootstrap.php:** Removed problematic `addPlugin()` call that was causing PHPUnit bootstrap to fail
2. ✅ **Created schema.sql:** Added test database schema with essential tables required for testing
3. ✅ **Updated AppSettings Fixture:** Added proper schema definition and test data for app_settings table
4. ✅ **Implemented AppSettingsController Tests:** Added actual test methods for index, view, and add actions

## Next Steps

### 1. Update Test Database Initialization

- Ensure the schema.sql is being properly used during test initialization
- Fix the test environment configuration to properly create test tables

```php
// Update tests/bootstrap.php to use schema.sql if available
use Cake\TestSuite\Fixture\SchemaLoader;
if (file_exists(ROOT . DS . 'tests' . DS . 'schema.sql')) {
    (new SchemaLoader())->loadSqlFiles(ROOT . DS . 'tests' . DS . 'schema.sql', 'test');
}
```

### 2. Fix Missing Fixtures

Update the following key fixtures with proper schema definitions:

- Members Fixture
- Branches Fixture
- Roles Fixture
- Permissions Fixture
- Warrants Fixture
- Other fixtures referenced in tests

### 3. Add Mock Objects for Service Classes

Create mock versions of key service classes to avoid database dependencies during tests:

```php
// Example mock for AppSettingsTable
namespace App\Test\TestCase\Mock;

class MockAppSettingsTable extends \App\Model\Table\AppSettingsTable
{
    public function getAppSetting($name, $default = '', $dataType = null, $useCache = false)
    {
        // Return predefined values for testing
        $settings = [
            'KMP.configVersion' => '25.01.11.a',
            'KMP.KingdomName' => 'Test Kingdom',
            // Add other settings needed for tests
        ];
        
        return $settings[$name] ?? $default;
    }
}
```

### 4. Complete Controller Test Implementation

Implement remaining test methods:

- Complete AppSettingsController tests (edit, delete)
- Implement BranchesController tests
- Implement RolesController tests
- Complete the major controller tests

### 5. Integration Tests

Add integration tests for key workflows:

- User authentication flow
- Permissions and authorization
- Warrant management workflow

### 6. Add Javascript Tests

Set up testing for Stimulus.js controllers:

- Configure Jest for JavaScript testing
- Add test fixtures for Stimulus controllers
- Implement controller tests

## Implementation Timeline

1. **Week 1:** Fix database initialization and fixture issues
2. **Week 2:** Add mock objects and complete basic controller tests
3. **Week 3:** Implement integration tests and JavaScript tests

## Metrics for Success

- Test coverage percentage: Target 70%+ code coverage
- Passing tests: All tests should pass with no warnings
- Build stability: Tests should run consistently without random failures