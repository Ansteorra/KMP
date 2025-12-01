<?php

/**
 * Dataverse Grid Table Data (Loaded via Inner Turbo Frame)
 * 
 * This template contains only the table and pagination.
 * It gets reloaded when filters, sorting, or pagination changes.
 * 
 * @var \App\View\AppView $this
 * @var iterable $data The data to display (e.g., $members, $warrants)
 * @var array $gridState Complete grid state object
 * @var string $tableFrameId Table turbo frame ID (e.g., 'members-grid-table')
 * @var array $rowActions Row action configurations (optional)
 */

$rowActions = $rowActions ?? [];
?>
<turbo-frame id="<?= h($tableFrameId) ?>">
    <!-- Grid State (JSON) - Read by grid-view-controller on load -->
    <!-- State is here because it changes with filters/sort/pagination -->
    <script type="application/json" id="<?= h($tableFrameId) ?>-state">
        <?= json_encode($gridState, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?>
    </script>

    <!-- Dataverse Table -->
    <?= $this->element('dataverse_table', [
        'columns' => $gridState['columns']['all'],
        'visibleColumns' => $gridState['columns']['visible'],
        'data' => $data,
        'currentSort' => $gridState['sort'],
        'controllerName' => 'grid-view',
        'primaryKey' => $gridState['config']['primaryKey'],
        'gridKey' => $gridState['config']['gridKey'],
        'rowActions' => $rowActions,
    ]) ?>

    <!-- Pagination -->
    <div class="paginator">
        <ul class="pagination">
            <?= $this->Paginator->first('<< ' . __('first')) ?>
            <?= $this->Paginator->prev('< ' . __('previous')) ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next(__('next') . ' >') ?>
            <?= $this->Paginator->last(__('last') . ' >>') ?>
        </ul>
        <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?></p>
    </div>
</turbo-frame>