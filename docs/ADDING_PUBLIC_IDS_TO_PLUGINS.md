# Adding Public IDs to Plugin Tables

This guide explains how to add public ID support to your plugin's tables.

## Quick Start

### 1. Create Migration in Your Plugin

Create a new migration file in your plugin:

```
plugins/YourPlugin/config/Migrations/YYYYMMDDHHMMSS_AddPublicIdToYourPluginTables.php
```

### 2. Use This Template

```php
<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddPublicIdToYourPluginTables extends AbstractMigration
{
    /**
     * Tables in this plugin that need public IDs
     */
    protected const TABLES = [
        'your_table_1',
        'your_table_2',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (!$this->hasTable($tableName)) {
                $this->io()->warning(sprintf('Table %s does not exist, skipping', $tableName));
                continue;
            }

            $table = $this->table($tableName);
            
            if ($table->hasColumn('public_id')) {
                $this->io()->warning(sprintf('Table %s already has public_id column, skipping', $tableName));
                continue;
            }

            $table->addColumn('public_id', 'string', [
                'limit' => 8,
                'null' => true,
                'default' => null,
                'after' => 'id',
                'comment' => 'Non-sequential public identifier safe for client exposure',
            ]);
            
            $table->addIndex(['public_id'], [
                'unique' => true,
                'name' => sprintf('idx_%s_public_id', $tableName),
            ]);
            
            $table->update();
            
            $this->io()->success(sprintf('Added public_id to %s', $tableName));
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (!$this->hasTable($tableName)) {
                continue;
            }

            $table = $this->table($tableName);
            
            if (!$table->hasColumn('public_id')) {
                continue;
            }

            $table->removeIndexByName(sprintf('idx_%s_public_id', $tableName));
            $table->removeColumn('public_id');
            $table->update();
            
            $this->io()->success(sprintf('Removed public_id from %s', $tableName));
        }
    }
}
```

### 3. Run Migration

```bash
# Run plugin migrations
bin/cake migrations migrate -p YourPlugin
```

### 4. Generate Public IDs

```bash
# Generate public IDs for your plugin tables
bin/cake generate_public_ids your_table_1 your_table_2
```

### 5. Add Behavior to Tables

In your plugin's Table classes:

```php
// plugins/YourPlugin/src/Model/Table/YourTableTable.php

class YourTableTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        
        // Add PublicId behavior
        $this->addBehavior('PublicId');
    }
}
```

### 6. Update Controllers

Change controllers to use public_id:

```php
// Before
public function view($id = null)
{
    $record = $this->YourTable->get($id);
}

// After
public function view($publicId = null)
{
    $record = $this->YourTable->getByPublicId($publicId);
}
```

### 7. Update Templates

Change links to use public_id:

```php
// Before
<?= $this->Html->link('View', ['action' => 'view', $record->id]) ?>

// After
<?= $this->Html->link('View', ['action' => 'view', $record->public_id]) ?>
```

## Which Tables Need Public IDs?

Add public IDs to tables that:
- ✅ Have records viewable by end users
- ✅ Have URLs with IDs (e.g., `/awards/view/123`)
- ✅ Are used in autocomplete or AJAX calls
- ✅ Have client-side JavaScript that references records

Skip tables that:
- ❌ Are pure join tables (many-to-many)
- ❌ Are never referenced from client-side
- ❌ Are internal-only (e.g., settings, cache)
- ❌ Are log/audit tables

## Example: Awards Plugin

```php
protected const TABLES = [
    'awards',          // ✅ Has view page, used in autocomplete
    'recommendations', // ✅ Has view page, used in forms
];

// These would NOT need public IDs:
// 'awards_members'   // ❌ Join table only
// 'award_settings'   // ❌ Internal configuration
```

## Example: Activities Plugin

```php
protected const TABLES = [
    'activities',      // ✅ Has view page
    'activity_types',  // ✅ Used in dropdowns/autocomplete
];
```

## Testing

After adding public IDs:

1. **Verify column exists:**
   ```sql
   DESC your_table;
   -- Should show public_id column
   ```

2. **Verify index exists:**
   ```sql
   SHOW INDEXES FROM your_table;
   -- Should show idx_your_table_public_id
   ```

3. **Verify generation:**
   ```sql
   SELECT id, public_id FROM your_table LIMIT 5;
   -- Should show 8-character alphanumeric IDs
   ```

4. **Test controller:**
   ```
   Visit: /your-plugin/your-controller/view/a7fK9mP2
   Should work if public_id = 'a7fK9mP2'
   ```

## Migration Order

If your plugin depends on core tables with public IDs, ensure migration order in `config/plugins.php`:

```php
return [
    'YourPlugin' => [
        'migrationOrder' => 2, // After core (1)
    ],
];
```

## Plugin-Specific Notes

### Awards Plugin
- Tables: `awards`, `recommendations`
- Migration: `plugins/Awards/config/Migrations/20251103140000_AddPublicIdToAwardsTables.php`
- Command: `bin/cake generate_public_ids awards recommendations`

### Activities Plugin
- Tables: `activities`, `activity_types`
- Migration: Create in `plugins/Activities/config/Migrations/`
- Command: `bin/cake generate_public_ids activities activity_types`

### Authorization Plugin (if applicable)
- Tables: `authorizations`
- Migration: Create in `plugins/Authorizations/config/Migrations/`
- Command: `bin/cake generate_public_ids authorizations`

## Common Issues

### Issue: Migration fails with "Table not found"
**Solution:** Ensure table exists by running plugin's table creation migrations first

### Issue: "Column already exists"
**Solution:** Migration is idempotent and will skip, this is safe

### Issue: Foreign key constraints
**Solution:** Public IDs don't affect foreign keys - those still use internal `id`

### Issue: Existing code breaks
**Solution:** Add public IDs gradually:
1. Add column and generate IDs (no breaking change)
2. Update one controller at a time
3. Keep both `id` and `public_id` working during transition

## Summary

1. Create migration in your plugin
2. Run migration to add column
3. Generate public IDs for existing records
4. Add PublicIdBehavior to Table classes
5. Update controllers to use `getByPublicId()`
6. Update templates to use `public_id` in links
7. Update JavaScript/AJAX to use public_id

For more details, see: `docs/PUBLIC_ID_SYSTEM.md`
