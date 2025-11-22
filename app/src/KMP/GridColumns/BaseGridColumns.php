<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

/**
 * Base Grid Columns Class
 *
 * Provides common helper methods for grid column metadata classes.
 * Grid-specific column classes should extend this class and implement
 * the getColumns() method to define their column metadata.
 *
 * ## Usage
 *
 * ```php
 * class MyGridColumns extends BaseGridColumns
 * {
 *     public static function getColumns(): array
 *     {
 *         return [
 *             'id' => [
 *                 'key' => 'id',
 *                 'label' => 'ID',
 *                 'type' => 'number',
 *                 'sortable' => true,
 *                 'defaultVisible' => true,
 *                 'exportable' => true,  // Optional: false to exclude from CSV export
 *             ],
 *             // ... more columns
 *         ];
 *     }
 * }
 * ```
 *
 * ## Column Configuration
 *
 * ### Required Properties
 * - `key` (string): Unique column identifier
 * - `label` (string): Display label for column header
 * - `type` (string): Column type (string, number, date, boolean, relation, actions, etc.)
 *
 * ### Optional Properties
 * - `sortable` (bool): Whether column can be sorted (default: true)
 * - `filterable` (bool): Whether column can be filtered (default: false)
 * - `searchable` (bool): Whether column is included in text search (default: false)
 * - `defaultVisible` (bool): Whether column is visible by default (default: false)
 * - `exportable` (bool): Whether column is included in CSV export (default: true)
 * - `required` (bool): Whether column cannot be hidden (default: false)
 * - `width` (string): CSS width value (e.g., '100px', '20%')
 * - `alignment` (string): Text alignment (left, center, right)
 *
 * ### Special Properties
 * - `queryField` (string): For SQL queries - uses capitalized table aliases (e.g., 'Members.sca_name', 'Branches.name')
 * - `renderField` (string): For entity property access - uses lowercase association names (e.g., 'member.sca_name', 'branch.name')
 * - `filterType` (string): Type of filter UI (dropdown, date-range)
 * - `filterOptions` (array): Options for dropdown filters
 *
 * ### Field Naming Convention
 * - **queryField**: Used in SQL SELECT, WHERE, and ORDER BY clauses. Must match CakePHP table aliases (capitalized).
 * - **renderField**: Used to access entity properties for display. Uses entity association names (lowercase).
 */
abstract class BaseGridColumns
{
    /**
     * Get all available columns for the grid
     *
     * Must be implemented by child classes to return column metadata
     *
     * @return array<string, array<string, mixed>>
     */
    abstract public static function getColumns(): array;

    /**
     * Get column by key
     *
     * @param string $key Column key
     * @return array<string, mixed>|null
     */
    public static function getColumn(string $key): ?array
    {
        $columns = static::getColumns();
        return $columns[$key] ?? null;
    }

    /**
     * Get only columns that are visible by default
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getDefaultVisibleColumns(): array
    {
        return array_filter(
            static::getColumns(),
            fn($column) => !empty($column['defaultVisible'])
        );
    }

    /**
     * Get column keys as array
     *
     * @return array<string>
     */
    public static function getColumnKeys(): array
    {
        return array_keys(static::getColumns());
    }

    /**
     * Validate that column keys exist
     *
     * @param array<string> $keys Column keys to validate
     * @return array<string> Invalid keys
     */
    public static function validateColumnKeys(array $keys): array
    {
        $validKeys = static::getColumnKeys();
        return array_diff($keys, $validKeys);
    }

    /**
     * Get searchable columns
     *
     * Returns array of column keys that should be included in text search
     *
     * @return array<string> Searchable column keys
     */
    public static function getSearchableColumns(): array
    {
        $searchable = [];
        foreach (static::getColumns() as $key => $column) {
            if (!empty($column['searchable'])) {
                $searchable[] = $key;
            }
        }
        return $searchable;
    }

    /**
     * Get columns with dropdown filters
     *
     * Returns array of column metadata for columns that have dropdown filter type
     *
     * @return array<string, array<string, mixed>> Column metadata indexed by key
     */
    public static function getDropdownFilterColumns(): array
    {
        $dropdown = [];
        foreach (static::getColumns() as $key => $column) {
            if (!empty($column['filterable']) && ($column['filterType'] ?? '') === 'dropdown') {
                $dropdown[$key] = $column;
            }
        }
        return $dropdown;
    }

    /**
     * Get all filterable columns
     *
     * Returns array of column keys that can be filtered
     *
     * @return array<string> Filterable column keys
     */
    public static function getFilterableColumns(): array
    {
        $filterable = [];
        foreach (static::getColumns() as $key => $column) {
            if (!empty($column['filterable'])) {
                $filterable[] = $key;
            }
        }
        return $filterable;
    }

    /**
     * Get required columns
     *
     * Returns array of column keys that are required and cannot be hidden
     *
     * @return array<string> Required column keys
     */
    public static function getRequiredColumns(): array
    {
        $required = [];
        foreach (static::getColumns() as $key => $column) {
            if (!empty($column['required'])) {
                $required[] = $key;
            }
        }
        return $required;
    }

    /**
     * Get sortable columns
     *
     * Returns array of column keys that can be sorted
     *
     * @return array<string> Sortable column keys
     */
    public static function getSortableColumns(): array
    {
        $sortable = [];
        foreach (static::getColumns() as $key => $column) {
            if (!empty($column['sortable'])) {
                $sortable[] = $key;
            }
        }
        return $sortable;
    }
}
