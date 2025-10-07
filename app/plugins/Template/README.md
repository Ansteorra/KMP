# Template Plugin

**The Official KMP Plugin Boilerplate** - A fully working, production-ready template for creating new Kingdom Management Portal (KMP) plugins.

## 🎯 Purpose

This is **THE** starting point for all new KMP plugins. It's not just documentation—it's a complete, functional plugin that you can copy, rename, and customize.

## ✅ What's Included

This template includes **everything** you need:

### Core Components (All Working)
- ✅ **KMPPluginInterface Implementation**: Proper migration order and constructor
- ✅ **Navigation System**: Parent sections with mergePath hierarchy (correct format)
- ✅ **CRUD Controller**: HelloWorldController with all standard actions
- ✅ **Authorization**: HelloWorldPolicy with BasePolicy integration
- ✅ **Models**: Table and Entity classes with validation
- ✅ **Views**: Bootstrap 5 styled templates (index, view, add, edit)
- ✅ **Frontend**: Stimulus.js controller and custom CSS
- ✅ **Database**: Migrations and seeds that work out-of-the-box
- ✅ **Tests**: CakePHP 5 integration tests with correct patterns

### KMP Integration (Production Quality)
- ✅ **NavigationRegistry**: Proper 3-parameter registration
- ✅ **ViewCellRegistry**: Correct signature (commented with example)
- ✅ **Settings Management**: Version-controlled configuration
- ✅ **Routing**: Plugin-specific routes with fallback
- ✅ **Middleware**: Hook points for custom middleware
- ✅ **Services**: Dependency injection examples

### Documentation (Comprehensive)
- 📘 **OVERVIEW.md** - Feature list and architecture
- 📘 **USAGE_GUIDE.md** - Step-by-step customization
- 📘 **NAVIGATION_GUIDE.md** - Complete navigation system reference
- 📘 **QUICK_REFERENCE.md** - Code snippets and patterns
- 📘 **INDEX.md** - Documentation navigation
- 📘 **SUMMARY.md** - Technical details

## 🚀 Quick Start (5 Minutes)

### Step 1: Copy the Template
```bash
cd /workspaces/KMP/app/plugins
cp -r Template MyPlugin
cd MyPlugin
```

### Step 2: Search and Replace
```bash
# Replace all occurrences (Linux/Mac)
find . -type f \( -name "*.php" -o -name "*.json" -o -name "*.md" \) -exec sed -i 's/Template/MyPlugin/g' {} +
find . -type f \( -name "*.php" -o -name "*.json" -o -name "*.md" \) -exec sed -i 's/template/my-plugin/g' {} +

# Or use your IDE's "Find and Replace in Files"
# Replace: Template → MyPlugin
# Replace: template → my-plugin
```

### Step 3: Register Plugin
Edit `/workspaces/KMP/app/config/plugins.php`:
```php
'MyPlugin' => [
    'migrationOrder' => 10,  // Adjust as needed
],
```

### Step 4: Update Autoloader
```bash
cd /workspaces/KMP/app
composer dump-autoload
bin/cake cache clear_all
```

### Step 5: Run Migrations (Optional)
```bash
bin/cake migrations migrate -p MyPlugin
bin/cake migrations seed -p MyPlugin
```

### Done! 
Access your plugin at: `http://localhost/my-plugin/hello-world`

## 🔍 What This Plugin Does

The Template plugin is fully functional and demonstrates:

- ✅ **Navigation**: "Template" parent section with "Hello World" sub-menu
- ✅ **Routes**: `/template/hello-world` with full CRUD operations
- ✅ **Authorization**: Policy-based access control (all users can view, authenticated users can add/edit)
- ✅ **Database**: `hello_world_items` table with sample data
- ✅ **Frontend**: Interactive Stimulus.js controller with CSS styling
- ✅ **Settings**: Configurable via app settings (enable/disable, show in nav, custom message)

## 📁 Directory Structure

```
Template/
├── assets/              # Frontend assets (CSS, JavaScript)
├── config/              # Migrations and seeds
├── src/
│   ├── Controller/      # HelloWorldController
│   ├── Model/          # Example models
│   ├── Policy/         # HelloWorldPolicy for authorization
│   ├── Services/       # TemplateNavigationProvider
│   └── View/           # View cells
├── templates/          # View templates
├── tests/              # PHPUnit tests
└── webroot/           # Public assets

```

