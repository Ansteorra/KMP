<?php
declare(strict_types=1);

use App\Services\UiPatternGridService;

/** Shared Dataverse surface in a collection or an embedded record section. */
$gridKey = $gridState['config']['gridKey'];
$gridState['config']['showSaveViewLabel'] = true;
$query = $this->getRequest()->getQueryParams();
$embedded = $gridKey === UiPatternGridService::BRANCH_GRID_KEY;
$sticky = $embedded
    ? ['pattern' => 'record', 'record' => 'branch', 'section' => 'record-branch-gatherings-tab']
    : ['pattern' => 'collection'];
$query = array_merge($query, $sticky);
$dataUrl = $this->Url->build(
    ['controller' => 'UiPatterns', 'action' => 'gridData', '?' => $query],
    ['escape' => false],
);
?>
<section class="ui-dataverse" aria-label="<?= __('Gathering collection') ?>"
    data-controller="grid-view page-context ui-pattern-grid"
    data-grid-view-sticky-query-value="pattern,state,record,section,layout"
    data-grid-view-sticky-default-value="<?= h(json_encode($sticky)) ?>"
    data-grid-view-sync-url-value="true">
    <details class="small text-muted mb-3 ui-grid-help">
        <summary><?= __('About saved views') ?></summary>
        <p class="mt-2 mb-0">
            <?= __('Saved views belong to this showcase only.') ?>
            <?= __('They remember filters, search, sorting, columns, and page size.') ?>
        </p>
    </details>
    <form class="ui-grid-search mb-3" data-action="submit->ui-pattern-grid#search" role="search">
        <label class="form-label" for="showcase-grid-search"><?= __('Search gatherings') ?></label>
        <div class="input-group">
            <input class="form-control" type="search" id="showcase-grid-search" name="search"
                placeholder="<?= __('Name, branch, or activity') ?>" data-ui-pattern-grid-target="search">
            <button class="btn btn-outline-primary" type="submit">
                <?= $this->Icon->render('search') ?> <?= __('Search') ?>
            </button>
        </div>
    </form>
    <?= $this->element('grid_view_toolbar', ['gridState' => $gridState]) ?>
    <div class="ui-grid-view-status mb-3" role="status" aria-live="polite">
        <span data-ui-pattern-grid-target="viewStatus"></span>
        <button class="btn btn-link btn-sm" type="button" data-ui-pattern-grid-target="restore"
            data-action="ui-pattern-grid#restoreView" hidden><?= __('Restore saved view') ?></button>
    </div>
    <details class="ui-display-options" open data-ui-pattern-grid-target="displayOptions">
        <summary><?= __('Display & selection') ?></summary>
        <div class="ui-toolbar">
            <div class="ui-actions">
                <div class="btn-group" role="group" aria-label="<?= __('Result display') ?>">
                    <?php foreach (['cards' => __('Cards'), 'table' => __('Table')] as $layout => $label) : ?>
                        <button class="btn btn-outline-secondary" type="button" data-layout="<?= h($layout) ?>"
                            data-action="ui-pattern-grid#layout" data-ui-pattern-grid-target="layoutButton"
                            aria-pressed="false"><?= h($label) ?></button>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal"
                    data-bs-target="#columnPickerModal-<?= h($gridKey) ?>">
                    <?= $this->Icon->render('columns') ?> <?= __('Columns') ?>
                </button>
                <button class="btn btn-outline-secondary" type="button" data-action="ui-pattern-grid#selectPage">
                    <?= __('Select page') ?>
                </button>
            </div>
        </div>
    </details>
    <div class="ui-bulk-bar ui-actions mb-3" data-ui-pattern-grid-target="selectionBar" hidden>
        <span data-ui-pattern-grid-target="selectionCount"></span>
        <button class="btn btn-outline-secondary" type="button" data-action="grid-view#clearBulkSelection">
            <?= __('Clear selection') ?>
        </button>
        <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal"
            data-bs-target="#showcase-grid-selection"><?= __('Review selected gatherings') ?></button>
    </div>
    <turbo-frame id="ui-patterns-grid-table" data-grid-src="<?= h($dataUrl) ?>">
        <?= $this->element('uiPatterns/grid_results') ?>
    </turbo-frame>
    <div class="modal fade" id="showcase-grid-selection" tabindex="-1"
        aria-labelledby="showcase-grid-selection-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="showcase-grid-selection-title"><?= __('Selected gatherings') ?></h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal"
                    aria-label="<?= __('Close') ?>"></button>
            </div>
            <div class="modal-body">
                <p><?= __('Selection applies to this page and clears when the results change.') ?></p>
                <ul data-ui-pattern-grid-target="selectedNames"></ul>
                <p class="small text-muted">
                    <?= __('Export CSV downloads all matching rows, regardless of selection.') ?>
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" type="button" data-bs-dismiss="modal"><?= __('Done') ?></button>
            </div>
        </div></div>
    </div>
</section>
