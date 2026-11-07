<?php
declare(strict_types=1);

/** @var \App\View\AppView $this */
/** @var string $showcaseState */

$collectionUrl = $this->Url->build([
    'controller' => 'UiPatterns', 'action' => 'index', '?' => ['pattern' => 'collection'],
]);
$taskUrl = $this->Url->build(['controller' => 'UiPatterns', 'action' => 'index', '?' => ['pattern' => 'task']]);
$readerUrl = $this->Url->build(['controller' => 'UiPatterns', 'action' => 'index', '?' => ['pattern' => 'reader']]);
$hasWork = $showcaseState !== 'empty';
$canReview = $showcaseState !== 'restricted';
$headerActions = [['label' => __('All gatherings'), 'icon' => 'table', 'href' => $collectionUrl]];
if ($canReview) {
    $headerActions[] = [
        'label' => __('Submit waivers'), 'icon' => 'file-upload', 'href' => $taskUrl, 'primary' => true,
    ];
}
$exceptions = [
    [
        'gathering' => __('Oakmere Winter Court'), 'branch' => __('Barony of Oakmere'), 'branchKey' => 'oakmere',
        'status' => __('Missing submission'), 'tone' => 'warning',
        'detail' => __('Adult roster waiver has not been submitted.'),
        'action' => __('Submit waiver'), 'url' => $taskUrl, 'recordUrl' => $readerUrl,
    ],
    [
        'gathering' => __('Willowbank Archery Practice'), 'branch' => __('Shire of Willowbank'),
        'branchKey' => 'willowbank',
        'status' => __('Lost waiver'), 'tone' => 'danger',
        'detail' => __('A loss report is recorded. Follow-up is still required.'),
        'action' => __('Review loss report'), 'url' => null, 'recordUrl' => null,
    ],
    [
        'gathering' => __('Oakmere Arts Evening'), 'branch' => __('Barony of Oakmere'), 'branchKey' => 'oakmere',
        'status' => __('Ready to close'), 'tone' => 'success',
        'detail' => __('Submissions and exemptions are reviewed; closure is the remaining step.'),
        'action' => __('Review for closure'), 'url' => null, 'recordUrl' => null,
    ],
];
?>
<?= $this->element('uiPatterns/pageHeader', [
    'eyebrow' => __('Waivers'),
    'title' => __('Waiver overview'),
    'description' => __('Resolve outstanding collection work and review gatherings ready for closure.'),
    'actions' => $headerActions,
]) ?>

<div class="ui-toolbar">
    <div class="ui-control-group">
        <label class="form-label" for="dashboard-scope"><?= __('Branch scope') ?></label>
        <select class="form-select" id="dashboard-scope" data-action="change->ui-patterns#filterDashboard"
            data-ui-patterns-target="dashboardScope">
            <option value="all"><?= __('My permitted branches') ?></option>
            <option value="oakmere"><?= __('Barony of Oakmere') ?></option>
            <option value="willowbank"><?= __('Shire of Willowbank') ?></option>
        </select>
    </div>
    <p class="text-muted mb-0 ms-md-auto">
        <?= $showcaseState === 'error' ? __('Last available update · 15 minutes ago') : __('Updated just now') ?>
    </p>
</div>

<?php if ($showcaseState === 'error') : ?>
    <div class="alert alert-danger" role="alert">
        <strong><?= __('Current waiver totals could not be loaded.') ?></strong>
        <?= __('These are the last available figures. Refresh before making a closure decision.') ?>
        <button type="button" class="btn btn-outline-danger ms-md-2" data-action="ui-patterns#notice"
            data-message="<?= __('Refresh requested in this preview. No live records were queried.') ?>">
            <?= $this->Icon->render('refresh') ?> <?= __('Refresh') ?>
        </button>
    </div>
<?php endif; ?>

<div class="ui-metrics" aria-label="<?= __('Gathering waiver status') ?>">
    <a class="ui-surface ui-metric" href="#dashboard-attention">
        <span class="ui-field-label"><?= __('Need follow-up') ?></span>
        <strong data-ui-patterns-target="dashboardMetric" data-count-all="<?= $hasWork ? 2 : 0 ?>"
            data-count-oakmere="<?= $hasWork ? 1 : 0 ?>" data-count-willowbank="<?= $hasWork ? 1 : 0 ?>">
            <?= $hasWork ? '2' : '0' ?>
        </strong>
        <span><?= __('Missing or lost waivers') ?></span>
    </a>
    <a class="ui-surface ui-metric" href="#dashboard-attention">
        <span class="ui-field-label"><?= __('Ready to close') ?></span>
        <strong data-ui-patterns-target="dashboardMetric" data-count-all="<?= $hasWork ? 1 : 0 ?>"
            data-count-oakmere="<?= $hasWork ? 1 : 0 ?>" data-count-willowbank="0">
            <?= $hasWork ? '1' : '0' ?>
        </strong>
        <span><?= __('Awaiting final review') ?></span>
    </a>
    <div class="ui-surface ui-metric">
        <span class="ui-field-label"><?= __('Closed this month') ?></span>
        <strong data-ui-patterns-target="dashboardMetric" data-count-all="12"
            data-count-oakmere="8" data-count-willowbank="4">12</strong>
        <span><?= __('Collection completed') ?></span>
    </div>
