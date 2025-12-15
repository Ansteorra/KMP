<?php

/**
 * Dataverse Grid Core Content (Toolbarless)
 *
 * Minimal Turbo-frame payload used by custom layouts. Contains only the
 * state script and the table frame, leaving surrounding layout to the host view.
 *
 * @var \App\View\AppView $this
 * @var iterable $data
 * @var array $gridState
 * @var string $frameId
 * @var string|null $tableDataUrl
 */

if (!isset($tableDataUrl)) {
    $request = $this->getRequest();
    $queryParams = $request->getQueryParams();
    $tableDataUrl = $dataUrl ?? $request->getRequestTarget();

    if (!empty($queryParams) && strpos($tableDataUrl, '?') === false) {
        $tableDataUrl .= '?' . http_build_query($queryParams);
    }
}

$tableFrameId = ($frameId ?? 'grid') . '-table';
$renderInlineTable = isset($data) && isset($gridState);
$rowActions = $rowActions ?? [];
$customElement = $customElement ?? null;
$customElementOptions = $customElementOptions ?? [];
?>
<turbo-frame id="<?= h($frameId) ?>">
    <turbo-frame
        id="<?= h($tableFrameId) ?>"
        <?= $renderInlineTable ? '' : 'src="' . h($tableDataUrl) . '"' ?>
        data-grid-src="<?= h($tableDataUrl) ?>">
        <?php if ($renderInlineTable): ?>
            <script type="application/json" id="<?= h($tableFrameId) ?>-state">
                <?= json_encode($gridState, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?>
            </script>

            <?php if ($customElement): ?>
                <?= $this->element($customElement, array_merge($customElementOptions, [
                    'data' => $data,
                    'gridState' => $gridState,
                    'tableFrameId' => $tableFrameId,
                ])) ?>
            <?php else: ?>
                <?= $this->element('dataverse_table', [
                    'columns' => $gridState['columns']['all'],
                    'visibleColumns' => $gridState['columns']['visible'],
                    'data' => $data,
                    'currentSort' => $gridState['sort'],
                    'controllerName' => 'grid-view',
                    'primaryKey' => $gridState['config']['primaryKey'],
                    'gridKey' => $gridState['config']['gridKey'],
                    'rowActions' => $rowActions,
                    'enableColumnPicker' => $gridState['config']['enableColumnPicker'] ?? true,
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
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center p-3">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        <?php endif; ?>
    </turbo-frame>
</turbo-frame>