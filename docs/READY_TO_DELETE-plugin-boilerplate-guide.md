# KMP Plugin Boilerplate Guide

This guide documents the structure and requirements for creating CakePHP plugins within the Kingdom Management Portal (KMP) system.

## 🎯 Quick Start: Use the Template Plugin

**The fastest way to create a new KMP plugin is to copy the Template plugin:**

```bash
cd /workspaces/KMP/app/plugins
cp -r Template MyPlugin
# Then search/replace "Template" with "MyPlugin" throughout
# See plugins/Template/README.md for complete instructions
```

The Template plugin (`/app/plugins/Template/`) is a **fully working, production-ready** reference implementation that demonstrates all KMP plugin patterns correctly.

## Why Use the Template Plugin?

- ✅ **Complete**: Includes all components (controller, models, views, tests, etc.)
- ✅ **Working**: No errors, fully functional out-of-the-box
- ✅ **Correct**: Uses proper CakePHP 5 and KMP patterns
- ✅ **Documented**: Comprehensive documentation with examples
- ✅ **Tested**: Follows correct CakePHP 5 testing patterns
- ✅ **Copy-Paste Ready**: Designed to be copied and customized

## Understanding Plugin Architecture

This guide explains the architecture behind the Template plugin, based on analysis of Activities, Awards, Officers, Bootstrap, and GitHubIssueSubmitter plugins:

## Minimum Required Files and Structure

### Essential Directory Structure
```
plugins/YourPlugin/
├── .gitignore                    # Git ignore file
├── README.md                     # Plugin documentation
├── composer.json                 # Composer configuration (REQUIRED)
├── phpunit.xml.dist             # PHPUnit configuration
└── src/                         # Source code directory (REQUIRED)
    └── YourPluginPlugin.php     # Main plugin class (REQUIRED)
```

### Optional/Advanced Structure
```
plugins/YourPlugin/
├── assets/                      # Frontend assets (optional)
│   ├── css/                     # CSS files
│   └── js/                      # JavaScript files
├── config/                      # Configuration files (optional)
│   ├── Migrations/              # Database migrations
│   └── Seeds/                   # Database seeds
├── src/                         # Source code (REQUIRED)
│   ├── YourPluginPlugin.php     # Main plugin class (REQUIRED)
│   ├── Controller/              # Controllers
│   ├── Model/                   # Models (Tables, Entities, Behaviors)
│   ├── Services/                # Business logic services
│   ├── Policy/                  # Authorization policies
│   ├── View/                    # View cells and helpers
│   ├── Event/                   # Event handlers
│   └── Mailer/                  # Email classes
├── templates/                   # Template files (optional)
│   ├── YourController/          # Controller templates
│   ├── cell/                    # View cell templates
│   ├── element/                 # Template elements
│   └── email/                   # Email templates
├── tests/                       # Test files (optional but recommended)
├── webroot/                     # Public web files (optional)
└── tmp/                         # Temporary files (optional)
```

## Required Files

### 1. composer.json (REQUIRED)

The composer.json file is essential for CakePHP plugin autoloading. It must have the type "cakephp-plugin":

```json
{
    "name": "your-name-here/your-plugin",
    "description": "YourPlugin plugin for CakePHP",
    "type": "cakephp-plugin",
    "license": "MIT",
    "require": {
        "php": ">=8.1",
        "cakephp/cakephp": "^5.0.1"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.1"
    },
    "autoload": {
        "psr-4": {
            "YourPlugin\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "YourPlugin\\Test\\": "tests/",
            "Cake\\Test\\": "vendor/cakephp/cakephp/tests/"
        }
    }
}
```

### 2. Main Plugin Class (REQUIRED)

The main plugin class must extend `Cake\Core\BasePlugin`. For KMP plugins that need advanced features, they can also implement `App\KMP\KMPPluginInterface`.

#### Minimal Plugin Class:
```php
<?php
declare(strict_types=1);

namespace YourPlugin;

use Cake\Core\BasePlugin;

/**
 * Plugin for YourPlugin
 */
class YourPluginPlugin extends BasePlugin
{
}
```

