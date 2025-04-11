---
layout: default
---
# 11. Extending KMP

The Kingdom Management Portal is designed to be extensible. This section explains how developers can extend and customize the application to meet specific needs.

## 11.1 Creating Plugins

Plugins are the primary mechanism for extending KMP. They allow you to add new functionality while keeping your code separate from the core application.

### Plugin Structure

Create a new plugin with this standard structure:

```
plugins/MyPlugin/
├── config/             # Plugin configuration
│   ├── routes.php      # Plugin routes
│   ├── bootstrap.php   # Plugin bootstrap code
│   └── Migrations/     # Database migrations
├── src/                # Plugin source code
│   ├── Plugin.php      # Plugin class
│   ├── Controller/     # Controllers
│   ├── Model/          # Models
│   │   ├── Entity/     # Entities
│   │   └── Table/      # Tables
│   ├── View/           # View classes
│   │   └── Helper/     # View helpers
│   ├── Event/          # Event listeners
│   └── Service/        # Business services
├── templates/          # Template files
│   ├── layout/         # Layout templates
│   ├── cell/           # Cell templates
│   └── MyController/   # Controller templates
├── webroot/            # Public assets
│   ├── css/            # Stylesheets
│   ├── js/             # JavaScript files
│   └── img/            # Images
└── tests/              # Test cases
```

### Plugin Class

Every plugin needs a main Plugin class:

```php
namespace MyPlugin;

use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;

class Plugin extends BasePlugin
{
    /**
     * Plugin name
     *
     * @var string
     */
    protected $name = 'MyPlugin';

    /**
     * Enable middleware
     *
     * @var bool
     */
    protected $middlewareEnabled = false;

    /**
     * Load routes or not
     *
     * @var bool
     */
    protected $routesEnabled = true;

    /**
     * Console middleware
     *
     * @var bool
     */
    protected $consoleEnabled = false;

    /**
     * Bootstrap hook
     *
     * @param \Cake\Core\PluginApplicationInterface $app Application instance
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);
        
        // Add custom bootstrap code here
    }

    /**
     * Routes hook
     *
     * @param \Cake\Routing\RouteBuilder $routes Routes builder
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        parent::routes($routes);
        
        $routes->plugin('MyPlugin', function (RouteBuilder $builder) {
            $builder->connect('/', ['controller' => 'MyController', 'action' => 'index']);
            // Add more routes here
        });
    }

    /**
     * Middleware hook
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue Middleware queue
     * @return \Cake\Http\MiddlewareQueue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        // Add middleware here
        return $middlewareQueue;
    }
}
```

### Plugin Registration

Register your plugin in `config/plugins.php`:

```php
return [
    // ... other plugins
    'MyPlugin' => [
        'routes' => true,
        'bootstrap' => true,
        'migrationOrder' => 10, // After core plugins
    ],
];
```

### Database Migrations

Create migrations for your plugin's database tables:

```bash
bin/cake bake migration -p MyPlugin CreateMyPluginTable
```

Example migration file:

```php
use Migrations\AbstractMigration;

class CreateMyPluginTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('my_plugin_items');
        $table->addColumn('name', 'string', [
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('description', 'text', [
            'null' => true,
        ]);
        $table->addColumn('member_id', 'integer', [
            'null' => false,
        ]);
        $table->addColumn('active', 'boolean', [
            'default' => true,
        ]);
        $table->addColumn('created', 'datetime');
        $table->addColumn('modified', 'datetime');
        $table->addForeignKey('member_id', 'members', 'id', [
            'delete' => 'CASCADE',
            'update' => 'NO_ACTION',
        ]);
        $table->create();
    }
}
```

## 11.2 Event System

The event system is a powerful way to extend KMP without modifying core code. It allows your code to respond to key moments in the application lifecycle.

### Event Listeners

Create an event listener class:

```php
namespace MyPluginvent;

use CakeventventInterface;
use CakeventventListenerInterface;

class MyPluginListener implements EventListenerInterface
{
    /**
     * List of events this listener responds to
     *
     * @return array<string,mixed>
     */
    public function implementedEvents(): array
    {
        return [
            'Model.Member.afterSave' => 'onMemberSave',
            'Navigation.build' => 'onNavigationBuild',
        ];
    }

    /**
     * Handle member save event
     *
     * @param \CakeventventInterface $event Event instance
     * @param \App\Modelntity\Member $member The saved member
     * @param \ArrayObject $options Save options
     * @return void
     */
    public function onMemberSave(EventInterface $event, $member, $options)
    {
        // Custom logic when a member is saved
    }

    /**
     * Add items to the navigation
     *
     * @param \CakeventventInterface $event Event instance
     * @param \ArrayObject $navigation Navigation items
     * @return void
     */
    public function onNavigationBuild(EventInterface $event, $navigation)
    {
        $navigation->push([
            'title' => 'My Plugin',
            'url' => [
                'plugin' => 'MyPlugin',
                'controller' => 'MyController',
                'action' => 'index',
            ],
            'icon' => 'plugin-icon',
        ]);
    }
}
```

### Registering Listeners

Register your listener in the plugin's bootstrap method:

```php
public function bootstrap(PluginApplicationInterface $app): void
{
    parent::bootstrap($app);
    
    // Register event listeners
    $eventManager = \CakeventventManager::instance();
    $eventManager->on(new \MyPluginvent\MyPluginListener());
}
```

