<?php
declare(strict_types=1);

/**
 * Dataverse Table Element
 *
 * Renders a data table with sortable headers, custom cell renderers,
 * row actions, and integration with the grid-view Stimulus controller.
 *
 * @var \App\View\AppView $this
 * @var array $columns Column metadata from GridColumns class
 * @var array $visibleColumns Currently visible column keys
 * @var array $data The result set to display
 * @var array $currentSort Current sort configuration ['field' => 'column_key', 'direction' => 'asc|desc']
 * @var string $controllerName The Stimulus controller identifier
 * @var string $primaryKey The primary key field for row identification (default: 'id')
 * @var string|null $gridKey Grid key for component uniqueness (optional)
 * @var array $rowActions Row action configurations (optional)
 * @var \Authorization\Identity|null $user Current user for permission checks (optional)
 * @var bool $enableBulkSelection Whether to show row selection checkboxes (optional)
 * @var array $bulkSelection Bulk selection label configuration (optional)
 * @var string|null $bulkSelectionDisabledLabel Disabled row checkbox title (optional)
 * @var bool $bulkSelectionHideDisabledControl Hide disabled row checkboxes while preserving cells (optional)
 * @var string|null $tableLabel Accessible name for the table and its scroll region (optional)
 */

$controllerName = $controllerName ?? 'grid-view';
$primaryKey = $primaryKey ?? 'id';
$currentSort = $currentSort ?? [];
$gridKey = $gridKey ?? 'grid';
$rowActions = $rowActions ?? [];
$tableLabel = $tableLabel ?? __('Table results');
$user = $user ?? $this->request->getAttribute('identity');
$enableColumnPicker = $enableColumnPicker ?? true;
$enableBulkSelection = $enableBulkSelection ?? false;
$bulkSelectionDataFields = $bulkSelectionDataFields ?? [];
$bulkSelectionDisabledField = $bulkSelectionDisabledField ?? null;
$bulkSelectionDisabledLabel = $bulkSelectionDisabledLabel ?? null;
$bulkSelectionHideDisabledControl = $bulkSelectionHideDisabledControl ?? false;
$bulkSelection = $bulkSelection ?? [];
$selectAllBulkSelectionLabel = $bulkSelection['selectAllLabel'] ?? __('Select all rows on this page');
$rowBulkSelectionLabelTemplate = $bulkSelection['rowLabelTemplate'] ?? null;
$rowDomIdPrefix = $rowDomIdPrefix ?? null;
if ($rowDomIdPrefix === null && !empty($tableFrameId)) {
    $rowDomIdPrefix = preg_replace('/-table$/', '', (string)$tableFrameId);
}
$dataRows = is_array($data) ? $data : (is_iterable($data) ? iterator_to_array($data, false) : []);

$getRowValue = function ($row, string $path) use (&$getRowValue, $columns) {
    if (isset($columns[$path]['renderField']) && $columns[$path]['renderField'] !== $path) {
        return $getRowValue($row, $columns[$path]['renderField']);
    }

    if (str_contains($path, '.')) {
        $value = $row;
        foreach (explode('.', $path) as $part) {
            $value = $getRowValue($value, $part);
            if ($value === null) {
                return null;
            }
        }

        return $value;
    }

    if (is_array($row) && array_key_exists($path, $row)) {
        return $row[$path];
    }

    if ($row instanceof ArrayAccess && isset($row[$path])) {
        return $row[$path];
    }

    if (is_object($row)) {
        if (method_exists($row, 'has') && method_exists($row, 'get') && $row->has($path)) {
            return $row->get($path);
        }

        if (isset($row->{$path})) {
            return $row->{$path};
        }
    }

    return null;
};

