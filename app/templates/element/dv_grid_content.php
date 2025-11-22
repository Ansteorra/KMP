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
 */

// Build URL for table data with current query params
$queryParams = $this->getRequest()->getQueryParams();
$tableDataUrl = $this->Url->build(['action' => 'gridData']);
if (!empty($queryParams)) {
    $tableDataUrl .= '?' . http_build_query($queryParams);
}
?>
<turbo-frame id="<?= h($frameId) ?>">
    <!-- Grid Toolbar (Static - doesn't reload with filters) -->
    <?= $this->element('grid_view_toolbar', [
        'gridState' => $gridState,
        'controllerName' => 'grid-view',
    ]) ?>

    <!-- Table Data Frame (Dynamic - reloads with filters/sort/pagination) -->
    <turbo-frame id="<?= h($frameId) ?>-table" src="<?= h($tableDataUrl) ?>">
        <!-- Loading state -->
        <div class="text-center p-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </turbo-frame>
</turbo-frame>