### Common Events

KMP provides many events you can listen for:

#### Member Events

- `Model.Member.beforeSave`
- `Model.Member.afterSave`
- `Model.Member.beforeDelete`
- `Model.Member.afterDelete`

#### Warrant Events

- `Warrant.beforeCreate`
- `Warrant.afterCreate`
- `Warrant.beforeStateChange`
- `Warrant.afterStateChange`

#### Navigation Events

- `Navigation.build`
- `Navigation.beforeRender`

#### View Events

- `View.beforeRender`
- `View.afterRender`
- `View.beforeLayout`
- `View.afterLayout`

## 11.3 Custom Reports

KMP provides a framework for creating custom reports that can be accessed through the application's reporting interface.

### Report Class

Create a report class:

```php
namespace MyPlugin\Report;

use App\Report\AbstractReport;
use Cake\ORM\TableRegistry;

class MyCustomReport extends AbstractReport
{
    /**
     * Report identifier
     *
     * @var string
     */
    protected $reportId = 'my-custom-report';

    /**
     * Report name displayed to users
     *
     * @var string
     */
    protected $name = 'My Custom Report';

    /**
     * Report description
     *
     * @var string
     */
    protected $description = 'This report shows custom data from my plugin.';

    /**
     * Report category
     *
     * @var string
     */
    protected $category = 'My Plugin Reports';

    /**
     * Get report parameters configuration
     *
     * @return array
     */
    public function getParameters(): array
    {
        return [
            'startDate' => [
                'type' => 'date',
                'label' => 'Start Date',
                'required' => true,
            ],
            'endDate' => [
                'type' => 'date',
                'label' => 'End Date',
                'required' => true,
            ],
            'branchId' => [
                'type' => 'select',
                'label' => 'Branch',
                'options' => $this->getBranchOptions(),
                'empty' => '-- All Branches --',
                'required' => false,
            ],
        ];
    }

    /**
     * Execute the report
     *
     * @param array $params User-provided parameters
     * @return array Report data
     */
    public function execute(array $params): array
    {
        // Build your report query
        $query = TableRegistry::getTableLocator()
            ->get('MyPlugin.MyItems')
            ->find();
            
        // Apply parameters
        if (!empty($params['startDate'])) {
            $query->where(['created >=' => $params['startDate']]);
        }
        
        if (!empty($params['endDate'])) {
            $query->where(['created <=' => $params['endDate']]);
        }
        
        if (!empty($params['branchId'])) {
            $query->where(['branch_id' => $params['branchId']]);
        }
        
        // Get the data
        $results = $query->all();
        
        // Format for display
        $reportData = [
            'headers' => [
                'Item ID',
                'Name',
                'Description',
                'Created',
            ],
            'rows' => [],
        ];
        
        foreach ($results as $item) {
            $reportData['rows'][] = [
                $item->id,
                $item->name,
                $item->description,
                $item->created->format('Y-m-d H:i:s'),
            ];
        }
        
        return $reportData;
    }

    /**
     * Get branch options for the parameter dropdown
     *
     * @return array
     */
    protected function getBranchOptions(): array
    {
        return TableRegistry::getTableLocator()
            ->get('Branches')
            ->find('list')
            ->where(['active' => true])
            ->toArray();
    }
}
```

### Registering Reports

Register your report in your plugin's bootstrap:

```php
public function bootstrap(PluginApplicationInterface $app): void
{
    parent::bootstrap($app);
    
    // Register reports
    \App\Report\ReportRegistry::register(
        new \MyPlugin\Report\MyCustomReport()
    );
}
```

### Report Templates

Create a custom template for your report if needed:

```php
// plugins/MyPlugin/templates/Reports/my_custom_report.php
<div class="report my-custom-report">
    <h2><?= h($report->getName()) ?></h2>
    
    <div class="report-info">
        <p><?= h($report->getDescription()) ?></p>
        
        <div class="report-parameters">
            <strong>Parameters:</strong>
            <ul>
                <li>Start Date: <?= h($params['startDate']->format('Y-m-d')) ?></li>
                <li>End Date: <?= h($params['endDate']->format('Y-m-d')) ?></li>
                <?php if (!empty($params['branchId'])): ?>
                <li>Branch: <?= h($branches[$params['branchId']]) ?></li>
                <?php else: ?>
                <li>Branch: All Branches</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    
    <div class="report-results">
        <?php if (empty($data['rows'])): ?>
            <p class="empty-results">No records found matching the criteria.</p>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <?php foreach ($data['headers'] as $header): ?>
                            <th><?= h($header) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['rows'] as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?= h($cell) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
```

### Report Export

Reports automatically support CSV export. To customize export formats:

```php
/**
 * Export the report to CSV
 *
 * @param array $data Report data
 * @param array $params Report parameters
 * @return string CSV data
 */
public function exportCsv(array $data, array $params): string
{
    $output = fopen('php://temp', 'r+');
    
    // Add headers
    fputcsv($output, $data['headers']);
    
    // Add rows
    foreach ($data['rows'] as $row) {
        fputcsv($output, $row);
    }
    
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    
    return $csv;
}

/**
 * Export the report to PDF
 *
 * @param array $data Report data
 * @param array $params Report parameters
 * @return string PDF data
 */
public function exportPdf(array $data, array $params): string
{
    // PDF generation logic using TCPDF or other library
    // ...
    
    return $pdfData;
}
```