$formatBulkSelectionLabel = function (string $template, $row) use ($getRowValue): string {
    return preg_replace_callback('/\{([A-Za-z0-9_.-]+)\}/', function (array $matches) use ($row, $getRowValue): string {
        $value = $getRowValue($row, $matches[1]);

        if (is_bool($value)) {
            return $value ? __('Yes') : __('No');
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        if ($value instanceof Stringable) {
            return (string)$value;
        }

        return '';
    }, $template);
};

// Show actions column if column picker is enabled OR there are row actions
$showActionsColumn = $enableColumnPicker || !empty($rowActions);

// Calculate total column count for empty state colspan
$totalColumns = max(1, count($visibleColumns) + ($showActionsColumn ? 1 : 0) + ($enableBulkSelection ? 1 : 0));
?>

<div class="table-responsive" role="region" tabindex="0"
    aria-label="<?= h(__('{0}. Scroll horizontally to compare columns.', $tableLabel)) ?>">
    <table class="table table-striped table-hover" data-<?= h($controllerName) ?>-target="gridTable">
        <caption class="visually-hidden"><?= h($tableLabel) ?></caption>
        <thead class="table-light text-nowrap">
            <tr>
                <?php if ($enableBulkSelection) : ?>
                    <th scope="col" style="width: 40px; text-align: center;">
                        <input type="checkbox" 
                               class="form-check-input" 
                               data-<?= h($controllerName) ?>-target="selectAllCheckbox"
                               data-action="change-><?= h($controllerName) ?>#toggleAllSelection"
                               aria-label="<?= h($selectAllBulkSelectionLabel) ?>">
                    </th>
                <?php endif; ?>
                <?php foreach ($visibleColumns as $columnKey) : ?>
                    <?php if (!isset($columns[$columnKey])) {
                        continue;
                    } ?>
                    <?php $column = $columns[$columnKey]; ?>
                    <?php
                    $isSorted = isset($currentSort['field']) && $currentSort['field'] === $columnKey;
                    $sortDirection = $isSorted ? $currentSort['direction'] : null;
                    $sortable = $column['sortable'] ?? true;
                    $thClasses = trim(
                        ($sortable ? 'sortable-header' : '')
                        . ' '
                        . ($isSorted ? 'sorted-' . $sortDirection : ''),
                    );
                    ?>
                    <th scope="col"
                        class="<?= h($thClasses) ?>"
                        style="<?= !empty($column['width']) ? 'width: ' . h($column['width']) . ';' : '' ?> 
                               text-align: <?= h($column['alignment'] ?? 'left') ?>;"
                        <?php if ($sortable) : ?>
                        data-column-key="<?= h($columnKey) ?>"
                        aria-sort="<?= $isSorted ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' ?>"
                        <?php endif; ?>>
                        <?php if ($sortable) : ?>
                            <button type="button"
                                class="btn btn-link p-0 text-body text-decoration-none dataverse-sort-button"
                                data-action="click-><?= h($controllerName) ?>#applySort"
                                data-column-key="<?= h($columnKey) ?>"
                                aria-label="<?= h(__('Sort by {0}', $column['label'])) ?>">
                                <?= h($column['label']) ?>
                            <span class="sort-indicator ms-1">
                                <?php if ($isSorted) : ?>
                                    <?php if ($sortDirection === 'asc') : ?>
                                        <kmp-icon name="caret-up"  aria-hidden="true"></kmp-icon>
                                    <?php else : ?>
                                        <kmp-icon name="caret-down"  aria-hidden="true"></kmp-icon>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <kmp-icon name="caret-up-down" class="text-muted" aria-hidden="true"></kmp-icon>
                                <?php endif; ?>
                            </span>
                            </button>
                        <?php else : ?>
                            <?= h($column['label']) ?>
                        <?php endif; ?>
                    </th>
                <?php endforeach; ?>
                <?php if ($showActionsColumn) : ?>
                    <th scope="col" class="text-end" style="width: 70px;">
                        <?php if ($enableColumnPicker) : ?>
                            <button type="button"
                                class="btn btn-sm btn-outline-secondary"
                                aria-label="<?= h(__('Select columns')) ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#columnPickerModal-<?= h($gridKey) ?>">
                                <kmp-icon name="columns"  aria-hidden="true"></kmp-icon>
                            </button>
                        <?php endif; ?>
                    </th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($dataRows)) : ?>
                <tr>
                    <td colspan="<?= $totalColumns ?>" class="text-center text-muted py-4">
                        <div role="status" aria-live="polite">
                            <?= __('No records found.') ?>
                        </div>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($dataRows as $row) : ?>
                    <?php
                    $rowId = is_array($row) ? ($row[$primaryKey] ?? null) : ($row->{$primaryKey} ?? null);
                    $bulkSelectionLabel = $rowBulkSelectionLabelTemplate
                        ? $formatBulkSelectionLabel($rowBulkSelectionLabelTemplate, $row)
                        : __('Select row {0}', $rowId);
                    ?>
                    <?= $this->element('dataverse_table_row', [
                        'row' => $row,
                        'columns' => $columns,
                        'visibleColumns' => $visibleColumns,
                        'controllerName' => $controllerName,
                        'primaryKey' => $primaryKey,
                        'gridKey' => $gridKey,
                        'rowActions' => $rowActions,
                        'user' => $user,
                        'enableBulkSelection' => $enableBulkSelection,
                        'bulkSelectionDataFields' => $bulkSelectionDataFields,
                        'bulkSelectionDisabledField' => $bulkSelectionDisabledField,
                        'bulkSelectionDisabledLabel' => $bulkSelectionDisabledLabel,
                        'bulkSelectionHideDisabledControl' => $bulkSelectionHideDisabledControl,
                        'bulkSelectionLabel' => $bulkSelectionLabel,
                        'rowDomIdPrefix' => $rowDomIdPrefix,
                        'showActionsColumn' => $showActionsColumn,
                    ]) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
    .sortable-header {
        user-select: none;
    }

    .dataverse-sort-button {
        display: inline-flex;
        align-items: center;
        font: inherit;
        text-align: inherit;
        white-space: nowrap;
    }

    .sortable-header:hover {
        background-color: rgba(0, 0, 0, 0.05);
    }

    .sorted-asc,
    .sorted-desc {
        background-color: rgba(13, 110, 253, 0.1);
    }

    .sort-indicator {
        flex-shrink: 0;
        font-size: 0.875rem;
    }
</style>
