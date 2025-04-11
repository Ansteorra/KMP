# Testing and Quality Assurance

This document outlines the testing strategies and quality assurance practices in the Kingdom Management Portal (KMP) project.

## Testing Philosophy

KMP follows a comprehensive testing approach to ensure system reliability, maintainability, and quality. The testing strategy includes:

- Automated unit testing
- Integration testing
- Static code analysis
- Manual QA testing
- Security testing

## Testing Tools

KMP uses several tools for testing and quality assurance:

- **PHPUnit**: Primary testing framework for PHP code
- **PHPStan**: Static code analysis to catch potential errors
- **PHP_CodeSniffer**: Coding standards compliance checking
- **Psalm**: Additional static type checking and security analysis
- **Fixture system**: For consistent test data

## Test Types

### Unit Tests

Unit tests focus on testing individual components in isolation:

- Model validations and behavior
- Service class methods
- Helper methods
- Utility functions

Example of a unit test for a service method:

```php
public function testWarrantRequestCreation(): void
{
    // Arrange: Set up test data
    $name = "Test Warrant";
    $description = "Test Description";
    $warrants = [
        new WarrantRequest("Warrant 1", "Entity.Type", 1, 1, 1, new DateTime(), new DateTime("+1 year"))
    ];
    
    // Mock dependencies
    $mockActiveWindowManager = $this->createMock(ActiveWindowManagerInterface::class);
    $service = new DefaultWarrantManager($mockActiveWindowManager);
    
    // Act: Call the method being tested
    $result = $service->request($name, $description, $warrants);
    
    // Assert: Check the results
    $this->assertTrue($result->success);
    $this->assertNotNull($result->data);
    $this->assertInstanceOf(WarrantRoster::class, $this->WarrantRosters->get($result->data));
}
```

### Integration Tests

Integration tests verify that components work together correctly:

- Controller actions
- End-to-end workflows
- Database interactions

Example of an integration test for a controller action:

```php
public function testAddAction(): void
{
    // Set up test data
    $data = [
        'name' => 'Test Branch',
        'parent_id' => 1,
        'type' => 'Local Group',
    ];
    
    // Make the request
    $this->post('/branches/add', $data);
    
    // Assert the results
    $this->assertResponseSuccess();
    $this->assertRedirect(['controller' => 'Branches', 'action' => 'index']);
    
    // Verify the data was saved
    $query = $this->Branches->find()->where(['name' => 'Test Branch']);
    $this->assertEquals(1, $query->count());
}
```

### API Tests

Tests for API endpoints verify the correct functioning of:

- Request handling
- Response formats
- Authentication and authorization
- Error handling

Example of an API test:

```php
public function testApiEndpoint(): void
{
    // Set up authentication
    $this->configRequest([
        'headers' => [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->generateToken(),
        ],
    ]);
    
    // Make the request
    $this->get('/api/members/1');
    
    // Assert the results
    $this->assertResponseOk();
    $this->assertContentType('application/json');
    
    $responseData = json_decode((string)$this->_response->getBody(), true);
    $this->assertArrayHasKey('id', $responseData);
    $this->assertEquals(1, $responseData['id']);
}
```

## Test Fixtures

KMP uses fixtures for consistent test data across tests. Fixtures are defined in the `tests/Fixture` directory and follow the CakePHP fixture conventions:

```php
class MembersFixture extends TestFixture
{
    /**
     * Records
     *
     * @var array
     */
    public array $records = [
        [
            'id' => 1,
            'email_address' => 'test@example.com',
            'password' => '$2y$10$abcdefghijklmnopqrstuv',
            'sca_name' => 'Lord Test Person',
            'membership_number' => '12345',
            'membership_expires_on' => '2026-01-01',
            'first_name' => 'Test',
            'last_name' => 'Person',
            'status' => 'active',
            'created' => '2024-01-01 00:00:00',
            'modified' => '2024-01-01 00:00:00',
        ],
        // Additional test records
    ];
}
```

## Test Organization

Tests are organized to mirror the structure of the application code:

```
tests/
├── Fixture/           # Test fixtures
│   ├── MembersFixture.php
│   ├── BranchesFixture.php
│   └── ...
├── TestCase/
│   ├── Controller/    # Controller tests
│   ├── Model/         # Model and Table tests
│   │   ├── Table/
│   │   └── Entity/
│   ├── Service/       # Service tests
│   ├── View/          # View and Helper tests
│   └── ...
└── bootstrap.php      # Test bootstrap configuration
```

## Static Analysis

### PHPStan

PHPStan analyzes code for potential issues without executing it. KMP uses PHPStan at level 8 (the highest level):

```bash
vendor/bin/phpstan analyse
```

PHPStan configuration is defined in `phpstan.neon`:

```yaml
parameters:
    level: 8
    paths:
        - src
        - plugins
    excludePaths:
        - src/Console/Installer.php
    checkMissingIterableValueType: false
```