## 🎨 Key Decisions & Patterns

This template demonstrates the **correct** way to implement KMP plugins:

### ✅ KMPPluginInterface Implementation
```php
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
**Why**: Required by KMPPluginInterface for proper plugin initialization order.

### ✅ Navigation Registration (Correct Format)
```php
NavigationRegistry::register(
    'template',                    // Plugin identifier
    [],                           // Static items array
    function ($user, $params) {   // Dynamic callback
        return TemplateNavigationProvider::getNavigationItems($user, $params);
    }
);
```
**Why**: 3-parameter signature matches NavigationRegistry service.

### ✅ Parent Section + mergePath Navigation
```php
// Parent section
[
    "type" => "parent",
    "label" => "Template",
    "icon" => "bi-puzzle",
    "id" => "navheader_template",
    "order" => 900,
]

// Child item
[
    "type" => "link",
    "mergePath" => ["Template"],  // NOT children array!
    "label" => "Hello World",
    // ...
]
```
**Why**: KMP navigation uses flat arrays with mergePath, not nested children.

### ✅ ViewCellRegistry (Commented Example)
```php
// ViewCellRegistry::register(
//     'template',
//     [],
//     function ($urlParams, $user) {
//         return [ /* cells */ ];
//     }
// );
```
**Why**: Shows correct 3-parameter signature. Uncomment when you add view cells.

### ✅ CakePHP 5 Test Pattern
```php
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class HelloWorldControllerTest extends TestCase {
    use IntegrationTestTrait;
}
```
**Why**: CakePHP 5 uses TestCase + trait, not IntegrationTestCase.

### ✅ Settings Management with Versioning
```php
$currentConfigVersion = '1.0.0';
$configVersion = StaticHelpers::getAppSetting('Template.configVersion', '0.0.0', null, true);

if ($configVersion != $currentConfigVersion) {
    // Update settings
}
```
**Why**: Allows automatic configuration updates during deployments.

## 📚 Documentation

All decisions and patterns are fully documented:

- **[NAVIGATION_GUIDE.md](NAVIGATION_GUIDE.md)** - Complete navigation system reference (600+ lines)
- **[USAGE_GUIDE.md](USAGE_GUIDE.md)** - Step-by-step customization guide
- **[OVERVIEW.md](OVERVIEW.md)** - Complete feature list
- **[QUICK_REFERENCE.md](QUICK_REFERENCE.md)** - Code snippets
- **[INDEX.md](INDEX.md)** - Documentation navigation

## 🔧 Customization Workflow

1. **Copy & Rename**: Follow Quick Start above
2. **Update Controller**: Modify `HelloWorldController` for your domain
3. **Update Models**: Change table/entity to match your data
4. **Customize Navigation**: Edit `TemplateNavigationProvider` 
5. **Adjust Authorization**: Modify `HelloWorldPolicy` for your rules
6. **Update Views**: Customize templates in `templates/` directory
7. **Add Features**: Follow patterns in existing files

See **[USAGE_GUIDE.md](USAGE_GUIDE.md)** for detailed instructions.

## ✅ Verification

The Template plugin has been tested and verified:
- ✅ No compilation errors
- ✅ All interfaces properly implemented
- ✅ Navigation appears correctly
- ✅ CRUD operations work
- ✅ Authorization enforced
- ✅ Tests pass
- ✅ Follows all KMP patterns

## 🎯 This is THE Reference

When in doubt about how to implement something in a KMP plugin:

1. **Check this plugin first** - It demonstrates the correct pattern
2. **Copy the code** - Don't reinvent patterns
3. **Read the documentation** - Comprehensive guides included
4. **Follow the examples** - All patterns are production-quality

## 📖 Additional Resources

- Main KMP Documentation: `/docs/plugin-boilerplate-guide.md`
- CakePHP 5 Documentation: https://book.cakephp.org/5/en/
- Stimulus.js Documentation: https://stimulus.hotwired.dev/
- Bootstrap 5 Documentation: https://getbootstrap.com/docs/5.3/

## License

MIT License - See LICENSE file for details
