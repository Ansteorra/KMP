# KMP Project - CakePHP & Stimulus.JS Best Practices

This guide outlines the best practices and conventions to follow when working with the KMP project, which uses CakePHP 5.x and Stimulus.JS.

## CakePHP Structure and Conventions

### Directory Structure

Follow CakePHP's conventional directory structure:

- `src/` - Application source code
  - `Controller/` - Controllers
  - `Model/` - Models, Tables, and Entities
  - `View/` - View templates and cells
  - `KMP/` - Application-specific classes
  - `Services/` - Service classes
  - `Policy/` - Authorization policies
- `config/` - Configuration files
- `plugins/` - Plugin directories
- `templates/` - Template files
- `webroot/` - Public web files

### Naming Conventions

- **Controllers**: Plural, PascalCase, suffixed with `Controller` (e.g., `BranchesController`)
- **Models**: Singular, PascalCase (e.g., `Member`)
- **Tables**: Plural, PascalCase, suffixed with `Table` (e.g., `MembersTable`)
- **Templates**: Use lowercase, snake_case for directories and files
- **Plugins**: PascalCase (e.g., `Awards`, `Activities`)

### Routing

Use the standard CakePHP routing conventions in `config/routes.php`:

```php
$routes->scope('/', function (RouteBuilder $builder) {
    $builder->connect('/', ['controller' => 'Pages', 'action' => 'display', 'home']);
});
```

### Database

- Use migrations for database schema changes
- Name tables in plural, lowercase, with underscores
- Use proper foreign key constraints and indexes
- Follow CakePHP's naming conventions for relations

## Stimulus.JS Controllers

### Controller Organization

- Place controller files in `assets/js/controllers/` with `-controller.js` suffix
- For plugin controllers, use `plugins/PluginName/assets/js/controllers/`
- Use descriptive names that reflect functionality

### Controller Structure

Follow this pattern for all Stimulus controllers:

```javascript
import { Controller } from "@hotwired/stimulus"

class MyFeatureController extends Controller {
    // Define targets - elements your controller interacts with
    static targets = ["input", "output"]
    
    // Define values - properties that can be set from HTML
    static values = {
        url: String,
        delay: { type: Number, default: 300 }
    }
    
    // Define outlets - connections to other controllers
    static outlets = ["other-controller"]
    
    // Initialize function (optional)
    initialize() {
        // Setup code here
    }
    
    // Connect function - runs when controller connects to DOM
    connect() {
        // Connection code here
    }
    
    // Event handler methods
    handleEvent(event) {
        // Handle events
    }
    
    // Disconnect function - cleanup when controller disconnects
    disconnect() {
        // Cleanup code here
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["my-feature"] = MyFeatureController;
```

### HTML Data Attributes

Use consistent data attributes in HTML:

```html
<div data-controller="my-feature" 
     data-my-feature-url-value="/api/endpoint"
     data-my-feature-delay-value="500">
  <input data-my-feature-target="input">
  <div data-my-feature-target="output"></div>
  <button data-action="click->my-feature#handleEvent">Submit</button>
</div>
```

### Communication Between Controllers

Use the outlet pattern for controller communication:

```javascript
// Controller with outlet
static outlets = ["other-controller"]

otherControllerOutletConnected(outlet, element) {
    // Handle outlet connection
}

otherControllerOutletDisconnected(outlet) {
    // Handle outlet disconnection
}
```

## Plugin Development

### Plugin Structure

Follow this structure for plugins:

```
plugins/PluginName/
  |-- assets/
  |     |-- js/
  |     |-- css/
  |-- config/
  |-- src/
  |     |-- Controller/
  |     |-- Model/
  |     |-- Plugin.php
  |-- templates/
  |-- tests/
  |-- webroot/
```

### Plugin Registration

Register plugins in `config/plugins.php`:

```php
return [
    'PluginName' => [
        'migrationOrder' => 1,
    ],
];
```

### Plugin Bootstrapping

Initialize plugins properly in `src/Application.php`:

```php
public function bootstrap(): void
{
    parent::bootstrap();
    
    $this->addPlugin('PluginName', ['bootstrap' => true, 'routes' => true]);
}
```

## JavaScript Organization

### Entry Point

Use `assets/js/index.js` as the main entry point:

```javascript
import 'bootstrap';
import * as Turbo from "@hotwired/turbo"
import { Application } from "@hotwired/stimulus"
import KMP_utils from './KMP_utils.js';

window.KMP_utils = KMP_utils;
window.Stimulus = Application.start();

// Register all controllers
for (var controller in window.Controllers) {
    Stimulus.register(controller, window.Controllers[controller]);
}
```

