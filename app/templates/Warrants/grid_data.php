<?php

/**
 * Warrants Grid Data - Inner Turbo-Frame
 *
 * Returns the inner turbo-frame with table data.
 * Reloaded on all grid state changes (view, filter, search, sort, pagination).
 *
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface|\App\Model\Entity\Warrant[] $warrants
 * @var array $gridState
 */
?>
<turbo-frame id="warrants-grid-table" data-grid-view-target="tableFrame">
    <!-- Complete Grid State - Single Source of Truth -->
    <script type="application/json" data-grid-state>
        <?= json_encode($gridState, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?>
    </script>

    <?= $this->element('dataverse_table', [
        'columns' => $gridState['columns']['all'],
        'visibleColumns' => $gridState['columns']['visible'],
        'data' => $warrants,
        'currentSort' => $gridState['sort'],
        'controllerName' => 'grid-view',
        'primaryKey' => $gridState['config']['primaryKey'],
        'gridKey' => $gridState['config']['gridKey'],
    ]) ?>

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