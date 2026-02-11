---
layout: default
---
[← Back to Table of Contents](index.md)

# 7. Development Workflow

This section documents the development practices, standards, and workflows used in the Kingdom Management Portal project.

## 7.1 Coding Standards

KMP follows the CakePHP coding standards with some additional project-specific rules.

### PHP Coding Standards

The project uses PHP_CodeSniffer with the CakePHP ruleset to enforce coding standards:

```bash
cd /workspaces/KMP/app

# Check coding standards
composer cs-check

# Automatically fix coding standards issues
composer cs-fix

# Run all checks (tests + coding standards)
composer check

# Or run phpcs directly
vendor/bin/phpcs --colors -p
vendor/bin/phpcbf --colors -p
```

Key coding standards include:

- PSR-12 compatibility
- 4 spaces for indentation (no tabs)
- Line length should not exceed 120 characters
- Method and function names use camelCase
- Class names use PascalCase
- Constants use UPPER_CASE with underscores
- Use type hints for method parameters and return types

### Static Analysis

PHPStan is used for static analysis:

```bash
cd /workspaces/KMP/app

# PHPStan static analysis
composer stan

# Or run directly
vendor/bin/phpstan analyse
```

### Documentation Standards

- PHPDoc blocks are required for all classes, methods, and properties
- Comments should explain "why" rather than "what" where possible
- Inline docs focus on maintenance; usage examples go in `/docs`

## 7.2 Testing

KMP uses PHPUnit 10.x with a **seed SQL + transaction wrapping** strategy — NOT CakePHP fixtures.

### Test Data Strategy

Test data comes from `dev_seed_clean.sql`, loaded once at bootstrap via `SeedManager`. Each test runs inside a database transaction that rolls back automatically, so tests never affect each other.

**How it works:**

1. `tests/bootstrap.php` calls `SeedManager::bootstrap('test')` to load `dev_seed_clean.sql`
2. `BaseTestCase::setUp()` opens a transaction
3. Your test runs against the full seed dataset
4. `BaseTestCase::tearDown()` rolls the transaction back

### Test Suites

Test suites are defined in `phpunit.xml.dist`:

| Suite | Directories | Purpose |
|-------|------------|---------|
| `core-unit` | `tests/TestCase/Core/Unit`, `tests/TestCase/Model`, `tests/TestCase/Services`, `tests/TestCase/KMP`, `ApplicationTest.php` | Fast unit/service tests |
| `core-feature` | `tests/TestCase/Core/Feature`, `tests/TestCase/Controller`, `tests/TestCase/Command`, `tests/TestCase/Middleware`, `tests/TestCase/View` | HTTP and controller tests |
| `plugins` | `tests/TestCase/Plugins`, `plugins/*/tests/TestCase` | Plugin tests |
| `all` | Everything | Complete regression suite |

### Running Tests

```bash
cd /workspaces/KMP/app

# Run all tests
composer test
# or
vendor/bin/phpunit

# Run a specific suite
vendor/bin/phpunit --testsuite core-unit
vendor/bin/phpunit --testsuite core-feature
vendor/bin/phpunit --testsuite plugins
vendor/bin/phpunit --testsuite all

# Run a specific test file
vendor/bin/phpunit tests/TestCase/Controller/MembersControllerTest.php

# Run a specific test method
vendor/bin/phpunit --filter testIndex tests/TestCase/Controller/MembersControllerTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html tmp/coverage
```

### JavaScript Tests

```bash
cd /workspaces/KMP/app

# Unit tests (Jest)
npm run test:js

# UI/E2E tests (Playwright)
npm run test:ui
```

### Test Structure

```
app/tests/
├── bootstrap.php                      # Loads seed SQL, configures test DB
├── TestCase/
│   ├── BaseTestCase.php               # Transaction wrapping + data constants
│   ├── TestAuthenticationHelper.php    # Auth helper trait
│   ├── Support/
│   │   ├── HttpIntegrationTestCase.php    # Base for HTTP/controller tests
│   │   ├── PluginIntegrationTestCase.php  # Base for plugin HTTP tests
│   │   └── SeedManager.php                # Loads dev_seed_clean.sql
│   ├── Controller/                    # Controller tests
│   ├── Model/                         # Table and entity tests
│   ├── Services/                      # Service layer tests
│   ├── Command/                       # CLI command tests
│   ├── Middleware/                     # Middleware tests
│   └── View/                          # Helper and cell tests
├── js/                                # Jest unit tests
└── ui/                                # Playwright E2E tests
```

### Writing Tests

#### Base Classes

All tests extend one of these base classes (never extend `Cake\TestSuite\TestCase` directly):

| Base Class | Use For |
|-----------|---------|
| `App\Test\TestCase\BaseTestCase` | Unit tests (models, entities, services) |
| `App\Test\TestCase\Support\HttpIntegrationTestCase` | Controller/HTTP tests |
| `App\Test\TestCase\Support\PluginIntegrationTestCase` | Plugin controller tests |

#### Controller Test Pattern

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

class MembersControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    public function testIndex(): void
    {
        $this->get('/members');
        $this->assertResponseOk();
        $this->assertResponseContains('Members');
    }

    public function testAddWithValidData(): void
    {
        $data = [
            'email_address' => 'newmember@example.com',
            'sca_name' => 'New Member',
        ];
        $this->post('/members/add', $data);
        $this->assertResponseSuccess();
    }
}
```

#### Model Test Pattern

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Test\TestCase\BaseTestCase;

class MembersTableTest extends BaseTestCase
{
    protected $Members;

    protected function setUp(): void
    {
        parent::setUp();
        $this->Members = $this->getTableLocator()->get('Members');
    }

    public function testGetAdmin(): void
    {
        $admin = $this->Members->get(self::ADMIN_MEMBER_ID);
        $this->assertEquals('admin@amp.ansteorra.org', $admin->email_address);
    }
}
```

