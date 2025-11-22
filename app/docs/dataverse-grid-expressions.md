# Dataverse Grid Expression System

## Overview

The Dataverse Grid system supports complex filtering through a powerful expression tree format that can handle nested OR/AND logic, similar to FetchXML or CakePHP QueryExpressions. This eliminates the need for hardcoded query callbacks in most scenarios.

## Expression Format

### Basic Structure

An expression is a nested tree structure that can contain:
- **Simple conditions**: Field comparisons using operators
- **OR groups**: Multiple conditions where ANY can match
- **AND groups**: Multiple conditions where ALL must match
- **Nested groups**: OR/AND groups can be nested to any depth

### JSON Schema

```json
{
  "expression": {
    "type": "OR|AND",
    "conditions": [
      {
        "field": "fieldName",
        "operator": "eq|neq|gt|gte|lt|lte|in|notIn|contains|startsWith|endsWith|isNull|isNotNull|dateRange",
        "value": "value or array"
      },
      {
        "type": "AND|OR",
        "conditions": [...]
      }
    ]
  }
}
```

## Supported Operators

| Operator | Description | Value Type | Example |
|----------|-------------|------------|---------|
| `eq` | Equals | scalar | `{"field": "status", "operator": "eq", "value": "Current"}` |
| `neq` | Not equals | scalar | `{"field": "status", "operator": "neq", "value": "Expired"}` |
| `gt` | Greater than | scalar | `{"field": "age", "operator": "gt", "value": 18}` |
| `gte` | Greater than or equal | scalar | `{"field": "expires_on", "operator": "gte", "value": "2025-11-22"}` |
| `lt` | Less than | scalar | `{"field": "expires_on", "operator": "lt", "value": "2025-11-22"}` |
| `lte` | Less than or equal | scalar | `{"field": "created", "operator": "lte", "value": "2025-01-01"}` |
| `in` | Value in array | array | `{"field": "status", "operator": "in", "value": ["Active", "Current"]}` |
| `notIn` | Value not in array | array | `{"field": "status", "operator": "notIn", "value": ["Deleted"]}` |
| `contains` | String contains (LIKE %value%) | string | `{"field": "name", "operator": "contains", "value": "John"}` |
| `startsWith` | String starts with (LIKE value%) | string | `{"field": "name", "operator": "startsWith", "value": "Sir"}` |
| `endsWith` | String ends with (LIKE %value) | string | `{"field": "name", "operator": "endsWith", "value": "Jr"}` |
| `isNull` | Field is NULL | none | `{"field": "deleted_at", "operator": "isNull"}` |
| `isNotNull` | Field is not NULL | none | `{"field": "email", "operator": "isNotNull"}` |
| `dateRange` | Date between range | [start, end] | `{"field": "created", "operator": "dateRange", "value": ["2025-01-01", "2025-12-31"]}` |

## Examples

### Example 1: Simple OR Condition

Show warrants that are either expired OR have a deactivated/expired status:

```php
'config' => [
    'expression' => [
        'type' => 'OR',
        'conditions' => [
            ['field' => 'expires_on', 'operator' => 'lt', 'value' => '2025-11-22'],
            ['field' => 'status', 'operator' => 'in', 'value' => ['Deactivated', 'Expired']],
        ],
    ],
],
```

**Generated SQL:**
```sql
WHERE (Warrants.expires_on < '2025-11-22' OR Warrants.status IN ('Deactivated', 'Expired'))
```

### Example 2: Nested AND within OR

Show warrants that are either:
- Expired (date < today), OR
- Current status AND starting in the future

```php
'config' => [
    'expression' => [
        'type' => 'OR',
        'conditions' => [
            ['field' => 'expires_on', 'operator' => 'lt', 'value' => '2025-11-22'],
            [
                'type' => 'AND',
                'conditions' => [
                    ['field' => 'status', 'operator' => 'eq', 'value' => 'Current'],
                    ['field' => 'start_on', 'operator' => 'gte', 'value' => '2025-11-23'],
                ],
            ],
        ],
    ],
],
```

**Generated SQL:**
```sql
WHERE (
    Warrants.expires_on < '2025-11-22'
    OR (Warrants.status = 'Current' AND Warrants.start_on >= '2025-11-23')
)
```

### Example 3: Complex Multi-Level Nesting

Show members that match complex criteria:

```php
'config' => [
    'expression' => [
        'type' => 'AND',
        'conditions' => [
            ['field' => 'membership_expires_on', 'operator' => 'gte', 'value' => '2025-11-22'],
            [
                'type' => 'OR',
                'conditions' => [
                    ['field' => 'branch_id', 'operator' => 'in', 'value' => [1, 2, 3]],
                    [
                        'type' => 'AND',
                        'conditions' => [
                            ['field' => 'parent_id', 'operator' => 'isNotNull'],
                            ['field' => 'status', 'operator' => 'eq', 'value' => 'Active'],
                        ],
                    ],
                ],
            ],
        ],
    ],
],
```

**Generated SQL:**
```sql
WHERE (
    Members.membership_expires_on >= '2025-11-22'
    AND (
        Members.branch_id IN (1, 2, 3)
        OR (Members.parent_id IS NOT NULL AND Members.status = 'Active')
    )
)
```

### Example 4: Date Range with OR

Show events that are either:
- Happening this month, OR
- Marked as featured

```php
'config' => [
    'expression' => [
        'type' => 'OR',
        'conditions' => [
            ['field' => 'start_date', 'operator' => 'dateRange', 'value' => ['2025-11-01', '2025-11-30']],
            ['field' => 'featured', 'operator' => 'eq', 'value' => true],
        ],
    ],
],
```

**Generated SQL:**
```sql
WHERE (
    (Gatherings.start_date >= '2025-11-01' AND Gatherings.start_date <= '2025-11-30')
    OR Gatherings.featured = 1
)
```

## Usage in System Views

### Warrants Controller Example

The Warrants controller demonstrates expression usage in the "Previous" system view:

```php
protected function getWarrantSystemViews(): array
{
    $today = FrozenDate::today();
    $todayString = $today->format('Y-m-d');
    
    return [
        'sys-warrants-previous' => [
            'id' => 'sys-warrants-previous',
            'name' => __('Previous'),
            'description' => __('Expired or deactivated warrants'),
            'canManage' => false,
            'config' => [
                // Expression tree handles OR logic declaratively
                'expression' => [
                    'type' => 'OR',
                    'conditions' => [
                        ['field' => 'expires_on', 'operator' => 'lt', 'value' => $todayString],
                        ['field' => 'status', 'operator' => 'in', 'value' => [
                            Warrant::DEACTIVATED_STATUS,
                            Warrant::EXPIRED_STATUS,
                        ]],
                    ],
                ],
                // Keep filter UI seeds for display pills (but don't apply to query)
                'filters' => [
                    ['field' => 'status', 'operator' => 'in', 'value' => [
                        Warrant::DEACTIVATED_STATUS,
                        Warrant::EXPIRED_STATUS,
                    ]],
                    ['field' => 'expires_on', 'operator' => 'dateRange', 'value' => [null, $yesterdayString]],
                ],
                'skipFilterColumns' => ['status', 'expires_on'],
            ],
        ],
    ];
}
```

### Key Points

1. **Expression Tree**: The `expression` key contains the declarative OR/AND logic
2. **Filter Seeds**: The `filters` array still exists to populate the UI filter pills
3. **Skip Columns**: Use `skipFilterColumns` to prevent filter UI from applying to query (since expression handles it)
4. **No Callback Needed**: The query callback no longer needs to inject OR logic

## DataverseGridTrait Processing

The trait automatically processes expressions when present:

```php
// In DataverseGridTrait::processDataverseGrid()

// Apply expression tree from system view (if present and not dirty)
if ($selectedSystemView && !$dirtyFilters && !empty($selectedSystemView['config']['expression'])) {
    $config = new GridViewConfig();
    $expression = $config->extractExpression(
        $selectedSystemView['config'],
        $baseQuery->clause('where') ?? $baseQuery->newExpr(),
        $tableName
    );

    if ($expression !== null) {
        $baseQuery->where($expression);
    }
}
```

## GridViewConfig Methods

### extractExpression()

Builds a CakePHP QueryExpression from the expression tree:

```php
$expression = GridViewConfig::extractExpression(
    $config,                  // View config with 'expression' key
    $queryExpression,         // Base QueryExpression from query
    'TableName'              // Table name for field qualification
);

if ($expression !== null) {
    $query->where($expression);
}
```

### buildExpression() (Protected)

Recursively processes expression nodes:
- Detects OR/AND groups vs. leaf conditions
- Builds nested QueryExpression trees
- Properly qualifies field names with table name

### buildLeafCondition() (Protected)