#### Advanced Plugin Class (with KMP integration):
```php
<?php
declare(strict_types=1);

namespace YourPlugin;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use App\KMP\KMPPluginInterface;
use Cake\Event\EventManager;
use App\Services\NavigationRegistry;
use App\Services\ViewCellRegistry;
use App\KMP\StaticHelpers;

/**
 * YourPlugin - Description of your plugin
 */
class YourPluginPlugin extends BasePlugin implements KMPPluginInterface
{
    /**
     * Plugin initialization and service registration
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);
        
        // Register navigation items (if needed)
        NavigationRegistry::register(
            'your-plugin',
            [],
            function ($user, $params) {
                // Return navigation items for your plugin
                return [];
            }
        );
        
        // Register view cells (if needed)
        ViewCellRegistry::register('your-plugin', function () {
            // Return view cell configurations
            return [];
        });
    }

    /**
     * Configure plugin routes
     */
    public function routes(RouteBuilder $routes): void
    {
        $routes->plugin(
            'YourPlugin',
            ['path' => '/your-plugin'],
            function (RouteBuilder $builder) {
                // Add custom routes here
                $builder->fallbacks();
            }
        );
        parent::routes($routes);
    }

    /**
     * Configure plugin middleware
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        // Add custom middleware here if needed
        return $middlewareQueue;
    }

    /**
     * Configure plugin services
     */
    public function services(ContainerInterface $container): void
    {
        // Register services in the dependency injection container
    }

    /**
     * Configure plugin console commands
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        // Add console commands here if needed
        return $commands;
    }
}
```

## Plugin Registration

### 1. Add to config/plugins.php
```php
<?php
return [
    // ... other plugins
    'YourPlugin' => [
        'migrationOrder' => 4,  // Set appropriate order based on dependencies
    ],
];
```

### 2. Migration Order Guidelines
- **1**: Core data structures (Activities)
- **2**: Member-related plugins (Officers)
- **3**: Complex workflow plugins (Awards)
- **4+**: Utility or standalone plugins

## Common Plugin Components

### Controllers
Place in `src/Controller/` directory:
```php
<?php
declare(strict_types=1);

namespace YourPlugin\Controller;

use App\Controller\AppController;

class YourItemsController extends AppController
{
    public function index()
    {
        $items = $this->paginate($this->YourItems);
        $this->set(compact('items'));
    }
}
```

### Models
Place Tables in `src/Model/Table/` and Entities in `src/Model/Entity/`:
```php
<?php
declare(strict_types=1);

namespace YourPlugin\Model\Table;

use Cake\ORM\Table;

class YourItemsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        
        $this->setTable('your_items');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
    }
}
```

### Services
Place in `src/Services/` directory for business logic:
```php
<?php
declare(strict_types=1);

namespace YourPlugin\Services;

interface YourServiceInterface
{
    public function doSomething(): array;
}

class DefaultYourService implements YourServiceInterface
{
    public function doSomething(): array
    {
        // Business logic here
        return [];
    }
}
```

### Authorization Policies
Place in `src/Policy/` directory:
```php
<?php
declare(strict_types=1);

namespace YourPlugin\Policy;

use App\Policy\BasePolicy;
use Authorization\IdentityInterface;

class YourItemPolicy extends BasePolicy
{
    public function canView(IdentityInterface $user, $resource): bool
    {
        return true; // Implement your authorization logic
    }
}
```

## Frontend Assets

### JavaScript Controllers (Stimulus.js)
Place in `assets/js/controllers/` with `-controller.js` suffix:
```javascript
import { Controller } from "@hotwired/stimulus"

class YourFeatureController extends Controller {
    static targets = ["input", "output"]
    static values = { url: String }
    
    connect() {
        console.log("YourFeature controller connected");
    }
    
    handleEvent(event) {
        // Handle events
    }
}

// Register with global controllers
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["your-feature"] = YourFeatureController;
```

### CSS Styles
Place in `assets/css/` directory and include in main application CSS compilation.

## Database Integration

### Migrations
Place in `config/Migrations/` directory:
```php
<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateYourItems extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('your_items');
        $table->addColumn('name', 'string', ['limit' => 255])
              ->addColumn('description', 'text', ['null' => true])
              ->addColumn('created', 'datetime')
              ->addColumn('modified', 'datetime')
              ->create();
    }
}
```