### Utility Functions

Create utility modules for common functionality:

```javascript
// KMP_utils.js
export default {
    sanitizeString(str) {
        // Implementation
    },
    
    urlParam(name) {
        // Implementation
    }
};
```

### Asset Compilation

Use Laravel Mix for asset compilation as configured in `webpack.mix.js`:

```javascript
mix.js('assets/js/index.js', 'webroot/js')
   .extract(['bootstrap', 'popper.js', '@hotwired/turbo', '@hotwired/stimulus'])
   .css('assets/css/app.css', 'webroot/css')
   .version();
```

## Testing

### PHPUnit Tests

- Place tests in `tests/TestCase/` matching the src directory structure
- Tests should extend `Cake\TestSuite\TestCase` or `Cake\TestSuite\IntegrationTestCase`
- Use fixtures for database testing
- Use appropriate assertions for CakePHP responses

```php
public function testView(): void
{
    $this->get('/members/view/1');
    $this->assertResponseOk();
    $this->assertResponseContains('Member Details');
}
```

### JavaScript Testing

For Stimulus controllers, test DOM interactions:

```javascript
// Test implementation with Jest or similar
describe('MyFeatureController', () => {
    it('should handle event correctly', () => {
        // Test implementation
    });
});
```

## Asset Management

### CSS Organization

- Use Bootstrap CSS framework
- Place custom CSS in `assets/css/`
- For plugin-specific styles, use `plugins/PluginName/assets/css/`

### JavaScript Organization

- Place application JavaScript in `assets/js/`
- For plugin-specific JavaScript, use `plugins/PluginName/assets/js/`
- Use Laravel Mix for compilation

### Image Assets

- Place images in `webroot/img/` or `plugins/PluginName/webroot/img/`
- Use the AssetMix helper for versioned assets

## Authentication and Authorization

### Authentication

Use CakePHP's Authentication plugin:

```php
// In Application.php
public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
{
    $service = new AuthenticationService();
    
    // Configuration...
    
    return $service;
}
```

### Authorization

Use CakePHP's Authorization plugin with policies:

```php
// In Application.php
public function getAuthorizationService(ServerRequestInterface $request): AuthorizationServiceInterface
{
    $resolver = new ControllerResolver();
    return new AuthorizationService($resolver);
}

// In Controller
public function initialize(): void
{
    parent::initialize();
    $this->Authorization->authorizeModel("index", "add");
}
```

## Coding Standards

### PHP

- Follow PSR-12 coding standard
- Use strict types: `declare(strict_types=1);`
- Use type declarations for parameters and return types
- Use docblocks for classes and methods

```php
<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Members Controller
 */
class MembersController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void
     */
    public function index(): void
    {
        $members = $this->paginate($this->Members);
        $this->set(compact('members'));
    }
}
```

### JavaScript

- Use ES6+ syntax
- Use proper class structure for Stimulus controllers
- Document functions and complex logic
- Prefer const/let over var

```javascript
const handleResponse = (data) => {
    // Implementation
};

let counter = 0;
```

## Error Handling

### PHP Exceptions

- Use appropriate exception types
- Use try/catch blocks for recovery
- Log exceptions with sufficient context

```php
try {
    $result = $this->service->process($data);
} catch (ValidationException $e) {
    $this->Flash->error($e->getMessage());
    Log::error('Validation error: ' . $e->getMessage());
} catch (Exception $e) {
    $this->Flash->error('An unexpected error occurred');
    Log::error('Error processing data: ' . $e->getMessage());
}
```

### JavaScript Error Handling

- Use try/catch for async operations
- Add proper error handling for fetch operations

```javascript
async fetchData() {
    try {
        const response = await fetch(this.urlValue);
        if (!response.ok) {
            throw new Error(`Error: ${response.status}`);
        }
        return await response.json();
    } catch (error) {
        console.error('Error fetching data:', error);
        // Handle error in the UI
    }
}
```

## Git Workflow

- Use feature branches for new development
- Follow semantic commit messages
- Update dependencies regularly
- Use migrations for database changes

### Branch Naming

- Feature branches: `feature/feature-name`
- Bugfix branches: `fix/bug-description`
- Release branches: `release/version-number`

### Commit Messages

Follow this format:
- `feat: Add new feature`
- `fix: Fix specific bug`
- `docs: Update documentation`
- `refactor: Code refactoring without functionality change`
- `test: Add or update tests`

## Conclusion

By following these best practices, you'll ensure that the code generated for the KMP project is consistent, maintainable, and follows the established patterns in the codebase.