Converts individual conditions to CakePHP format:
- Maps operator to CakePHP SQL syntax
- Handles special cases (dateRange, isNull, IN clauses)
- Qualifies field names

## Migration from Query Callbacks

### Before (Query Callback)

```php
protected function buildSystemViewQueryCallback(FrozenDate $today): callable
{
    return function ($query, $selectedSystemView) use ($today) {
        $query = $this->addConditions($query);

        if (is_array($selectedSystemView) && ($selectedSystemView['id'] ?? null) === 'sys-warrants-previous') {
            $query->where(function ($exp) use ($today) {
                return $exp->add([
                    'OR' => [
                        'Warrants.expires_on <' => $today,
                        'Warrants.status IN' => [Warrant::DEACTIVATED_STATUS, Warrant::EXPIRED_STATUS],
                    ],
                ]);
            });
        }

        return $query;
    };
}
```

### After (Expression Tree)

```php
'config' => [
    'expression' => [
        'type' => 'OR',
        'conditions' => [
            ['field' => 'expires_on', 'operator' => 'lt', 'value' => $todayString],
            ['field' => 'status', 'operator' => 'in', 'value' => [
                Warrant::DEACTIVATED_STATUS,
                Warrant::EXPIRED_STATUS,
            ]],
        ],
    ],
],
```

### Query Callback Still Available

Keep the query callback for:
- Base field selection and optimization (`addConditions()`)
- Exceptional edge cases that can't be expressed declaratively
- Future views with custom query manipulation needs

```php
protected function buildSystemViewQueryCallback(FrozenDate $today): callable
{
    return function ($query, $selectedSystemView) use ($today) {
        // Always apply base conditions (field selection, associations)
        $query = $this->addConditions($query);

        // Add exceptional custom logic here if needed
        
        return $query;
    };
}
```

## Best Practices

1. **Prefer Expressions**: Use expression trees for all OR/AND logic instead of query callbacks
2. **Keep UI Seeds**: Maintain `filters` array for UI pill display, use `skipFilterColumns` to prevent query application
3. **Field Qualification**: Expression system auto-qualifies fields with table name
4. **Date Handling**: Use ISO format strings (YYYY-MM-DD) for date comparisons
5. **Operator Consistency**: Use the same operator names as the flat filter system
6. **Validation**: Expression trees are not validated at runtime - test thoroughly
7. **Documentation**: Comment complex nested expressions to explain business logic

## Performance Considerations

- Expression trees generate optimized SQL with proper parentheses
- CakePHP's QueryExpression handles parameter binding automatically
- Complex nested expressions may impact query planning - test with EXPLAIN
- Consider indexes on fields used in OR conditions for better performance

## Testing

Use Playwright MCP for end-to-end testing:

```javascript
// Navigate to view with expression
await page.goto('http://localhost:8080/warrants/index-dv?view_id=sys-warrants-previous');

// Verify results
await expect(page.locator('table tbody tr')).toHaveCount(14);

// Check console for errors
const errors = await page.evaluate(() => {
    return console.errors || [];
});
expect(errors).toHaveLength(0);
```

## Troubleshooting

### Expression Not Applied

**Symptom**: Query doesn't use expression logic

**Causes**:
- `dirtyFilters` flag is true (user modified filters)
- Expression syntax error (missing `type` or `conditions`)
- Expression on saved view instead of system view

**Solution**: Check dirty flags, validate JSON structure, ensure expression is in system view config

### Incorrect SQL Generated

**Symptom**: Query results don't match expected logic

**Causes**:
- Incorrect operator usage
- Missing field qualification
- Nested groups at wrong level

**Solution**: Enable SQL logging, verify generated WHERE clause, check expression nesting

### UI Pills Don't Match Query

**Symptom**: Filter pills show but don't affect results

**Causes**:
- Missing `skipFilterColumns` configuration
- Expression and filters array out of sync

**Solution**: Add fields to `skipFilterColumns`, ensure filters array matches expression for UI display

## Future Enhancements

Potential improvements to the expression system:

1. **Runtime Validation**: Add schema validation for expression trees
2. **UI Builder**: Visual expression builder in frontend
3. **Expression Variables**: Support dynamic values like `{today}`, `{currentUser}`
4. **Sub-Queries**: Support EXISTS and IN (SELECT...) sub-queries
4. **Aggregation**: Support HAVING clauses with aggregate functions
5. **Custom Functions**: Support database functions (CONCAT, DATE_FORMAT, etc.)