### Seeds
Place in `config/Seeds/` directory for sample data.

## Testing

### PHPUnit Configuration
Include `phpunit.xml.dist`:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php" colors="true">
    <testsuites>
        <testsuite name="YourPlugin Test Suite">
            <directory>tests/</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

### Test Files
Place in `tests/TestCase/` matching the src directory structure.

## Best Practices

### Naming Conventions
- **Plugin Directory**: PascalCase (e.g., `YourPlugin`)
- **Main Plugin Class**: `{PluginName}Plugin.php`
- **Namespace**: Match the plugin directory name
- **Controllers**: Plural, PascalCase, suffixed with `Controller`
- **Models**: Singular for Entity, Plural for Table
- **Routes**: Use kebab-case URLs (`/your-plugin`)

### Security
- Implement proper authorization policies
- Use CSRF protection for forms
- Sanitize user inputs
- Follow KMP's RBAC patterns

### Integration
- Register navigation items if your plugin adds menu items (see Navigation section below)
- Register view cells for dashboard widgets
- Use KMP's service container for dependency injection
- Follow KMP's event system for loose coupling

## Navigation System

KMP uses a hierarchical navigation system with parent sections and child links organized through `mergePath` arrays.

### Navigation Structure

Navigation items have two types:

1. **Parent** - Top-level section headers (like "Award Recs.", "Config", "Members")
2. **Link** - Clickable navigation items organized under parents

### Parent Section

Create a top-level navigation section:

```php
[
    "type" => "parent",
    "label" => "Your Plugin",           // Display name
    "icon" => "bi-puzzle",              // Bootstrap icon
    "id" => "navheader_your_plugin",    // Unique ID
    "order" => 500,                     // Sort order (higher = further down)
]
```

### Navigation Links

Links are organized using `mergePath` to define hierarchy:

**Top-level link** (appears under parent):
```php
[
    "type" => "link",
    "mergePath" => ["Your Plugin"],     // Parent path
    "label" => "Items",
    "order" => 10,                      // Order within parent
    "url" => [
        "controller" => "Items",
        "action" => "index",
        "plugin" => "YourPlugin",
        "model" => "YourPlugin.Items",  // Optional, for authorization
    ],
    "icon" => "bi-list",
    "activePaths" => [                  // Highlight when viewing these paths
        "your-plugin/Items/view/*",
        "your-plugin/Items/edit/*",
    ]
]
```

**Nested link** (sub-menu under another link):
```php
[
    "type" => "link",
    "mergePath" => ["Your Plugin", "Items"],  // Nested under "Items"
    "label" => "Add New",
    "order" => 0,
    "url" => [
        "controller" => "Items",
        "action" => "add",
        "plugin" => "YourPlugin",
    ],
    "icon" => "bi-plus-circle",
]
```

**Link in existing section** (like Config):
```php
[
    "type" => "link",
    "mergePath" => ["Config"],          // Add to existing Config section
    "label" => "Your Plugin Settings",
    "order" => 100,
    "url" => [
        "controller" => "Settings",
        "action" => "index",
        "plugin" => "YourPlugin",
    ],
    "icon" => "bi-gear",
]
```

### Dynamic Badges

Add notification counts or status indicators:

```php
[
    "type" => "link",
    "mergePath" => ["Action Items"],
    "label" => "Pending Items",
    "order" => 30,
    "url" => [...],
    "icon" => "bi-exclamation-circle",
    "badgeClass" => "bg-danger",        // Bootstrap badge class
    "badgeValue" => [                   // Dynamic value source
        "class" => "YourPlugin\\Model\\Table\\ItemsTable",
        "method" => "getPendingCount",
        "argument" => $user->id         // Pass user ID to method
    ],
]
```

### Navigation Provider Service

Create a navigation provider in `src/Services/{YourPlugin}NavigationProvider.php`:

