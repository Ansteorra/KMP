<?php
declare(strict_types=1);

namespace App\KMP;

/**
 * Resolved column context for building Dataverse grid queries.
 *
 * The grid-visible columns are used for UI state. Query-visible columns include
 * hidden-but-active dependencies such as sort, filter, and search columns.
 */
class DataverseGridQueryContext
{
    /**
     * @param array<int,string> $gridVisibleColumns
     * @param array<int,string>|null $queryVisibleColumns Null means load all display data.
     * @param array<string,array<string,mixed>> $columnsMetadata
     */
    public function __construct(
        private readonly array $gridVisibleColumns,
        private readonly ?array $queryVisibleColumns,
        private readonly array $columnsMetadata,
    ) {
    }

    /**
     * Columns that should be displayed in the grid UI.
     *
     * @return array<int,string>
     */
    public function gridVisibleColumns(): array
    {
        return $this->gridVisibleColumns;
    }

    /**
     * Columns whose data dependencies should be loaded by query builders.
     *
     * @return array<int,string>|null Null means load all display data.
     */
    public function queryVisibleColumns(): ?array
    {
        return $this->queryVisibleColumns;
    }

    /**
     * Determine whether a column's display data should be loaded.
     */
    public function loadsColumn(string $columnKey): bool
    {
        return $this->queryVisibleColumns === null || in_array($columnKey, $this->queryVisibleColumns, true);
    }

    /**
     * Determine whether any of the given columns should be loaded.
     *
     * @param array<int,string> $columnKeys
     */
    public function loadsAny(array $columnKeys): bool
    {
        if ($this->queryVisibleColumns === null) {
            return true;
        }

        return array_intersect($columnKeys, $this->queryVisibleColumns) !== [];
    }

    /**
     * Determine whether export/all-display mode is active.
     */
    public function selectsAllDisplayData(): bool
    {
        return $this->queryVisibleColumns === null;
    }

    /**
     * Column metadata after request-specific filtering.
     *
     * @return array<string,array<string,mixed>>
     */
    public function columnsMetadata(): array
    {
        return $this->columnsMetadata;
    }

    /**
     * Associations declared by active columns as required display data.
     *
     * @return array<int,string>
     */
    public function requiredContains(): array
    {
        return $this->metadataValuesForActiveColumns('requiresContain');
    }

    /**
     * Fields declared by active columns as required display data.
     *
     * @return array<int,string>
     */
    public function requiredFields(): array
    {
        return $this->metadataValuesForActiveColumns('requiresFields');
    }

    /**
     * Computed enrichment steps declared by active columns.
     *
     * @return array<int,string>
     */
    public function requiredComputed(): array
    {
        return $this->metadataValuesForActiveColumns('requiresComputed');
    }

    /**
     * Determine whether an association is required by active columns.
     */
    public function requiresContain(string $association): bool
    {
        return $this->selectsAllDisplayData() || in_array($association, $this->requiredContains(), true);
    }

    /**
     * Determine whether a computed enrichment step is required by active columns.
     */
    public function requiresComputed(string $computedKey): bool
    {
        return $this->selectsAllDisplayData() || in_array($computedKey, $this->requiredComputed(), true);
    }

    /**
     * @return array<int,string>
     */
    private function activeColumnKeys(): array
    {
        return $this->queryVisibleColumns ?? array_keys($this->columnsMetadata);
    }

    /**
     * @return array<int,string>
     */
    private function metadataValuesForActiveColumns(string $metadataKey): array
    {
        $values = [];
        foreach ($this->activeColumnKeys() as $columnKey) {
            $columnValues = $this->columnsMetadata[$columnKey][$metadataKey] ?? [];
            if (is_string($columnValues)) {
                $values[] = $columnValues;
            } elseif (is_array($columnValues)) {
                $values = array_merge($values, array_filter($columnValues, 'is_string'));
            }
        }

        return array_values(array_unique($values));
    }
}