### PHP_CodeSniffer

PHP_CodeSniffer verifies that code follows the defined coding standards:

```bash
vendor/bin/phpcs
```

Configuration is defined in `phpcs.xml`:

```xml
<?xml version="1.0"?>
<ruleset name="KMP">
    <description>KMP coding standard</description>
    <rule ref="PSR12"/>
    <file>src/</file>
    <file>plugins/</file>
    <file>config/</file>
    <exclude-pattern>*/vendor/*</exclude-pattern>
    <exclude-pattern>*/config/Migrations/*</exclude-pattern>
</ruleset>
```

### Psalm

Psalm provides additional static analysis focusing on type safety and security:

```bash
vendor/bin/psalm
```

Configuration is defined in `psalm.xml`:

```xml
<?xml version="1.0"?>
<psalm
    errorLevel="4"
    resolveFromConfigFile="true"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns="https://getpsalm.org/schema/config"
    xsi:schemaLocation="https://getpsalm.org/schema/config vendor/vimeo/psalm/config.xsd"
>
    <projectFiles>
        <directory name="src" />
        <ignoreFiles>
            <directory name="vendor" />
        </ignoreFiles>
    </projectFiles>
</psalm>
```

## Continuous Integration

KMP uses GitHub Actions for continuous integration:

- Run tests on each pull request and push to the main branch
- Verify coding standards compliance
- Run static analysis
- Generate code coverage reports

```yaml
# Example GitHub Action workflow
name: CI

on:
  push:
    branches: [ main ]
  pull_request:
    branches: [ main ]

jobs:
  tests:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: kmp_test
        ports:
          - 3306:3306
          
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        extensions: mbstring, intl, pdo_mysql
        coverage: pcov
        
    - name: Composer Install
      run: composer install --prefer-dist --no-progress
      
    - name: Run PHPUnit
      run: vendor/bin/phpunit --coverage-clover=coverage.xml
      
    - name: Run PHPStan
      run: vendor/bin/phpstan analyse
      
    - name: Run PHP_CodeSniffer
      run: vendor/bin/phpcs
      
    - name: Upload coverage to Codecov
      uses: codecov/codecov-action@v1
```

## Manual Testing

Despite automated testing, manual testing is still important for:

- User interface functionality
- User experience evaluation
- Complex workflows
- Integration with external systems

### Testing Procedures

1. **Feature Testing**: Verify that new features work as expected
2. **Regression Testing**: Ensure that existing functionality still works
3. **Cross-Browser Testing**: Test across different browsers and devices
4. **Performance Testing**: Verify system performance under load
5. **Security Testing**: Check for vulnerabilities

## Test-Driven Development

KMP encourages test-driven development (TDD) for new features:

1. Write tests that describe the expected behavior
2. Implement the feature to make the tests pass
3. Refactor the code while maintaining passing tests

## Code Coverage

Code coverage reports help identify untested parts of the codebase:

```bash
vendor/bin/phpunit --coverage-html coverage
```

The goal is to maintain high test coverage, especially for critical components:

- Core services: 90%+ coverage
- Models and controllers: 80%+ coverage
- Utility classes: 70%+ coverage

## Testing Patterns

### Mocking Dependencies

For unit tests, dependencies are mocked to isolate the component being tested:

```php
// Create a mock of the dependency
$mockActiveWindowManager = $this->createMock(ActiveWindowManagerInterface::class);

// Configure the mock behavior
$mockActiveWindowManager->method('isActive')
    ->willReturn(true);

// Inject the mock into the service being tested
$service = new DefaultWarrantManager($mockActiveWindowManager);
```

### Data Providers

PHPUnit data providers are used for testing multiple scenarios:

```php
/**
 * @dataProvider validationDataProvider
 */
public function testValidation($data, $expectedErrors): void
{
    $entity = $this->Members->newEntity($data);
    $errors = $entity->getErrors();
    
    $this->assertEquals(count($expectedErrors), count($errors));
    foreach ($expectedErrors as $field => $error) {
        $this->assertArrayHasKey($field, $errors);
    }
}

public function validationDataProvider(): array
{
    return [
        'missing email' => [
            ['sca_name' => 'Test Person'],
            ['email_address' => 'This field is required']
        ],
        'invalid email' => [
            ['email_address' => 'not-an-email'],
            ['email_address' => 'Invalid email address']
        ],
        // More test cases
    ];
}
```

## Security Testing

Security testing includes:

- Authentication and authorization testing
- Input validation testing
- CSRF protection testing
- Session security testing
- SQL injection prevention testing

## Performance Testing

Performance testing focuses on:

- Database query optimization
- Response time measurement
- Resource usage monitoring
- Load testing for concurrent users

## Next Steps

- For information about coding standards, see [Coding Standards and Practices](./coding-standards.md)
- To understand deployment, see [Deployment and Environment Setup](./deployment.md)
- For contribution guidelines, see [Contributing Guidelines](./contributing.md)