```php
<?php
declare(strict_types=1);

namespace YourPlugin\Services;

use App\KMP\StaticHelpers;

class YourPluginNavigationProvider
{
    public static function getNavigationItems($user, array $params = []): array
    {
        // Check if plugin is enabled
        if (StaticHelpers::pluginEnabled('YourPlugin') == false) {
            return [];
        }

        $items = [];

        // Parent section
        $items[] = [
            "type" => "parent",
            "label" => "Your Plugin",
            "icon" => "bi-puzzle",
            "id" => "navheader_your_plugin",
            "order" => 500,
        ];

        // Main navigation item
        $items[] = [
            "type" => "link",
            "mergePath" => ["Your Plugin"],
            "label" => "Items",
            "order" => 10,
            "url" => [
                "controller" => "Items",
                "action" => "index",
                "plugin" => "YourPlugin",
                "model" => "YourPlugin.Items",
            ],
            "icon" => "bi-list",
            "activePaths" => [
                "your-plugin/Items/view/*",
            ]
        ];

        // Conditional navigation (authenticated users only)
        if ($user !== null) {
            $items[] = [
                "type" => "link",
                "mergePath" => ["Your Plugin", "Items"],
                "label" => "Add New",
                "order" => 0,
                "url" => [
                    "controller" => "Items",
                    "action" => "add",
                    "plugin" => "YourPlugin",
                ],
                "icon" => "bi-plus-circle",
            ];
        }

        return $items;
    }
}
```

### Register Navigation

In your plugin's main class (`src/YourPluginPlugin.php`), register the navigation provider:

```php
use App\Services\NavigationRegistry;
use YourPlugin\Services\YourPluginNavigationProvider;

public function bootstrap(PluginApplicationInterface $app): void
{
    parent::bootstrap($app);

    // Register navigation
    NavigationRegistry::register(
        'your-plugin',
        function ($user, $params) {
            return YourPluginNavigationProvider::getNavigationItems($user, $params);
        }
    );
}
```

### Navigation Best Practices

1. **Use Parent Sections** - Always create a parent section for your plugin
2. **Unique IDs** - Parent IDs should be unique: `navheader_{plugin_name}`
3. **Order Numbers** - Space out order values (10, 20, 30) to allow insertions
4. **Bootstrap Icons** - Use `bi-{icon-name}` format (e.g., `bi-star`, `bi-gear`)
5. **activePaths** - Include view/edit paths for proper highlighting
6. **Conditional Items** - Check user authentication and permissions before adding items
7. **Model Parameter** - Include `model` in URL for authorization context
8. **Dynamic Badges** - Use Table methods for real-time counts

## Minimal Working Example

For a minimal working plugin, you only need:

1. **Directory**: `plugins/MyPlugin/`
2. **composer.json**: With correct autoloading configuration
3. **src/MyPluginPlugin.php**: Basic plugin class extending BasePlugin
4. **Registration**: Add to `config/plugins.php`

This creates a functioning plugin that can be extended with additional features as needed.

## Template Plugin - The Official Reference Implementation

**The Template plugin (`/app/plugins/Template/`) is the definitive, production-ready boilerplate for all KMP plugins.**

### Why This Matters

Unlike typical documentation that describes what you *should* do, the Template plugin **is** a complete, working implementation that you can copy and customize. It's not just examples—it's production code.

### What's Included (All Working)

The Template plugin provides a complete, tested starting point with:

- ✅ **KMPPluginInterface**: Proper `getMigrationOrder()` method and constructor
- ✅ **Complete CRUD Controller** with all standard actions (index, view, add, edit, delete)
- ✅ **Authorization Policy** with BasePolicy integration and permission examples
- ✅ **Navigation Integration** with parent sections and mergePath hierarchy (correct format!)
- ✅ **Database Models** (Table and Entity classes)
- ✅ **View Templates** (Index, View, Add, Edit)
- ✅ **Frontend Assets** (Stimulus.js controller and CSS)
- ✅ **Migrations and Seeds** for database setup
- ✅ **Unit Tests** with examples
- ✅ **Complete Documentation** and usage guide

### Quick Start (Copy & Customize)

