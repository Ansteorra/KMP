<?php

/**
 * Warrants Dataverse Grid - Outer Frame
 *
 * Main page rendering outer turbo-frame with toolbar.
 * Inner turbo-frame with table data loaded via gridData() action.
 *
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface|\App\Model\Entity\Warrant[] $warrants
 * @var array $gridState
 */

$this->assign('title', __('Warrants'));
?>

<div class="warrants index content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><?= __('Warrants - Dataverse Grid') ?></h3>
    </div>

    <!-- Outer turbo-frame: contains toolbar and loads inner frame -->
    <turbo-frame id="warrants-grid" data-controller="grid-view"
        data-grid-view-grid-state-value="<?= h(json_encode($gridState)) ?>">

        <?= $this->element('grid_view_toolbar', [
            'gridState' => $gridState,
        ]) ?>

        <!-- Inner turbo-frame loaded from gridData action -->
        <?php
        $queryParams = $this->request->getQueryParams();
        $gridDataUrl = $this->Url->build(['action' => 'gridData', '?' => $queryParams]);
        ?>
        <turbo-frame id="warrants-grid-table" src="<?= $gridDataUrl ?>" data-grid-view-target="tableFrame">

            <div class="text-center p-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading...</p>
            </div>
        </turbo-frame>
    </turbo-frame>
</div>