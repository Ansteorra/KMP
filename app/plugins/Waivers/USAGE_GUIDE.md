# Template Plugin - Usage Guide

This guide explains how to use the Template plugin as a starting point for creating your own KMP plugins.

## Quick Start

### 1. Copy the Template Plugin

```bash
cd /workspaces/KMP/app/plugins
cp -r Template YourPluginName
cd YourPluginName
```

### 2. Update Plugin Identity

Replace all occurrences of "Template" with "YourPluginName":

**Files to update:**
- `composer.json` - Update name, description, and namespace
- `src/YourPluginNamePlugin.php` - Rename and update class name
- All files in `src/` directories - Update namespace
- All template files - Update references
- `README.md` - Update documentation

**Search and replace:**
```bash
# Linux/Mac
find . -type f -name "*.php" -exec sed -i 's/Template/YourPluginName/g' {} +
find . -type f -name "*.json" -exec sed -i 's/template/your-plugin-name/g' {} +

# Or manually search and replace in your editor
```

### 3. Register Your Plugin

Edit `/workspaces/KMP/app/config/plugins.php`:

```php
'YourPluginName' => [
    'migrationOrder' => 10,  // Choose an appropriate order
],
```

### 4. Run Migrations (if using database)

```bash
cd /workspaces/KMP/app
bin/cake migrations migrate -p YourPluginName
bin/cake migrations seed -p YourPluginName
```

### 5. Clear Cache

```bash
bin/cake cache clear_all
```

## Customization Guide

### Modify the Controller

Edit `src/Controller/HelloWorldController.php`:

1. **Rename the controller** to match your functionality (e.g., `ItemsController`)
2. **Update the model** being used
3. **Customize actions** for your use case
4. **Add new actions** as needed

Example:
```php
public function index()
{
    $items = $this->paginate($this->YourModel);
    $this->set(compact('items'));
}
```

### Update the Policy

Edit `src/Policy/HelloWorldPolicy.php`:

1. **Rename** to match your controller (e.g., `ItemsPolicy`)
2. **Customize permission logic** for your requirements
3. **Add helper methods** for complex authorization rules

Example:
```php
public function canIndex(?IdentityInterface $user, $resource): bool
{
    // Only authenticated users can view
    return $user !== null;
}

public function canAdd(?IdentityInterface $user, $resource): bool
{
    // Only users with specific warrant can create
    return $this->hasWarrant($user, 'your_plugin_admin');
}
```

### Customize Navigation

Edit `src/Services/TemplateNavigationProvider.php`:

1. **Rename** to match your plugin (e.g., `YourPluginNavigationProvider`)
2. **Update menu items** with your routes
3. **Add conditional logic** for dynamic menus
4. **Set appropriate icons** from Bootstrap Icons

#### Navigation Format

KMP uses a specific navigation structure:

**Parent Section** (creates top-level menu category):
```php
$items[] = [
    "type" => "parent",
    "label" => "Your Plugin",
    "icon" => "bi-star",
    "id" => "navheader_your_plugin",
    "order" => 500,
];
```

**Child Link** (appears under parent):
```php
$items[] = [
    "type" => "link",
    "mergePath" => ["Your Plugin"],
    "label" => "Items",
    "order" => 10,
    "url" => [
        "controller" => "Items",
        "action" => "index",
        "plugin" => "YourPluginName",
        "model" => "YourPluginName.Items",
    ],
    "icon" => "bi-list",
    "activePaths" => [
        "your-plugin-name/Items/view/*",
        "your-plugin-name/Items/edit/*",
    ]
];
```

**Nested Item** (sub-menu under child):
```php
$items[] = [
    "type" => "link",
    "mergePath" => ["Your Plugin", "Items"],
    "label" => "Add New",
    "order" => 0,
    "url" => [
        "controller" => "Items",
        "action" => "add",
        "plugin" => "YourPluginName",
    ],
    "icon" => "bi-plus-circle",
];
```

**With Dynamic Badge** (notification counts):
```php
$items[] = [
    "type" => "link",
    "mergePath" => ["Action Items"],
    "label" => "Pending Items",
    "order" => 30,
    "url" => [...],
    "icon" => "bi-exclamation-circle",
    "badgeClass" => "bg-danger",
    "badgeValue" => [
        "class" => "YourPlugin\\Model\\Table\\ItemsTable",
        "method" => "pendingCount",
        "argument" => $user->id
    ],
];
```

### Update Templates

Edit files in `templates/HelloWorld/`:

1. **Rename the directory** to match your controller
2. **Update view variables** to match your data
3. **Customize the layout** and styling
4. **Add new views** as needed

### Modify Models

Edit `src/Model/Table/HelloWorldItemsTable.php` and `src/Model/Entity/HelloWorldItem.php`:

1. **Rename** to match your table/entity
2. **Update table name** and fields
3. **Add associations** with other tables
4. **Customize validation rules**
5. **Add custom finder methods**

### Frontend Assets

