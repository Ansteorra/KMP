# Dataverse Grid Field Naming Convention

## Overview

This document describes the semantic separation between `queryField` and `renderField` in Dataverse grid column metadata. This separation was introduced to prevent confusion between SQL query operations and entity property access.

## The Problem (Historical Context)

Previously, the grid system used two fields with ambiguous names:
- `sortField` - Used for SQL queries but name implied only sorting
- `relationField` - Used for entity property access but had unclear purpose

This led to a critical bug where `relationField` (containing lowercase entity names like `branch.name`) was used in SQL SELECT statements, which caused errors because SQL requires capitalized table aliases (like `Branches.name`).

## The Solution: Semantic Field Names

### `queryField`

**Purpose:** Used for SQL operations (SELECT, WHERE, ORDER BY)  
**Format:** Capitalized table aliases following CakePHP conventions  
**Example:** `'Branches.name'`, `'Members.sca_name'`, `'Parents.sca_name'`

**Used by:**
- CSV export SELECT clauses
- Sorting ORDER BY clauses  
- Search WHERE conditions (after transformation)

### `renderField`

**Purpose:** Used to access entity properties for display in UI  
**Format:** Lowercase association names following CakePHP entity conventions  
**Example:** `'branch.name'`, `'member.sca_name'`, `'parent.sca_name'`

**Used by:**
- Template rendering in `dataverse_table.php`
- Accessing entity relationships via dot notation
- Display value extraction from result entities

## Column Metadata Structure

### For Relation Type Columns

```php
'branch_id' => [
    'key' => 'branch_id',
    'label' => 'Branch',
    'type' => 'relation',
    'sortable' => true,
    'defaultVisible' => true,
    
    // For SQL queries - must use capitalized table alias
    'queryField' => 'Branches.name',
    
    // For entity property access - uses lowercase association name
    'renderField' => 'branch.name',
],
```

### For Regular Columns

Regular columns (non-relation types) don't need these fields:

```php
'sca_name' => [
    'key' => 'sca_name',
    'label' => 'SCA Name',
    'type' => 'string',
    'sortable' => true,
    'defaultVisible' => true,
    // No queryField or renderField needed - uses 'key' directly
],
```

## CakePHP Naming Conventions

Understanding CakePHP's conventions is essential for using these fields correctly:

### Table Names (Plural, Capitalized)
- `Members` - SQL table name
- `Branches` - SQL table name
- `Parents` - Alias for self-referential Members relationship

### Association Names (Singular, Lowercase)
- `member` - Entity property (e.g., `$warrant->member`)
- `branch` - Entity property (e.g., `$member->branch`)
- `parent` - Entity property (e.g., `$member->parent`)

### In SQL Context (queryField)
```sql
SELECT Members.sca_name, Branches.name, Parents.sca_name
FROM members
LEFT JOIN branches ON members.branch_id = branches.id
LEFT JOIN members AS Parents ON members.parent_id = Parents.id
```

### In Entity Context (renderField)
```php
// Template rendering
echo $member->branch->name;        // Uses lowercase 'branch'
echo $warrant->member->sca_name;   // Uses lowercase 'member'
echo $member->parent->sca_name;    // Uses lowercase 'parent'
```

## Implementation Examples

### Members Grid - Branch Column

```php
'branch_id' => [
    'key' => 'branch_id',
    'label' => 'Branch',
    'type' => 'relation',
    'renderField' => 'branch.name',      // Entity: $member->branch->name
    'queryField' => 'Branches.name',     // SQL: Branches.name
],
```

**Controller must load association:**
```php
'baseQuery' => $this->Members->find()->contain(['Branches']),
```

### Warrants Grid - Member Column

```php
'member_id' => [
    'key' => 'member_id',
    'label' => 'Member',
    'type' => 'relation',
    'renderField' => 'member.sca_name',  // Entity: $warrant->member->sca_name
    'queryField' => 'Members.sca_name',  // SQL: Members.sca_name
],
```

**Controller must load association:**
```php
'baseQuery' => $this->Warrants->find()->contain(['Members']),
```

## Code Usage Patterns

### In DataverseGridTrait - CSV Export

```php
// Line 1016-1020: CSV export field selection
if ($columnMeta['type'] === 'relation' && !empty($columnMeta['queryField'])) {
    // Use queryField for SQL SELECT (has correct table alias)
    $alias = $columnKey . '_display';
    $selectFields[$alias] = $columnMeta['queryField'];  // Uses Branches.name
    $fieldMapping[$alias] = $headerLabel;
}
```

### In DataverseGridTrait - Sorting

```php
// Line 457-458: Applying sort to query
if ($columnMeta && isset($columnMeta['queryField'])) {
    $actualSortField = $columnMeta['queryField'];  // Uses Branches.name
}
$baseQuery->orderBy([$actualSortField => strtoupper($sortDirection)]);
```

### In Template - Rendering

```php
// templates/element/dataverse_table.php line 119
if (!empty($column['renderField'])) {
    // Parse dot notation for nested fields (e.g., 'branch.name')
    $relationParts = explode('.', $column['renderField']);
    $relationValue = $row;
    foreach ($relationParts as $part) {
        if (is_object($relationValue) && isset($relationValue->{$part})) {
            $relationValue = $relationValue->{$part};  // Uses entity properties
        }
    }
    echo $relationValue ? h($relationValue) : '<span class="text-muted">—</span>';
}
```

## Troubleshooting

### Error: "Unknown column 'branch.name' in SELECT"

**Problem:** Using `renderField` in SQL context  
**Solution:** Use `queryField` for SQL operations

```php
// ❌ Wrong - uses entity name in SQL
$query->select(['branch.name']);

// ✅ Correct - uses table alias in SQL
$query->select(['Branches.name']);
```

### Error: "Trying to access property 'name' on null"

**Problem:** Association not loaded in base query  
**Solution:** Add `->contain()` to base query

```php
// ❌ Wrong - association not loaded
'baseQuery' => $this->Members->find(),

// ✅ Correct - loads Branches association
'baseQuery' => $this->Members->find()->contain(['Branches']),
```

### Value Shows ID Instead of Name

**Problem:** Missing or incorrect `renderField`  
**Solution:** Verify `renderField` uses lowercase association name

```php
// ❌ Wrong - uses capitalized table name
'renderField' => 'Branches.name',

// ✅ Correct - uses lowercase association name
'renderField' => 'branch.name',
```

## Migration from Old Field Names

If you're updating existing grid columns from the old naming convention:

### Old Convention (Deprecated)
```php
'branch_id' => [
    'relationField' => 'Branches.name',  // Was used for both purposes
    'sortField' => 'Branches.name',      // Ambiguous name
],
```

### New Convention
```php
'branch_id' => [
    'renderField' => 'branch.name',   // Clear: for entity property access
    'queryField' => 'Branches.name',  // Clear: for SQL queries
],
```

## Best Practices

1. **Always use lowercase for renderField**: Match entity association names
2. **Always use capitalized for queryField**: Match CakePHP table aliases
3. **Load associations in baseQuery**: Use `->contain()` for all relations
4. **Test both rendering and CSV export**: Ensures both field types work correctly
5. **Document complex relationships**: Add comments for non-obvious associations

## Summary

The `queryField` / `renderField` separation creates a clear semantic distinction:

- **`queryField`** = "What SQL needs" (capitalized table aliases)
- **`renderField`** = "What entities provide" (lowercase association names)

This prevents bugs where the wrong field type is used in the wrong context and makes the code self-documenting about its intent.
