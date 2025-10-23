# Template Plugin - Quick Reference Card

## File Naming Patterns

| Component | File Name Pattern | Example |
|-----------|------------------|---------|
| Plugin Class | `{Name}Plugin.php` | `TemplatePlugin.php` |
| Controller | `{Name}Controller.php` | `HelloWorldController.php` |
| Table | `{Plural}Table.php` | `HelloWorldItemsTable.php` |
| Entity | `{Singular}.php` | `HelloWorldItem.php` |
| Policy | `{Name}Policy.php` | `HelloWorldPolicy.php` |
| Service | `{Name}{Type}.php` | `TemplateNavigationProvider.php` |
| Stimulus Controller | `{name}-controller.js` | `hello-world-controller.js` |
| Template | `{action}.php` | `index.php`, `view.php` |

## Directory Structure Reference

```
plugins/YourPlugin/
├── assets/              # Frontend assets
│   ├── css/            # Stylesheets
│   └── js/
│       └── controllers/ # Stimulus controllers
├── config/
│   ├── Migrations/     # Database migrations
│   └── Seeds/          # Test data
├── src/
│   ├── Controller/     # HTTP controllers
│   ├── Model/
│   │   ├── Entity/     # Entity classes
│   │   └── Table/      # Table classes
│   ├── Policy/         # Authorization policies
│   ├── Services/       # Business logic
│   └── View/
│       └── Cell/       # View cells
├── templates/          # View templates
│   ├── {Controller}/   # Controller templates
│   ├── cell/          # Cell templates
│   └── element/       # Reusable elements
├── tests/             # PHPUnit tests
└── webroot/           # Public files
```

## Essential Code Snippets

### Minimal Plugin Class
```php
<?php
namespace YourPlugin;
use Cake\Core\BasePlugin;

class YourPluginPlugin extends BasePlugin {}
```

### Basic Controller Action
```php
public function index()
{
    $items = $this->paginate($this->YourItems);
    $this->set(compact('items'));
}
```

### Policy Permission Check
```php
public function canIndex(?IdentityInterface $user, $resource): bool
{
    return $user !== null; // Require authentication
}
```

### Navigation Items

**Parent Section:**
```php
$items[] = [
    "type" => "parent",
    "label" => "Your Plugin",
    "icon" => "bi-star",
    "id" => "navheader_your_plugin",
    "order" => 500,
];
```

**Link (Child):**
```php
$items[] = [
    "type" => "link",
    "mergePath" => ["Your Plugin"],
    "label" => "Menu Item",
    "order" => 10,
    "url" => [
        "controller" => "Items",
        "action" => "index",
        "plugin" => "YourPlugin",
        "model" => "YourPlugin.Items",
    ],
    "icon" => "bi-list",
    "activePaths" => ["your-plugin/Items/view/*"]
];
```

**Nested Link:**
```php
$items[] = [
    "type" => "link",
    "mergePath" => ["Your Plugin", "Menu Item"],
    "label" => "Sub Item",
    "order" => 0,
    "url" => [...],
    "icon" => "bi-plus",
];
```

### Custom Finder
```php
public function findActive(SelectQuery $query, array $options): SelectQuery
{
    return $query->where(['active' => true]);
}
```

### Virtual Field
```php
protected function _getFullName(): string
{
    return $this->first_name . ' ' . $this->last_name;
}
```

## Common Commands

```bash
# Run migrations
bin/cake migrations migrate -p YourPlugin

# Rollback migrations
bin/cake migrations rollback -p YourPlugin

# Run seeds
bin/cake migrations seed -p YourPlugin

# Clear cache
bin/cake cache clear_all

# Run tests
vendor/bin/phpunit plugins/YourPlugin/tests

# Generate migration
bin/cake bake migration CreateYourTable -p YourPlugin
```

## URL Routing

| Pattern | Example URL | Maps To |
|---------|------------|---------|
| `/{plugin}/{controller}` | `/template/hello-world` | `index()` |
| `/{plugin}/{controller}/view/{id}` | `/template/hello-world/view/1` | `view(1)` |
| `/{plugin}/{controller}/add` | `/template/hello-world/add` | `add()` |
| `/{plugin}/{controller}/edit/{id}` | `/template/hello-world/edit/1` | `edit(1)` |
| `/{plugin}/{controller}/delete/{id}` | `/template/hello-world/delete/1` | `delete(1)` |

## Authorization Methods

| Policy Method | Checks Permission For |
|--------------|---------------------|
| `canIndex()` | Listing records |
| `canView()` | Viewing single record |
| `canAdd()` | Creating new record |
| `canEdit()` | Updating existing record |
| `canDelete()` | Deleting record |
| `scopeIndex()` | Filtering query results |

