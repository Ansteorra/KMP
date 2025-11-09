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
  - `.env` - Environment variables including database credentials (!! AVOID committing sensitive info, but use this when you need to call the database !!)
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
- IMPORTANT: when you want to connect to the database via commandline for testing or debugging, use the `.env` file in the `config/` directory to set your database credentials. Do NOT commit sensitive information to version control.

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

## Email and Mailer Best Practices

### Date Formatting in Mailers

All dates and times passed to mailer methods **must be pre-formatted to the kingdom's default timezone** before being sent to email templates. Email templates receive strings and should not perform any timezone conversion themselves.

**Key Principle**: Convert dates to strings in the controller or service layer using `TimezoneHelper`, not in the mailer or email template.

#### Correct Pattern

```php
use App\KMP\TimezoneHelper;

// In Service or Controller
$vars = [
    'memberScaName' => $member->sca_name,
    'warrantName' => $warrant->name,
    'warrantStart' => TimezoneHelper::formatDate($warrant->start_on),  // ✅ Format here
    'warrantExpires' => TimezoneHelper::formatDate($warrant->expires_on), // ✅ Format here
];
$this->queueMail('KMP', 'notifyOfWarrant', $member->email_address, $vars);

// In Mailer (just pass through)
public function notifyOfWarrant(
    string $to,
    string $memberScaName,
    string $warrantName,
    string $warrantStart,      // Already a formatted string
    string $warrantExpires,    // Already a formatted string
): void {
    $this->setTo($to)
        ->setSubject("Warrant Issued: $warrantName")
        ->setViewVars([
            'warrantStart' => $warrantStart,    // ✅ Just pass through
            'warrantExpires' => $warrantExpires, // ✅ Just pass through
        ]);
}
```

#### Incorrect Patterns

```php
// ❌ DON'T use virtual _to_string fields (they don't respect kingdom timezone)
$vars = [
    'warrantStart' => $warrant->start_on_to_string,  // ❌ Wrong
    'warrantExpires' => $warrant->expires_on_to_string, // ❌ Wrong
];

// ❌ DON'T use CakePHP's format methods directly (they don't respect kingdom timezone)
$vars = [
    'releaseDate' => $revokedOn->toDateString(), // ❌ Wrong
];

// ❌ DON'T pass DateTime objects to mailers
$vars = [
    'warrantStart' => $warrant->start_on, // ❌ Wrong - pass formatted string instead
];
```

#### Available TimezoneHelper Methods

```php
// Format date only (e.g., "March 15, 2025")
TimezoneHelper::formatDate($dateTime)

// Format date and time (e.g., "March 15, 2025 9:00 AM")
TimezoneHelper::formatDateTime($dateTime)

// Format time only (e.g., "9:00 AM")
TimezoneHelper::formatTime($dateTime)

// Custom format with user timezone
TimezoneHelper::formatForDisplay($dateTime, $member, 'Y-m-d H:i:s')
```

### Email Template Guidelines

Email templates should:
- Display pre-formatted date strings directly using `<?= h($varName) ?>`
- **Never** call `->format()` or timezone conversion methods
- Focus on layout and presentation, not data transformation

```php
// In email template (text or html)
Dear <?= h($memberScaName) ?>,

Your warrant for <?= h($warrantName) ?> has been issued.
Start Date: <?= h($warrantStart) ?>     <!-- ✅ Already formatted -->
Expires: <?= h($warrantExpires) ?>       <!-- ✅ Already formatted -->
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

## View Templates and Tab Ordering

### Tab Ordering System

KMP uses a CSS flexbox-based tab ordering system that allows mixing plugin tabs with template-specific tabs in any order.

#### Base Template Tabs

When adding tabs in view templates, always specify the order using `data-tab-order` attribute and inline `style="order: X;"`:

```php
<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link" 
    id="nav-my-tab-tab" 
    data-bs-toggle="tab" 
    data-bs-target="#nav-my-tab" 
    type="button" 
    role="tab"
    aria-controls="nav-my-tab" 
    aria-selected="false" 
    data-detail-tabs-target='tabBtn'
    data-tab-order="10"
    style="order: 10;"><?= __("My Tab") ?>
</button>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("tabContent") ?>
<div class="related tab-pane fade m-3" 
    id="nav-my-tab" 
    role="tabpanel" 
    aria-labelledby="nav-my-tab-tab"
    data-detail-tabs-target="tabContent"
    data-tab-order="10"
    style="order: 10;">
    <!-- Tab content here -->
</div>
<?php $this->KMP->endBlock() ?>
```

#### Order Value Guidelines

- **1-10**: Plugin tabs (Officers, Authorizations, Awards, etc.)
- **10-20**: Primary entity tabs (Members, Roles, Notes)
- **20-30**: Secondary entity tabs (Additional Info, Settings)
- **30+**: Administrative or rarely used tabs
- **999**: Default fallback for tabs without explicit order

#### Plugin Tabs

Plugin tabs automatically use the `order` field from their ViewCellRegistry configuration:

```php
$cells[] = [
    'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
    'label' => 'Tab Name',
    'id' => 'my-tab',
    'order' => 5,  // Controls tab position
    'cell' => 'Plugin.Cell',
    'validRoutes' => [...]
];
```

#### Key Requirements

1. Both tab button and content panel must have matching `data-tab-order` and `style="order: X;"`
2. Use increments of 5 or 10 for order values to allow future insertions
3. Add comments explaining order choices
4. See `/docs/tab-ordering-system.md` for complete documentation

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