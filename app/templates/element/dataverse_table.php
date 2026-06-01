<?php

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
 */

use App\KMP\StaticHelpers;

$controllerName = $controllerName ?? 'grid-view';
$primaryKey = $primaryKey ?? 'id';
$currentSort = $currentSort ?? [];
$gridKey = $gridKey ?? 'grid';
$rowActions = $rowActions ?? [];
$user = $user ?? $this->request->getAttribute('identity');
$enableColumnPicker = $enableColumnPicker ?? true;
$enableBulkSelection = $enableBulkSelection ?? false;
$bulkSelectionDataFields = $bulkSelectionDataFields ?? [];
$bulkSelectionDisabledField = $bulkSelectionDisabledField ?? null;
$rowDomIdPrefix = $rowDomIdPrefix ?? null;
if ($rowDomIdPrefix === null && !empty($tableFrameId)) {
    $rowDomIdPrefix = preg_replace('/-table$/', '', (string)$tableFrameId);
}

// Show actions column if column picker is enabled OR there are row actions
$showActionsColumn = $enableColumnPicker || !empty($rowActions);

// Calculate total column count for empty state colspan
$totalColumns = count($visibleColumns) + ($showActionsColumn ? 1 : 0) + ($enableBulkSelection ? 1 : 0);
?>

<div class="table-responsive">
    <table class="table table-striped table-hover" data-<?= h($controllerName) ?>-target="gridTable">
        <thead class="table-light">
            <tr>
                <?php if ($enableBulkSelection): ?>
                    <th scope="col" style="width: 40px; text-align: center;">
                        <input type="checkbox" 
                               class="form-check-input" 
                               data-<?= h($controllerName) ?>-target="selectAllCheckbox"
                               data-action="change-><?= h($controllerName) ?>#toggleAllSelection"
                               title="Select all rows on this page">
                    </th>
                <?php endif; ?>
                <?php foreach ($visibleColumns as $columnKey): ?>
                    <?php if (!isset($columns[$columnKey])) continue; ?>
                    <?php $column = $columns[$columnKey]; ?>
                    <?php
                    $isSorted = isset($currentSort['field']) && $currentSort['field'] === $columnKey;
                    $sortDirection = $isSorted ? $currentSort['direction'] : null;
                    $sortable = $column['sortable'] ?? true;
                    ?>
                    <th scope="col"
                        class="<?= $sortable ? 'sortable-header' : '' ?> <?= $isSorted ? 'sorted-' . $sortDirection : '' ?>"
                        style="<?= !empty($column['width']) ? 'width: ' . h($column['width']) . ';' : '' ?> 
                               text-align: <?= h($column['alignment'] ?? 'left') ?>;"
                        <?php if ($sortable): ?>
                        data-action="click-><?= h($controllerName) ?>#applySort"
                        data-column-key="<?= h($columnKey) ?>"
                        style="cursor: pointer;"
                        <?php endif; ?>>
                        <?= h($column['label']) ?>
                        <?php if ($sortable): ?>
                            <span class="sort-indicator ms-1">
                                <?php if ($isSorted): ?>
                                    <?php if ($sortDirection === 'asc'): ?>
                                        <i class="bi bi-caret-up-fill"></i>
                                    <?php else: ?>
                                        <i class="bi bi-caret-down-fill"></i>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <i class="bi bi-caret-down text-muted"></i>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </th>
                <?php endforeach; ?>
                <?php if ($showActionsColumn): ?>
                    <th scope="col" class="text-end" style="width: 70px;">
                        <?php if ($enableColumnPicker): ?>
                            <button type="button"
                                class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="modal"
                                data-bs-target="#columnPickerModal-<?= h($gridKey) ?>">
                                <i class="bi bi-list-columns"></i>
                            </button>
                        <?php endif; ?>
                    </th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data)): ?>
                <tr>
                    <td colspan="<?= $totalColumns ?>" class="text-center text-muted py-4">
                        No records found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($data as $row): ?>
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
        cursor: pointer;
        user-select: none;
    }

    .sortable-header:hover {
        background-color: rgba(0, 0, 0, 0.05);
    }

    .sorted-asc,
    .sorted-desc {
        background-color: rgba(13, 110, 253, 0.1);
    }

    .sort-indicator {
        font-size: 0.875rem;
    }
</style>