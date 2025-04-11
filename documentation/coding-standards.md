# Coding Standards and Practices

This document outlines the coding standards and development practices followed in the Kingdom Management Portal (KMP) project.

## PHP Coding Standards

KMP follows PSR-12 (PHP Standards Recommendations) extended coding style, which builds on PSR-1 and PSR-2:

### PHP Files

- Files MUST use only UTF-8 without BOM
- Files MUST use the `<?php` or `<?=` tags
- Files MUST declare strict types with `declare(strict_types=1);`
- Namespaces and classes MUST follow PSR-4 autoloading standards
- PHP files containing classes MUST be named after the class
- Each class MUST be in a file by itself
- Code MUST use 4 spaces for indenting, not tabs

### Namespace and Import Statements

- Namespaces are organized according to PSR-4 with `App` as the root namespace
- Import statements should be grouped with a blank line between groups:
  - PHP native classes
  - Framework classes
  - Application classes
  - Plugin classes

Example:
```php
<?php

declare(strict_types=1);

namespace App\Controller;

// PHP native classes
use DateTime;
use JsonSerializable;

// Framework classes
use Cake\Controller\Controller;
use Cake\Event\EventInterface;

// Application classes
use App\Model\Entity\Member;
use App\Services\WarrantManager\WarrantManagerInterface;

// Plugin classes
use Officers\Model\Entity\Officer;

class ExampleController extends Controller
{
    // Class implementation
}
```

### Class Structure

- Class properties should be declared at the top of a class
- Methods should be organized logically, with public methods first
- Type hints should be used for parameters and return types

```php
class ExampleService
{
    /**
     * @var \App\Services\DependencyServiceInterface
     */
    protected $dependencyService;
    
    /**
     * Constructor with dependency injection
     */
    public function __construct(DependencyServiceInterface $dependencyService)
    {
        $this->dependencyService = $dependencyService;
    }
    
    /**
     * Public method with type hints
     */
    public function processItem(string $itemId): bool
    {
        // Implementation
        return $this->validateItem($itemId);
    }
    
    /**
     * Protected helper method
     */
    protected function validateItem(string $itemId): bool
    {
        // Implementation
        return true;
    }
}
```

## JavaScript Coding Standards

### JavaScript Files

- JavaScript files use 2-space indentation
- Files should use camelCase for naming
- Semi-colons should be used at the end of statements
- ES6 features are preferred (arrow functions, template literals, etc.)

### Stimulus Controllers

KMP uses Stimulus.js for JavaScript interactivity:

```javascript
// Example Stimulus controller
class BranchLinksController extends Controller {
  static targets = ["new", "formValue", "displayList", "linkType"];
  
  initialize() {
    this.items = [];
  }
  
  connect() {
    // Process initial data
    const initialData = this.formValueTarget.value;
    if (initialData) {
      this.items = JSON.parse(initialData);
      this.items.forEach(item => this.createListItem(item));
    }
  }
  
  // Additional methods
}
```

## Database Conventions

### Table Naming

- Table names are plural and underscored (e.g., `members`, `warrant_rosters`)
- Junction tables use both model names in plural (e.g., `roles_permissions`)

### Column Naming

- Primary keys are named `id`
- Foreign keys are named with the singular model followed by `_id` (e.g., `member_id`)
- Boolean columns typically use `is_` or similar prefix (e.g., `is_active`)
- Timestamp columns use `created` and `modified` for CakePHP's Timestamp behavior

### Migrations

- One change per migration file
- Migrations should be reversible when possible
- Descriptive names that indicate the purpose of the migration

## Git Workflow

### Branching Strategy

KMP uses a modified Git Flow approach:

- `main` branch contains production-ready code
- Feature branches are created from `main` for new features
- Branches should follow the naming convention:
  - `feature/short-description` for new features
  - `bugfix/short-description` for bug fixes
  - `hotfix/short-description` for critical fixes

### Commit Messages

Commit messages should follow a standard format:

```
[Type] Short summary (50 chars max)

More detailed explanation if needed. Wrap at about 72 characters.
Explain what and why, not how (the code shows the how).

Resolves: #123
```

Types include:
- `[Feature]` - New functionality
- `[Fix]` - Bug fixes
- `[Docs]` - Documentation changes
- `[Style]` - Code style changes (formatting, missing semicolons, etc.)
- `[Refactor]` - Code changes that neither fix bugs nor add features
- `[Test]` - Adding or correcting tests
- `[Chore]` - Changes to the build process, dependencies, etc.

### Pull Requests

- Pull requests should focus on a single feature or fix
- Each pull request should include appropriate tests
- Code reviews are required before merging
- The pull request description should explain the changes and the reasons for them

### Managing Upstream Changes

For forks of the KMP repository, use the provided script to merge upstream changes:

```bash
./merge_from_upstream.sh
```

This script fetches changes from the upstream repository and merges them into the current branch.

## Testing

### PHPUnit Tests

- CakePHP's testing framework is used for automated tests
- Tests follow the same namespace structure as the application code
- Tests should be isolated and not depend on external services
- Fixtures are used for database testing

```php
public function testProcessWithValidData(): void
{
    $result = $this->service->process($this->validData);
    $this->assertTrue($result->success);
    $this->assertSame('Success', $result->message);
}
```

### Static Analysis

KMP uses PHPStan for static analysis to catch potential issues:

```bash
vendor/bin/phpstan analyse
```

### Code Style Checking

PHP_CodeSniffer is used to verify code style compliance:

```bash
vendor/bin/phpcs
```

## Documentation

### PHPDoc Blocks

- All classes and methods should have PHPDoc blocks
- Parameter and return types should be documented
- Complex logic should include explanatory comments

```php
/**
 * Process a warrant request
 *
 * @param string $name Warrant name
 * @param string $description Warrant description
 * @param \App\Services\WarrantManager\WarrantRequest[] $warrants Array of warrant requests
 * @return \App\Services\ServiceResult Result of the operation
 */
public function request(string $name, string $description, array $warrants): ServiceResult
{
    // Implementation
}
```

### Developer Documentation

- Developer documentation is maintained in the `documentation/` directory
- Documentation should be updated when significant changes are made
- Documentation is written in Markdown format

## Dependency Management

### Composer for PHP Dependencies

- PHP dependencies are managed with Composer
- The `composer.json` file defines all PHP dependencies
- Run `composer update` to update dependencies

### NPM for JavaScript Dependencies

- JavaScript dependencies are managed with NPM
- The `package.json` file defines all JavaScript dependencies
- Run `npm update` to update dependencies

## Build and Deployment

### Development Environment

- KMP includes a dev container configuration for consistent development environments
- Local development uses a development-specific configuration in `config/app_local.php`

### Database Management

KMP provides several scripts for managing the database:

- `bin/reset_dev_database.sh` - Reset the development database
- `bin/update_database.sh` - Apply pending migrations
- `bin/revert_database.sh` - Revert the most recent migration

### Asset Compilation

Laravel Mix (webpack wrapper) is used for asset compilation:

```bash
# Development
npm run dev

# Production
npm run production
```

## Next Steps

- For details about specific components, see [Core Components](./core-components.md)
- To understand the authentication and authorization practices, see [Authentication and Authorization](./auth.md)
- For plugin development standards, see [Plugin System](./plugins.md)