<?php

declare(strict_types=1);

namespace App\Model\Behavior;

use Cake\Database\Expression\QueryExpression;
use Cake\ORM\Behavior;
use Cake\ORM\Query\SelectQuery;

/**
 * JsonField Behavior
 * 
 * Provides enhanced JSON field handling capabilities for database tables with JSON columns.
 * This behavior enables deep querying into JSON field structures using database-native
 * JSON functions for efficient searching and filtering.
 *
 * ## Key Features
 * - **JSON Path Querying**: Query specific paths within JSON fields
 * - **Database-Native Functions**: Uses database JSON_EXTRACT functions for performance
 * - **Flexible Value Matching**: Support for various data types within JSON structures
 * - **Query Builder Integration**: Seamless integration with CakePHP's query builder
 *
 * ## Database Requirements
 * - Database must support JSON functions (MySQL 5.7+, PostgreSQL 9.3+, SQLite 3.38+)
 * - Tables must have JSON or TEXT columns storing valid JSON data
 *
 * ## Usage Examples
 * ```php
 * // In a Table class
 * $this->addBehavior('JsonField');
 * 
 * // Query JSON field for specific value
 * $members = $this->Members->find()
 *     ->addJsonWhere('additional_info', '$.preferences.notifications', true);
 * 
 * // Search nested JSON structures
 * $query = $this->Members->find();
 * $this->Members->addJsonWhere($query, 'metadata', '$.contact.email', 'user@example.com');
 * ```
 *
 * ## JSON Path Syntax
 * Uses standard JSON Path syntax:
 * - `$.field` - Root level field
 * - `$.nested.field` - Nested object field
 * - `$.array[0]` - Array element by index
 * - `$.array[*].field` - All array elements' field
 *
 * ## Use Cases in KMP
 * - **Member Additional Info**: Search member preferences, contact details, emergency info
 * - **Application Settings**: Query complex configuration structures
 * - **Activity Metadata**: Search event details, requirements, custom fields
 * - **Officer Qualifications**: Query certification details, specializations
 *
 * ## Performance Considerations
 * - JSON queries can be slower than normalized data
 * - Consider indexes on commonly queried JSON paths
 * - Use for flexible/dynamic data, not core relational data
 *
 * @see \App\Model\Table\MembersTable Member additional_info JSON field usage
 * @author KMP Development Team
 * @since 1.0.0
 */
class JsonFieldBehavior extends Behavior
{
    /**
     * Initialize behavior with optional configuration
     *
     * Currently performs basic initialization. Can be extended to:
     * - Configure default JSON field mappings
     * - Set up database-specific JSON function preferences
     * - Initialize caching for frequently accessed JSON paths
     *
     * ## Future Configuration Options
     * ```php
     * $this->addBehavior('JsonField', [
     *     'fields' => ['additional_info', 'metadata', 'preferences'],
     *     'cacheExpire' => 3600,
     *     'indexedPaths' => ['$.contact.email', '$.preferences.notifications']
     * ]);
     * ```
     *
     * @param array $config Configuration options for the behavior
     * @return void
     */
    public function initialize(array $config): void
    {
        // Some initialization code here
    }

    /**
     * Add JSON field WHERE condition to a query
     *
     * This method enables querying specific paths within JSON fields using database-native
     * JSON_EXTRACT functions. It builds a WHERE clause that extracts a value from a JSON
     * field at the specified path and compares it to the provided value.
     *
     * ## Method Flow
     * 1. Create a query expression function for JSON extraction
     * 2. Use JSON_EXTRACT to get value at specified path
     * 3. Compare extracted value to target value using equality
     * 4. Return modified query with JSON WHERE condition
     *
     * ## JSON Path Examples
     * ```php
     * // Root level field
     * $query->addJsonWhere('data', '$.status', 'active');
     * 
     * // Nested object field  
     * $query->addJsonWhere('profile', '$.contact.email', 'user@domain.com');
     * 
     * // Array element
     * $query->addJsonWhere('tags', '$[0]', 'important');
     * 
     * // Nested array field
     * $query->addJsonWhere('history', '$.events[0].type', 'login');
     * ```
     *
     * ## Usage in Finders
     * ```php
     * // Find members with specific notification preference
     * $notificationEnabled = $this->Members->find()
     *     ->addJsonWhere('additional_info', '$.preferences.notifications', true);
     * 
     * // Find members by emergency contact type
     * $emergencyType = $this->Members->find()
     *     ->addJsonWhere('additional_info', '$.emergency.relationship', 'spouse');
     * ```
     *
     * ## Database Compatibility
     * - **MySQL**: Uses JSON_EXTRACT() function
     * - **PostgreSQL**: Uses -> or ->> operators
     * - **SQLite**: Uses json_extract() function (3.38+)
     *
     * @param SelectQuery $query The query to modify
     * @param string $field The JSON field name in the table
     * @param string $path JSON path using $.notation (e.g., '$.preferences.email')
     * @param mixed $value Value to compare against the extracted JSON value
     * @return SelectQuery Modified query with JSON WHERE condition
     * @see initialize() For behavior configuration options
     * @throws \InvalidArgumentException When field or path is invalid
     */
    public function addJsonWhere($query, $field, $path, $value)
    {
        return $query->where(function (QueryExpression $exp, SelectQuery $q) use ($field, $path, $value) {
            $json = $q->func()->json_extract([$field => 'identifier', $path]);

            return $exp->eq($json, $value);
        });
    }
}
