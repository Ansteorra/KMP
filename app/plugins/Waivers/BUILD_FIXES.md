# TemplatePlugin.php Build Issues - Fixed

## Issues Identified and Resolved

### Issue 1: Missing `getMigrationOrder()` Method
**Error**: `'Template\TemplatePlugin' does not implement method 'getMigrationOrder'`

**Cause**: The `KMPPluginInterface` requires all implementing classes to provide a `getMigrationOrder()` method to control plugin initialization order.

**Fix Applied**:
```php
/**
 * Plugin migration order for KMP plugin system
 *
 * @var int Migration order priority for database setup
 */
protected int $_migrationOrder = 0;

/**
 * Get Migration Order
 *
 * Returns the migration order priority for this plugin, which determines
 * the sequence in which plugin migrations are executed during system setup.
 * The Template plugin uses default order (0) as it has no special dependencies.
 *
 * @return int Migration order priority
 */
public function getMigrationOrder(): int
{
    return $this->_migrationOrder;
}
```

### Issue 2: Missing Constructor
**Cause**: The plugin needs a constructor to accept and store the migration order from `config/plugins.php`.

**Fix Applied**:
```php
/**
 * Constructor
 *
 * Initialize the plugin with migration configuration from plugins.php.
 *
 * @param array $config Plugin configuration including migrationOrder
 */
public function __construct($config = [])
{
    if (!isset($config['migrationOrder'])) {
        $config['migrationOrder'] = 0;
    }
    $this->_migrationOrder = $config['migrationOrder'];
}
```

### Issue 3: Incorrect `ViewCellRegistry::register()` Signature
**Error**: `Expected type 'array'. Found 'Closure(): array'.`

**Cause**: The `ViewCellRegistry::register()` method expects three parameters:
1. Plugin name (string)
2. Static cells (array)
3. Dynamic cells callback (Closure)

**Wrong Code**:
```php
ViewCellRegistry::register('template', function () {
    return [
        // cells...
    ];
});
```

**Fix Applied**:
```php
ViewCellRegistry::register(
    'template',
    [], // Static cells (none for Template)
    function ($urlParams, $user) {
        // Dynamic view cells can be added here based on context
        return [
            // Example: Add a dashboard cell
            // [
            //     'cell' => 'Template.HelloWorld',
            //     'position' => 'main',
            //     'order' => 100,
            // ],
        ];
    }
);
```

## Pattern Followed

The fixes follow the exact pattern used by other KMP plugins (Awards, Activities, Officers):

### 1. Migration Order System
- Property: `protected int $_migrationOrder = 0;`
- Method: `public function getMigrationOrder(): int`
- Constructor: Accepts `$config['migrationOrder']` from plugin configuration

### 2. View Cell Registration
Three-parameter format matching the service signature:
```php
ViewCellRegistry::register(
    string $source,           // Plugin identifier
    array $staticCells,       // Static cell definitions
    Closure $dynamicCallback  // Dynamic cell generation function
);
```

## Configuration Integration

The plugin can now be properly configured in `config/plugins.php`:

```php
'Template' => [
    'migrationOrder' => 10,  // Custom migration order if needed
],
```

If no migration order is specified, it defaults to 0.

## Migration Order Guidelines

From KMPPluginInterface documentation:

- **1-5**: Critical system plugins (Activities, Awards, Officers, Reports, etc.)
- **10+**: Utility plugins (Queue, GitHubIssueSubmitter, Bootstrap, etc.)
- **0**: Default for plugins with no special dependencies

The Template plugin uses order 0 (default) as it:
- Has no database dependencies on other plugins
- Doesn't require special initialization ordering
- Can be initialized at any time

## Verification

After fixes:
- ✅ All compiler errors resolved
- ✅ Implements `KMPPluginInterface` correctly
- ✅ Follows KMP plugin patterns
- ✅ Matches Awards/Activities plugin structure
- ✅ Ready for production use

## Summary

All three build issues in TemplatePlugin.php have been resolved:
1. ✅ Added required `getMigrationOrder()` method
2. ✅ Added constructor for migration order configuration
3. ✅ Fixed `ViewCellRegistry::register()` call signature

The plugin now fully complies with the `KMPPluginInterface` contract and follows established KMP plugin patterns.