#### JavaScript Controller

Edit `assets/js/controllers/hello-world-controller.js`:

1. **Rename** to match your feature
2. **Update targets and values**
3. **Add your interactive behavior**
4. **Register with new name**

#### CSS Styles

Edit `assets/css/template.css`:

1. **Update class names** to match your plugin
2. **Add custom styling**
3. **Maintain Bootstrap compatibility**

## Testing Your Plugin

### Manual Testing

1. **Access the plugin** via browser: `http://localhost/template/hello-world`
2. **Test all CRUD operations**
3. **Verify authorization** works correctly
4. **Check navigation** appears properly
5. **Test responsive design**

### PHPUnit Tests

Create tests in `tests/TestCase/`:

**CakePHP 5 Testing Pattern:**
```php
<?php
namespace YourPluginName\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class ItemsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = [
        'plugin.YourPluginName.Items',
        'app.Members',
    ];

    public function testIndex()
    {
        $this->get('/your-plugin-name/items');
        $this->assertResponseOk();
    }
}
```

**For Authenticated Tests:**
```php
use App\Test\TestCase\Controller\AuthenticatedTrait;

class ItemsControllerTest extends TestCase
{
    use AuthenticatedTrait;  // Automatically logs in test user

    public function testAuthenticatedAction()
    {
        $this->get('/your-plugin-name/items/add');
        $this->assertResponseOk();
    }
}
```

Run tests:
```bash
vendor/bin/phpunit plugins/YourPluginName/tests
```

## Common Customizations

### Add a New Action

1. Add method to controller:
```php
public function custom($id = null)
{
    $item = $this->Items->get($id);
    $this->set(compact('item'));
}
```

2. Add policy method:
```php
public function canCustom(?IdentityInterface $user, $resource): bool
{
    return $user !== null;
}
```

3. Create template: `templates/Items/custom.php`

### Add a View Cell

1. Create cell class in `src/View/Cell/`:
```php
<?php
namespace YourPluginName\View\Cell;

use Cake\View\Cell;

class DashboardCell extends Cell
{
    public function display()
    {
        $data = $this->fetchData();
        $this->set('data', $data);
    }
}
```

2. Create cell template in `templates/cell/Dashboard/display.php`

3. Register in plugin class:
```php
ViewCellRegistry::register('your-plugin', function () {
    return [
        [
            'cell' => 'YourPluginName.Dashboard',
            'position' => 'main',
            'order' => 100,
        ],
    ];
});
```

### Add Database Association

In your Table class:
```php
public function initialize(array $config): void
{
    parent::initialize($config);
    
    $this->belongsTo('Members', [
        'foreignKey' => 'member_id',
        'joinType' => 'INNER',
    ]);
    
    $this->hasMany('RelatedItems', [
        'foreignKey' => 'parent_id',
    ]);
}
```

### Add Custom Finder

In your Table class:
```php
public function findByStatus(SelectQuery $query, array $options): SelectQuery
{
    return $query->where(['status' => $options['status'] ?? 'active']);
}
```

Use it:
```php
$items = $this->Items->find('byStatus', ['status' => 'pending']);
```

## Deployment Checklist

Before deploying your plugin:

- [ ] All "Template" references replaced with your plugin name
- [ ] Composer.json updated with correct information
- [ ] Plugin registered in `config/plugins.php`
- [ ] Migrations created and tested
- [ ] Authorization policies configured correctly
- [ ] Navigation items appear properly
- [ ] All CRUD operations work
- [ ] Frontend assets compiled (if modified)
- [ ] Tests written and passing
- [ ] Documentation updated
- [ ] Code follows KMP standards

## Troubleshooting

### Plugin not appearing in navigation

- Check that plugin is registered in `config/plugins.php`
- Verify `Template.ShowInNavigation` setting is 'yes'
- Clear cache: `bin/cake cache clear_all`
- Check navigation provider is returning items

### Authorization errors

- Verify policy class name matches controller name
- Check policy methods match action names (canIndex, canView, etc.)
- Ensure Authorization component is loaded in controller
- Review policy logic for correctness

### Database errors

- Run migrations: `bin/cake migrations migrate -p YourPluginName`
- Check table names match in Table class
- Verify foreign key constraints are satisfied
- Review migration files for errors

### Assets not loading

- Check file paths in templates
- Ensure assets are in correct directories
- Run asset compilation if using build tools
- Clear browser cache

## Additional Resources

- KMP Plugin Boilerplate Guide: `/docs/plugin-boilerplate-guide.md`
- CakePHP Documentation: https://book.cakephp.org/5/en/index.html
- Stimulus.js Documentation: https://stimulus.hotwired.dev/
- Bootstrap Documentation: https://getbootstrap.com/docs/5.3/

## Support

For questions or issues:
1. Review the plugin boilerplate documentation
2. Check existing plugin implementations (Activities, Awards, Officers)
3. Consult CakePHP documentation
4. Ask the KMP development team
