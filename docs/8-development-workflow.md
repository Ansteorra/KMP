---
layout: default
---
[← Back to Table of Contents](index.md)

# 8. Development Workflow

This section documents the development practices, standards, and workflows used in the Kingdom Management Portal project.

## 8.1 Coding Standards

KMP follows the CakePHP coding standards with some additional project-specific rules.

### PHP Coding Standards

The project uses PHP_CodeSniffer with the CakePHP ruleset to enforce coding standards:

```bash
# Check coding standards
cd /workspaces/KMP/app
vendor/bin/phpcs --standard=phpcs.xml src/

# Automatically fix some coding standards issues
vendor/bin/phpcbf --standard=phpcs.xml src/
```

Key coding standards include:

- PSR-12 compatibility
- 4 spaces for indentation (no tabs)
- Line length should not exceed 120 characters
- Method and function names use camelCase
- Class names use PascalCase
- Constants use UPPER_CASE with underscores
- Use type hints for method parameters and return types

### JavaScript Standards

For JavaScript, the project uses ESLint with a configuration extending the Standard JS style:

```bash
# Check JavaScript coding standards
cd /workspaces/KMP/app
npm run lint

# Automatically fix some JavaScript issues
npm run lint:fix
```

### Documentation Standards

- PHPDoc blocks are required for all classes, methods, and properties
- Comments should explain "why" rather than "what" where possible
- Complex methods should include explanatory comments

Example of a well-documented method:

```php
/**
 * Creates a new warrant based on the provided request data.
 *
 * This method validates the request, checks for conflicting warrants,
 * and creates a new warrant if all validation passes.
 *
 * @param \App\Services\WarrantManager\WarrantRequest $request The warrant request
 * @return \App\Services\ServiceResult Result with the created warrant or error messages
 */
public function createWarrant(WarrantRequest $request): ServiceResult
{
    // Method implementation...
}
```

## 8.2 Testing

KMP uses PHPUnit for testing, with separate test suites for unit, integration, and application tests.

### Test Structure

```
app/tests/
├── bootstrap.php           # Test bootstrap script
├── Fixture/                # Test fixtures
└── TestCase/               # Test cases
    ├── Command/            # Tests for CLI commands
    ├── Controller/         # Tests for controllers
    ├── Integration/        # Integration tests
    ├── Model/              # Tests for models
    ├── Service/            # Tests for services
    └── View/               # Tests for view elements
```

### Running Tests

```bash
# Run all tests
cd /workspaces/KMP/app
vendor/bin/phpunit

# Run a specific test suite
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite integration

# Run tests with code coverage report
vendor/bin/phpunit --coverage-html tmp/coverage
```

### Writing Tests

Each test class should extend the appropriate base test class:

```php
// For controller tests
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class MembersControllerTest extends TestCase
{
    use IntegrationTestTrait;
    
    // Test methods...
}

// For model/table tests
use Cake\TestSuite\TestCase;

class MembersTableTest extends TestCase
{
    protected $fixtures = [
        'app.Members',
        'app.MemberRoles',
        'app.Roles',
    ];
    
    // Test methods...
}
```

### Test Data

Fixture data is defined in the `tests/Fixture` directory. Each fixture corresponds to a database table and provides test data.

```php
// Example of a fixture
use Cake\TestSuite\Fixture\TestFixture;

class MembersFixture extends TestFixture
{
    public $fields = [
        'id' => ['type' => 'integer'],
        'email_address' => ['type' => 'string', 'length' => 255],
        'sca_name' => ['type' => 'string', 'length' => 255, 'null' => true],
        // Other fields...
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ];
    
    public $records = [
        [
            'id' => 1,
            'email_address' => 'test@example.com',
            'sca_name' => 'Test User',
            // Other field values...
        ],
        // Additional records...
    ];
}
```

## 8.3 Debugging

KMP provides several tools and techniques for debugging application issues.

### DebugKit

The CakePHP DebugKit panel is enabled in development environments and provides detailed information about:

- Request parameters
- SQL queries
- Environment variables
- Session and cache data
- Rendered view elements
- Timeline of request processing

Access the DebugKit panel by clicking the toolbar icon in the corner of the page when viewing the application in development mode.

### Logging

KMP uses CakePHP's logging system with custom log configurations:

```php
// Log debug information
Log::debug('Detailed information about the current operation', ['context' => $data]);

// Log errors
Log::error('An error occurred', ['exception' => $exception]);
```

Log files are stored in the `logs` directory:
- `debug.log`: General debugging information
- `error.log`: Error messages and exceptions
- `queries.log`: Database queries (when SQL logging is enabled)

### Debug Functions

The application includes several debugging helper functions:

```php
// Dump and die - outputs variable and halts execution
dd($variable);

// Debug - outputs variable and continues execution
debug($variable);

// Pretty print an array or object with error_log
StaticHelpers::logVar($variable, 'Label');
```

### Static Analysis

For detecting potential bugs and issues without running the code, KMP uses:

```bash
# PHPStan static analysis
cd /workspaces/KMP/app
vendor/bin/phpstan analyse src

# Psalm static analysis
vendor/bin/psalm
```

## 8.4 Git Workflow

KMP uses a feature branch workflow for development.

### Branch Structure

- `main`: Main branch, represents the production-ready state
- `develop`: Development branch, contains changes for the next release
- `feature/feature-name`: Feature branches for new functionality
- `bugfix/issue-name`: Bug fix branches
- `release/version`: Release branches for preparing releases

### Git Commands Reference

```bash
# Clone the repository
git clone https://github.com/Ansteorra/KMP.git
cd KMP

# Create a new feature branch
git checkout -b feature/new-feature-name

# Add and commit changes
git add .
git commit -m "Descriptive commit message"

# Push the branch to the remote repository
git push -u origin feature/new-feature-name

# Update from upstream (when working in a fork)
./merge_from_upstream.sh

# Reset the development database
./reset_dev_database.sh
```

### Pull Request Process

1. Create a feature branch from `develop`
2. Make your changes with clear, focused commits
3. Write or update tests as needed
4. Ensure coding standards are met (`phpcs` and `eslint`)
5. Push your branch to the repository
6. Create a pull request against the `develop` branch
7. Request code review
8. Address review feedback
9. Once approved, the branch will be merged

### Commit Message Guidelines

Follow these guidelines for commit messages:

- Begin with a short (50 chars or less) summary
- Use imperative mood ("Add feature" not "Added feature")
- Follow with a blank line and detailed explanation if needed
- Reference issue numbers in the detailed explanation

Example:
```
Add warrant expiration notification system

Implements a notification system that alerts members and administrators
when warrants are approaching expiration. Includes:
- Email notifications at 30, 14, and 7 days before expiration
- Dashboard warning for expiring warrants
- Option to disable notifications via app settings

Fixes #123
```