```bash
# 1. Copy the template
cd /workspaces/KMP/app/plugins
cp -r Template MyPlugin
cd MyPlugin

# 2. Search and replace throughout
find . -type f \( -name "*.php" -o -name "*.json" -o -name "*.md" \) -exec sed -i 's/Template/MyPlugin/g' {} +
find . -type f \( -name "*.php" -o -name "*.json" -o -name "*.md" \) -exec sed -i 's/template/my-plugin/g' {} +

# 3. Register in config/plugins.php
# Add: 'MyPlugin' => ['migrationOrder' => 10],

# 4. Update Composer autoloader
cd /workspaces/KMP/app
composer dump-autoload
bin/cake cache clear_all

# 5. Run migrations (optional)
bin/cake migrations migrate -p MyPlugin
bin/cake migrations seed -p MyPlugin

# Done! Access at http://localhost/my-plugin/hello-world
```

### Key Decisions in Template Plugin

The Template plugin demonstrates **the correct way** to implement KMP plugins:

#### ✅ KMPPluginInterface Implementation
```php
// Required: Migration order property and methods
protected int $_migrationOrder = 0;

public function getMigrationOrder(): int {
    return $this->_migrationOrder;
}

public function __construct($config = []) {
    if (!isset($config['migrationOrder'])) {
        $config['migrationOrder'] = 0;
    }
    $this->_migrationOrder = $config['migrationOrder'];
}
```

#### ✅ Navigation Registration (3-Parameter Format)
```php
NavigationRegistry::register(
    'template',                    // Plugin identifier
    [],                           // Static items (empty array)
    function ($user, $params) {   // Dynamic callback
        return TemplateNavigationProvider::getNavigationItems($user, $params);
    }
);
```

#### ✅ Navigation Structure (Parent + mergePath)
```php
// Parent section (required)
[
    "type" => "parent",
    "label" => "Template",
    "icon" => "bi-puzzle",
    "id" => "navheader_template",
    "order" => 900,
]

// Child link (uses mergePath, NOT children!)
[
    "type" => "link",
    "mergePath" => ["Template"],  // Hierarchy via mergePath
    "label" => "Hello World",
    "order" => 10,
    "url" => [...],
    "icon" => "bi-globe",
]
```

#### ✅ ViewCellRegistry (Correct Signature)
```php
// Commented example showing correct 3-parameter format
// ViewCellRegistry::register(
//     'template',                    // Plugin identifier
//     [],                           // Static cells
//     function ($urlParams, $user) { // Dynamic callback
//         return [/* cells */];
//     }
// );
```

#### ✅ CakePHP 5 Testing Pattern
```php
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class MyControllerTest extends TestCase {
    use IntegrationTestTrait;  // NOT extends IntegrationTestCase
}
```

#### ✅ Settings with Versioning
```php
$currentConfigVersion = '1.0.0';
$configVersion = StaticHelpers::getAppSetting('Template.configVersion', '0.0.0', null, true);

if ($configVersion != $currentConfigVersion) {
    StaticHelpers::setAppSetting('Template.configVersion', $currentConfigVersion, null, true);
    // Initialize settings
}
```

### Template Plugin Files (All Working)

Copy and customize these production-ready files:

**Core Plugin Files:**
- `src/TemplatePlugin.php` - Main plugin class (implements KMPPluginInterface correctly)
- `composer.json` - Composer configuration with PSR-4 autoloading
- `phpunit.xml.dist` - PHPUnit configuration

**Application Logic:**
- `src/Controller/HelloWorldController.php` - Full CRUD controller
- `src/Policy/HelloWorldPolicy.php` - Authorization with BasePolicy
- `src/Services/TemplateNavigationProvider.php` - Navigation provider
- `src/Model/Table/HelloWorldItemsTable.php` - Table with validation
- `src/Model/Entity/HelloWorldItem.php` - Entity with virtual fields

**Views & Frontend:**
- `templates/HelloWorld/` - Bootstrap 5 styled templates (index, view, add, edit)
- `assets/js/controllers/hello-world-controller.js` - Stimulus.js controller
- `assets/css/template.css` - Custom CSS

**Database:**
- `config/Migrations/20250107000000_CreateHelloWorldItems.php` - Migration
- `config/Seeds/HelloWorldItemsSeed.php` - Sample data

**Tests:**
- `tests/TestCase/Controller/HelloWorldControllerTest.php` - Integration tests (CakePHP 5 pattern)

