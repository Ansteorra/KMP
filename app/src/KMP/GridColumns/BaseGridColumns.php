<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

/**
 * Base class for grid column metadata definitions.
 *
 * Provides common helper methods for querying column metadata.
 * Grid-specific classes extend this and implement getColumns().
 *
 * @see /docs/9.3-dataverse-grid-complete-guide.md For field naming and grid configuration
 */
abstract class BaseGridColumns
implements SystemViewsProviderInterface
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
     * Return system views for a grid.
     *
     * Override in child classes to provide system-defined dv_grid views.
     *
     * @param array<string, mixed> $options Runtime context (timezone, scope, etc.)
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array
    {
        return [];
    }

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
     * Returns array of column metadata for columns that have dropdown filter type or other UI-based filters
     * (e.g., 'dropdown', 'is-populated')
     *
     * @return array<string, array<string, mixed>> Column metadata indexed by key
     */
    public static function getDropdownFilterColumns(): array
    {
        $dropdown = [];
        foreach (static::getColumns() as $key => $column) {
            if (!empty($column['filterable'])) {
                $filterType = $column['filterType'] ?? '';
                // Include dropdown and is-populated filter types
                if ($filterType === 'dropdown' || $filterType === 'is-populated') {
                    $dropdown[$key] = $column;
                }
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
