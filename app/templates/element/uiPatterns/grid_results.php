<?php
declare(strict_types=1);

use App\Services\UiPatternGridService;

/** Shared inline/frame results for the showcase's real Dataverse controls. */
$frameId = 'ui-patterns-grid-table';
$page = $gridPagination['page'];
$pageCount = $gridPagination['pageCount'];
$query = $this->getRequest()->getQueryParams();
$query['pattern'] = $gridState['config']['gridKey'] === UiPatternGridService::BRANCH_GRID_KEY
    ? 'record' : 'collection';
?>
<script type="application/json" id="<?= h($frameId) ?>-state">
    <?= json_encode($gridState, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
</script>
<?php if ($gridError) : ?>
    <div class="alert alert-danger" role="alert">
        <h2 class="h5"><?= __('Gatherings could not be loaded') ?></h2>
        <p><?= __('Your view and filters are still available. Try loading the results again.') ?></p>
        <a class="btn btn-outline-danger" data-turbo-frame="<?= h($frameId) ?>"
            href="<?= h($this->Url->build([
                'controller' => 'UiPatterns', 'action' => 'gridData',
                '?' => array_merge($query, ['state' => 'normal']),
            ], ['escape' => false])) ?>"><?= __('Try again') ?></a>
    </div>
<?php else : ?>
    <div class="ui-grid-results">
        <?= $this->element('dataverse_table', [
            'columns' => $columns,
            'visibleColumns' => $gridState['columns']['visible'],
            'data' => $gridData,
            'currentSort' => $gridState['sort'],
            'gridKey' => $gridState['config']['gridKey'],
            'tableFrameId' => $frameId,
            'tableLabel' => __('Gathering results'),
            'enableColumnPicker' => true,
            'enableBulkSelection' => true,
            'bulkSelection' => ['rowLabelTemplate' => __('Select {name}')],
        ]) ?>
    </div>
    <div class="paginator">
        <nav aria-label="<?= __('Gathering result pages') ?>">
            <ul class="pagination flex-wrap">
                <?php for ($number = 1; $number <= $pageCount; $number++) : ?>
                    <li class="page-item<?= $number === $page ? ' active' : '' ?>">
                        <?php if ($number === $page) : ?>
                            <span class="page-link" aria-current="page"><?= $number ?></span>
                        <?php else : ?>
                            <a class="page-link" href="<?= h($this->Url->build([
                                'controller' => 'UiPatterns', 'action' => 'index',
                                '?' => array_merge($query, ['page' => $number]),
                            ], ['escape' => false])) ?>"
                                aria-label="<?= h(__('Page {0}', $number)) ?>"><?= $number ?></a>
                        <?php endif; ?>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <p class="small text-muted" data-grid-result-summary>
            <?= h(__(
                'Showing {0}–{1} of {2} gatherings.',
                $gridPagination['start'],
                $gridPagination['end'],
                $gridPagination['total'],
            )) ?>
            <?= __('Export CSV includes all matching rows and the visible columns in their current order.') ?>
        </p>
    </div>
<?php endif; ?>