#### Plugin Test Pattern

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Plugins\Officers;

use App\Test\TestCase\Support\PluginIntegrationTestCase;

class OfficersControllerTest extends PluginIntegrationTestCase
{
    protected const PLUGIN_NAME = 'Officers';

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    public function testIndex(): void
    {
        $this->get('/officers');
        $this->assertResponseOk();
    }
}
```

### Test Data Constants

`BaseTestCase` provides constants for stable IDs in the seed data:

| Constant | Value | Description |
|----------|-------|-------------|
| `ADMIN_MEMBER_ID` | 1 | Super user (admin@amp.ansteorra.org) |
| `KINGDOM_BRANCH_ID` | 2 | Kingdom of Ansteorra (root branch) |
| `TEST_MEMBER_AGATHA_ID` | 2871 | Local MoAS test member |
| `TEST_MEMBER_BRYCE_ID` | 2872 | Local Seneschal test member |
| `TEST_MEMBER_DEVON_ID` | 2874 | Regional Armored Marshal test member |
| `TEST_MEMBER_EIRIK_ID` | 2875 | Kingdom Seneschal test member |
| `TEST_BRANCH_LOCAL_ID` | 14 | Shire of Adlersruhe |
| `TEST_BRANCH_STARGATE_ID` | 39 | Barony of Stargate |
| `TEST_BRANCH_CENTRAL_REGION_ID` | 12 | Central Region |
| `TEST_BRANCH_SOUTHERN_REGION_ID` | 13 | Southern Region |
| `ADMIN_ROLE_ID` | 1 | Admin role |
| `SUPER_USER_PERMISSION_ID` | 1 | Is Super User permission |

### Authentication in Tests

Use `TestAuthenticationHelper` (included automatically via `HttpIntegrationTestCase`):

```php
// Authenticate as admin/super user
$this->authenticateAsSuperUser();

// Authenticate as a specific test member
$this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

// Log out
$this->logout();

// Assertions
$this->assertAuthenticated();
$this->assertNotAuthenticated();
$this->assertAuthenticatedAs(self::ADMIN_MEMBER_ID);
```

### Helper Assertions (from BaseTestCase)

```php
$this->assertRecordExists('Members', ['email_address' => 'test@example.com']);
$this->assertRecordNotExists('Members', ['id' => 999]);
$this->assertRecordCount('Members', 5, ['status' => 'verified']);
```

## 7.3 Debugging

### DebugKit

The CakePHP DebugKit panel is enabled in development environments and provides information about request parameters, SQL queries, environment variables, session/cache data, and rendering timelines.

### Logging

```php
Log::debug('Operation details', ['context' => $data]);
Log::error('An error occurred', ['exception' => $exception]);
```

Log files are in `app/logs/`: `debug.log`, `error.log`, `queries.log`.

### Debug Functions

```php
dd($variable);       // Dump and die
debug($variable);    // Dump and continue
```

## 7.4 Git Workflow

KMP uses a feature branch workflow for development.

### Branch Structure

- `main`: Production-ready state
- `develop`: Development branch for next release
- `feature/feature-name`: Feature branches
- `bugfix/issue-name`: Bug fix branches
- `release/version`: Release branches

### Git Commands Reference

```bash
git clone https://github.com/Ansteorra/KMP.git
cd KMP

git checkout -b feature/new-feature-name
git add .
git commit -m "Descriptive commit message"
git push -u origin feature/new-feature-name

# Update from upstream (when working in a fork)
./merge_from_upstream.sh

# Reset the development database
./reset_dev_database.sh
```

### Commit Message Guidelines

- Begin with a short (50 chars or less) summary
- Use imperative mood ("Add feature" not "Added feature")
- Follow with a blank line and detailed explanation if needed
- Reference issue numbers in the detailed explanation

## 7.5 API Documentation Generation

KMP publishes API references for PHP services and JavaScript Stimulus controllers.

### Toolchain

- **PHP**: [`phpDocumentor`](https://www.phpdoc.org/) builds HTML docs from `app/src` and plugin PHP classes.
- **JavaScript**: [`JSDoc`](https://jsdoc.app/) parses `assets/js` along with plugin JS controllers.

### Regenerating Docs

```bash
# From repository root
./generate_api_docs.sh

# Or manually
cd app
composer docs:php
npm run docs:js
```

Output lives in `docs/api/php` and `docs/api/js`.

### Previewing Documentation

```bash
./serve_docs.sh

# Customize host/port
JEKYLL_HOST=0.0.0.0 JEKYLL_PORT=4100 ./serve_docs.sh
```

Visit `http://127.0.0.1:4000/` to browse the docs.

---

## Related Documentation

- **[7.1 Security Best Practices](7.1-security-best-practices.md)** - Security configuration and testing
- **[7.3 Testing Infrastructure](7.3-testing-infrastructure.md)** - Test infrastructure details and best practices
- **[7.6 Testing Suite Overview](7.6-testing-suite.md)** - PHPUnit suite structure and run commands

[← Back to Table of Contents](index.md)