**Documentation:**
- `README.md` - Plugin overview and quick start
- `OVERVIEW.md` - Complete feature list
- `USAGE_GUIDE.md` - Step-by-step customization guide
- `NAVIGATION_GUIDE.md` - Complete navigation system reference (600+ lines)
- `QUICK_REFERENCE.md` - Code snippets and patterns
- `INDEX.md` - Documentation navigation
- Plus templates, assets, migrations, seeds, and tests!

For detailed instructions, see:
- `plugins/Template/OVERVIEW.md` - Complete feature list
- `plugins/Template/USAGE_GUIDE.md` - Step-by-step customization guide
- `plugins/Template/README.md` - Plugin overview and quick start

### Verification

The Template plugin has been tested and verified:
- ✅ No compilation errors
- ✅ All interfaces properly implemented (KMPPluginInterface)
- ✅ Navigation appears correctly with parent sections
- ✅ CRUD operations work out-of-the-box
- ✅ Authorization properly enforced
- ✅ Tests pass with CakePHP 5 patterns
- ✅ Follows all KMP architectural patterns
- ✅ Production-ready code quality

### When to Use the Template

**Use the Template plugin when:**
- ✅ Creating any new KMP plugin
- ✅ You need a working example to reference
- ✅ You want to follow best practices
- ✅ You need all components (controller, models, views, etc.)
- ✅ You want production-ready code to customize

**Don't reinvent the wheel** - The Template plugin has all patterns correctly implemented.

## Development Workflow

### Creating a New Plugin from Template

1. **Copy Template**: `cp -r plugins/Template plugins/MyPlugin`
2. **Search & Replace**: Change all "Template" to "MyPlugin" references
3. **Update Composer**: Modify `composer.json` with your plugin details
4. **Register Plugin**: Add to `config/plugins.php` with migration order
5. **Dump Autoload**: Run `composer dump-autoload`
6. **Clear Cache**: Run `bin/cake cache clear_all`
7. **Run Migrations**: Optional, if using database
8. **Customize**: Modify controller, models, views as needed
9. **Test**: Run `vendor/bin/phpunit plugins/MyPlugin/tests`

### Key Commands

```bash
# After copying and renaming plugin
cd /workspaces/KMP/app

# Update Composer autoloader (REQUIRED)
composer dump-autoload

# Clear CakePHP caches
bin/cake cache clear_all

# Run migrations
bin/cake migrations migrate -p MyPlugin

# Run seeds
bin/cake migrations seed -p MyPlugin

# Run tests
vendor/bin/phpunit plugins/MyPlugin/tests

# Generate new migration
bin/cake bake migration CreateMyTable -p MyPlugin
```

## Best Practices Summary

### DO ✅
- **Use the Template plugin** as your starting point
- **Copy the patterns** from Template - they're all correct
- **Follow the documentation** in Template plugin's guides
- **Run composer dump-autoload** after creating/copying plugin
- **Implement KMPPluginInterface** with getMigrationOrder()
- **Use parent sections** in navigation with mergePath
- **Test with CakePHP 5 patterns** (TestCase + IntegrationTestTrait)
- **Version your settings** for automatic updates
- **Document your plugin** following Template's example

### DON'T ❌
- **Don't guess at patterns** - check Template plugin first
- **Don't use nested children** in navigation - use mergePath
- **Don't extend IntegrationTestCase** - use TestCase + trait
- **Don't skip composer dump-autoload** - Composer won't find your classes
- **Don't forget migration order** in config/plugins.php
- **Don't reinvent patterns** - copy from Template

## Conclusion

The KMP plugin system provides a flexible, powerful foundation for extending the application. **The Template plugin is your primary reference** - it's not just documentation, it's working, tested, production-quality code that you can copy and customize.

### Remember:
1. **Start with Template** - Copy it, don't start from scratch
2. **Follow the patterns** - They're all correct and tested
3. **Read the documentation** - Comprehensive guides included
4. **Run the commands** - composer dump-autoload is essential
5. **Test your changes** - Follow the testing patterns

**The Template plugin demonstrates the correct way to do everything.** When in doubt, check the Template plugin first.