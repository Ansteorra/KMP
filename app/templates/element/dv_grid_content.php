<?php

/**
 * Dataverse Grid Content (Loaded via Turbo Frame)
 * 
 * This template is loaded inside a turbo-frame and contains:
 * - Grid toolbar (static - doesn't change with filters)
 * - Table frame (dynamic - updates with filters/sort/pagination)
 * 
 * @var \App\View\AppView $this
 * @var iterable $data The data to display (e.g., $members, $warrants)
 * @var array $gridState Complete grid state object
 * @var string $frameId Turbo frame ID (e.g., 'members-grid')
 * @var string|null $tableDataUrl Optional table data URL (defaults to building from current action)
 */

// Build URL for table data with current query params if not provided
// Prefer explicitly provided $tableDataUrl, then a controller-supplied $dataUrl,
// otherwise fall back to the current request target so custom grid actions work.
if (!isset($tableDataUrl)) {
    $request = $this->getRequest();
    $queryParams = $request->getQueryParams();

    // If a specific data endpoint was passed in, use it; otherwise reuse the current action
    $tableDataUrl = $dataUrl ?? $request->getRequestTarget();

    // Append query params when the URL does not already include them
    if (!empty($queryParams) && strpos($tableDataUrl, '?') === false) {
        $tableDataUrl .= '?' . http_build_query($queryParams);
    }
}

$tableFrameId = ($frameId ?? 'grid') . '-table';
$renderInlineTable = isset($data) && isset($gridState);
$rowActions = $rowActions ?? [];
?>
<turbo-frame id="<?= h($frameId) ?>">
    <!-- Grid Toolbar (Static - doesn't reload with filters) -->
    <?= $this->element('grid_view_toolbar', [
        'gridState' => $gridState,
        'controllerName' => 'grid-view',
    ]) ?>

    <!-- Table Data Frame (Dynamic - reloads with filters/sort/pagination) -->
    <turbo-frame
        id="<?= h($tableFrameId) ?>"
        <?= $renderInlineTable ? '' : 'src="' . h($tableDataUrl) . '"' ?>
        data-grid-src="<?= h($tableDataUrl) ?>">
        <?php if ($renderInlineTable): ?>
            <!-- Grid State (JSON) - Read by grid-view-controller on load -->
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
                'enableColumnPicker' => $gridState['config']['enableColumnPicker'] ?? true,
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
        <?php else: ?>
            <!-- Loading state -->
            <div class="text-center p-3">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        <?php endif; ?>
    </turbo-frame>
</turbo-frame>