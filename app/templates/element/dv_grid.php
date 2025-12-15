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

// Preserve query string parameters for the data URL
$queryParams = $this->getRequest()->getQueryParams();
$dataUrlWithParams = $dataUrl;
if (!empty($queryParams)) {
    $separator = strpos($dataUrl, '?') === false ? '?' : '&';
    $dataUrlWithParams .= $separator . http_build_query($queryParams);
}
?>

<!-- Grid View Container with Stimulus Controller -->
<div data-controller="grid-view">

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