## Common Bootstrap Icons

| Icon Class | Visual | Use For |
|-----------|--------|---------|
| `bi-list` | ☰ | List/Index |
| `bi-eye` | 👁 | View/Details |
| `bi-pencil` | ✏️ | Edit |
| `bi-trash` | 🗑️ | Delete |
| `bi-plus-circle` | ➕ | Add/Create |
| `bi-gear` | ⚙️ | Settings |
| `bi-person` | 👤 | User/Member |
| `bi-building` | 🏢 | Branch/Organization |
| `bi-star` | ⭐ | Award/Favorite |
| `bi-shield` | 🛡️ | Authorization |
| `bi-graph-up` | 📈 | Reports/Analytics |

## Form Helper Methods

```php
// Text input
$this->Form->control('title', ['class' => 'form-control']);

// Textarea
$this->Form->control('description', ['type' => 'textarea', 'rows' => 4]);

// Select dropdown
$this->Form->control('status', ['options' => $statuses]);

// Checkbox
$this->Form->control('active', ['type' => 'checkbox']);

// Date picker
$this->Form->control('date', ['type' => 'date']);

// Submit button
$this->Form->button('Save', ['class' => 'btn btn-primary']);
```

## Template Helper Methods

```php
// Link
$this->Html->link('Text', ['action' => 'index'], ['class' => 'btn btn-primary']);

// Link with icon
$this->Html->link('<i class="bi bi-eye"></i> View', $url, ['escape' => false]);

// Form POST link (for delete)
$this->Form->postLink('Delete', ['action' => 'delete', $id], ['confirm' => 'Sure?']);

// Flash message
$this->Flash->success('Success message');
$this->Flash->error('Error message');

// Pagination
$this->Paginator->prev('‹ Previous');
$this->Paginator->numbers();
$this->Paginator->next('Next ›');
```

## Validation Rules

```php
$validator
    ->scalar('title')
    ->maxLength('title', 255)
    ->requirePresence('title', 'create')
    ->notEmptyString('title');

$validator
    ->email('email')
    ->allowEmptyString('email');

$validator
    ->integer('count')
    ->greaterThan('count', 0);

$validator
    ->date('date')
    ->requirePresence('date', 'create');
```

## Association Patterns

```php
// Belongs To (Many to One)
$this->belongsTo('Members', ['foreignKey' => 'member_id']);

// Has Many (One to Many)
$this->hasMany('Items', ['foreignKey' => 'parent_id']);

// Belongs To Many (Many to Many)
$this->belongsToMany('Tags', ['through' => 'ItemsTags']);

// Has One (One to One)
$this->hasOne('Profile', ['foreignKey' => 'member_id']);
```

## Stimulus Controller Basics

```javascript
// Define targets
static targets = ["input", "output"]

// Define values
static values = { url: String, count: Number }

// Event handler
greet(event) {
    event.preventDefault();
    this.outputTarget.textContent = "Hello!";
}

// Connect/Disconnect
connect() { /* setup */ }
disconnect() { /* cleanup */ }
```

## Migration Patterns

```php
// Create table
$table = $this->table('items');
$table->addColumn('name', 'string', ['limit' => 255])
      ->addColumn('active', 'boolean', ['default' => true])
      ->addTimestamps()
      ->create();

// Add foreign key
$table->addForeignKey('member_id', 'members', 'id', [
    'delete' => 'CASCADE',
    'update' => 'CASCADE'
]);

// Add index
$table->addIndex(['name'], ['name' => 'idx_items_name']);
```

## Testing Patterns

```php
// GET request
$this->get('/your-plugin/controller/action');
$this->assertResponseOk();

// POST request
$this->post('/your-plugin/controller/action', $data);
$this->assertRedirect(['action' => 'index']);

// Check content
$this->assertResponseContains('Expected text');

// Check flash message
$this->assertFlashMessage('Success');
```

## Quick Troubleshooting

| Issue | Solution |
|-------|----------|
| Plugin not loading | Check `config/plugins.php` registration |
| Autoload error | Run `composer dump-autoload` |
| Migration error | Check migration order in plugins.php |
| Authorization failing | Verify policy class name matches controller |
| Navigation not appearing | Check settings and clear cache |
| 404 on plugin routes | Verify routes() method in plugin class |
| Assets not loading | Check file paths and clear browser cache |

## Useful Resources

- CakePHP Docs: https://book.cakephp.org/5/
- Stimulus.js: https://stimulus.hotwired.dev/
- Bootstrap: https://getbootstrap.com/
- Bootstrap Icons: https://icons.getbootstrap.com/

---

**Keep this reference handy while developing your plugin!**