</div>

<div class="ui-content-grid">
    <section class="ui-surface ui-main-column" id="dashboard-attention" aria-labelledby="dashboard-attention-title">
        <div class="ui-section-heading">
            <h2 id="dashboard-attention-title"><?= __('Needs attention') ?></h2>
            <p class="text-muted mb-0">
                <?= __('Outstanding collection work first, then gatherings ready to close.') ?>
            </p>
        </div>
        <?php if (!$hasWork) : ?>
            <div class="ui-empty">
                <?= $this->Icon->render('circle-check') ?>
                <h3><?= __('No outstanding waiver work') ?></h3>
                <p><?= __('There are no missing submissions or pending closures in your current scope.') ?></p>
                <a class="btn btn-outline-primary" href="<?= h($collectionUrl) ?>"><?= __('Browse gatherings') ?></a>
            </div>
        <?php else : ?>
            <ul class="ui-attention-list list-unstyled mb-0">
                <?php foreach ($exceptions as $exception) : ?>
                    <li class="ui-attention-item" data-ui-patterns-target="dashboardItem"
                        data-branch="<?= h($exception['branchKey']) ?>">
                        <div>
                            <span class="ui-status ui-status-<?= h($exception['tone']) ?>">
                                <?= h($exception['status']) ?>
                            </span>
                            <h3>
                                <?php if ($exception['recordUrl']) : ?>
                                    <a href="<?= h($exception['recordUrl']) ?>"><?= h($exception['gathering']) ?></a>
                                <?php else : ?>
                                    <?= h($exception['gathering']) ?>
                                <?php endif; ?>
                            </h3>
                            <p class="text-muted mb-1"><?= h($exception['branch']) ?></p>
                            <p class="mb-0"><?= h($exception['detail']) ?></p>
                        </div>
                        <?php if ($canReview) : ?>
                            <?php if ($exception['url']) : ?>
                                <a class="btn btn-outline-primary" href="<?= h($exception['url']) ?>">
                                    <?= h($exception['action']) ?>
                                </a>
                            <?php else : ?>
                                <button type="button" class="btn btn-outline-primary" data-action="ui-patterns#notice"
                                    data-message="<?= __('Review the records before changing waiver status.') ?>">
                                    <?= h($exception['action']) ?>
                                </button>
                            <?php endif; ?>
                        <?php else : ?>
                            <span class="ui-status ui-status-neutral"><?= __('View only') ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <aside class="ui-aside">
        <section class="ui-surface" aria-labelledby="dashboard-recent-title">
            <h2 class="ui-section-heading" id="dashboard-recent-title"><?= __('Recent collection activity') ?></h2>
            <ol class="ui-activity-log list-unstyled mb-0">
                <li data-ui-patterns-target="dashboardItem" data-branch="oakmere">
                    <span class="ui-status ui-status-neutral"><?= __('Uploaded') ?></span>
                    <h3><?= __('Oakmere Rapier Practice') ?></h3>
                    <p class="text-muted mb-0"><?= __('Adult roster received · Awaiting review') ?></p>
                </li>
                <li data-ui-patterns-target="dashboardItem" data-branch="willowbank">
                    <span class="ui-status ui-status-neutral"><?= __('Exempted') ?></span>
                    <h3><?= __('Willowbank Business Meeting') ?></h3>
                    <p class="text-muted mb-0"><?= __('No waiver-required activities · Reason recorded') ?></p>
                </li>
                <li data-ui-patterns-target="dashboardItem" data-branch="oakmere">
                    <span class="ui-status ui-status-success"><?= __('Closed') ?></span>
                    <h3><?= __('Oakmere Heraldry Night') ?></h3>
                    <p class="text-muted mb-0"><?= __('Review and collection complete') ?></p>
                </li>
            </ol>
        </section>
    </aside>
</div>
