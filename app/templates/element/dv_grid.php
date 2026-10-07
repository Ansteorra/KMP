<?php

/**
 * Dataverse Grid Element
 *
 * Unified grid component with lazy-loading architecture.
 *
 * Usage:
 * <?= $this->element('dv_grid', [
 *     'gridKey' => 'Members.index.main',
 *     'frameId' => 'members-grid',
 *     'dataUrl' => $this->Url->build(['action' => 'gridData']),
 * ]) ?>
 *
 * @var \App\View\AppView $this
 * @var string $gridKey Unique identifier for this grid (e.g., 'Members.index.main')
 * @var string $frameId Turbo frame ID (e.g., 'members-grid')
 * @var string $dataUrl URL to load grid data from
 */

// Restore marked grid state only to its originating frame; keep sibling contexts independent.
$queryParams = $this->getRequest()->getQueryParams();
$restoresGridContext = ($queryParams['grid_context'] ?? null) === $frameId;
if (array_key_exists('grid_context', $queryParams)) {
    $queryParams = $restoresGridContext ? $queryParams : [];
    unset($queryParams['grid_context']);
}
$dataUrlWithParams = $dataUrl;
if (!empty($queryParams)) {
    $separator = strpos($dataUrl, '?') === false ? '?' : '&';
    $dataUrlWithParams .= $separator . http_build_query($queryParams);
}
$syncUrl = $syncUrl ?? (!$restoresGridContext && $this->getRequest()->getParam('action') !== 'view');
?>

<!-- Grid View Container with Stimulus Controller -->
<div data-controller="grid-view page-context"
    data-grid-view-sync-url-value="<?= $syncUrl ? 'true' : 'false' ?>">

    <!-- Lazy-Loading Turbo Frame -->
    <!-- The frame loads the complete grid (toolbar + table) from the server -->
    <!-- Server returns grid state in a script tag, which controller reads -->
    <turbo-frame id="<?= h($frameId) ?>" src="<?= h($dataUrlWithParams) ?>">
        <!-- Loading state -->
        <div class="text-center p-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading grid...</p>
        </div>
    </turbo-frame>
</div>
