<?php

/**
 * Gatherings Calendar Toolbar
 *
 * Contains all the controls that should NOT reload when filters change:
 * - Month/week navigation controls
 * - Filter dropdown
 * - View mode switcher
 * - Filter pills container
 *
 * @var \App\View\AppView $this
 * @var array $gridState Complete grid state object
 * @var array $calendarMeta Calendar metadata
 * @var string $viewMode Current view mode (month, week, list)
 */

use Cake\ORM\TableRegistry;

$year = $calendarMeta['year'] ?? (int)date('Y');
$month = $calendarMeta['month'] ?? (int)date('m');
$selectedBranch = $calendarMeta['selectedBranch'] ?? null;
$startDate = $calendarMeta['startDate'] ?? null;
$prevMonth = $calendarMeta['prevMonth'] ?? null;
$nextMonth = $calendarMeta['nextMonth'] ?? null;
$queryParams = $calendarMeta['queryParams'] ?? $this->getRequest()->getQueryParams();

// Remove pagination when building navigation URLs
unset($queryParams['page']);

$baseParams = $queryParams;
$identity = $this->getRequest()->getAttribute('identity');

$buildFrameUrl = function (array $overrides = []) use ($baseParams) {
    $params = array_merge($baseParams, $overrides);
    foreach ($params as $key => $value) {
        if ($value === null) {
            unset($params[$key]);
        }
    }
    return [
        'controller' => 'Gatherings',
        'action' => 'calendarGridData',
        '?' => $params,
    ];
};

// Calculate week start date for week view
$weekStartDate = null;
if ($viewMode === 'week' && $startDate instanceof \DateTimeInterface) {
    $weekStartDate = clone $startDate;
    $weekDay = (int)$weekStartDate->format('w');
    if ($weekDay > 0) {
        $weekStartDate->modify("-{$weekDay} days");
    }
}

// Build navigation URLs
if ($viewMode === 'week' && $weekStartDate instanceof \DateTimeInterface) {
    $prevWeek = (clone $weekStartDate)->modify('-7 days');
    $nextWeek = (clone $weekStartDate)->modify('+7 days');

    $prevUrl = $buildFrameUrl([
        'year' => $prevWeek->format('Y'),
        'month' => $prevWeek->format('m'),
        'week_start' => $prevWeek->format('Y-m-d'),
    ]);

    $nextUrl = $buildFrameUrl([
        'year' => $nextWeek->format('Y'),
        'month' => $nextWeek->format('m'),
        'week_start' => $nextWeek->format('Y-m-d'),
    ]);

    $todayUrl = $buildFrameUrl([
        'year' => date('Y'),
        'month' => date('m'),
        'week_start' => date('Y-m-d'),
    ]);
} else {
    $prevUrl = $prevMonth
        ? $buildFrameUrl([
            'year' => $prevMonth->format('Y'),
            'month' => $prevMonth->format('m'),
            'week_start' => null,
        ])
        : null;
    $nextUrl = $nextMonth
        ? $buildFrameUrl([
            'year' => $nextMonth->format('Y'),
            'month' => $nextMonth->format('m'),
            'week_start' => null,
        ])
        : null;
    $todayUrl = $buildFrameUrl([
        'year' => date('Y'),
        'month' => date('m'),
        'week_start' => null,
    ]);
}

$weekLinkStart = $weekStartDate
    ?? ($startDate instanceof \DateTimeInterface ? $startDate : new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)));

$monthUrl = $buildFrameUrl(['view' => 'month', 'week_start' => null]);
$weekUrl = $buildFrameUrl([
    'view' => 'week',
    'week_start' => $weekLinkStart instanceof \DateTimeInterface ? $weekLinkStart->format('Y-m-d') : date('Y-m-d'),
]);
$listUrl = $buildFrameUrl(['view' => 'list', 'week_start' => null]);

$gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
$tempGathering = $gatheringsTable->newEmptyEntity();
$canAddGathering = $identity && $identity->checkCan('add', $tempGathering);
?>

<div class="row mb-3 align-items-center">
    <div class="col-md-6">
        <h3 class="mb-0">
            <i class="bi bi-calendar-event"></i>
            <?= $selectedBranch ? h($selectedBranch) . ' ' : '' ?><?= __('Calendar') ?>
        </h3>
    </div>
    <div class="col-md-6 text-end d-flex justify-content-end gap-2">
        <a class="btn btn-outline-secondary" href="<?= $this->Url->build(['action' => 'index']) ?>"
            data-turbo-frame="_top">
            <i class="bi bi-list"></i> <?= __('List View') ?>
        </a>
        <?php if ($canAddGathering): ?>
            <a class="btn btn-primary" href="<?= $this->Url->build(['action' => 'add']) ?>" data-turbo-frame="_top">
                <i class="bi bi-plus-circle"></i> <?= __('Add Gathering') ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row align-items-center g-2">
            <div class="col-auto">
                <?php if ($prevUrl): ?>
                    <?= $this->Html->link('<i class="bi bi-chevron-left"></i>', $prevUrl, [
                        'escape' => false,
                        'class' => 'btn btn-outline-primary',
                        'title' => $viewMode === 'week' ? __('Previous Week') : __('Previous Month'),
                        'data-turbo-frame' => 'gatherings-calendar-grid-table',
                        'data-gatherings-calendar-nav' => 'prev',
                    ]) ?>
                <?php endif; ?>
            </div>
            <div class="col text-center">
                <h4 class="mb-0" data-gatherings-calendar-header>
                    <?= $startDate ? h($this->Timezone->format($startDate, null, 'F Y')) : sprintf('%s %s', date('F'), date('Y')) ?>
                </h4>
            </div>
            <div class="col-auto">
                <?php if ($nextUrl): ?>
                    <?= $this->Html->link('<i class="bi bi-chevron-right"></i>', $nextUrl, [
                        'escape' => false,
                        'class' => 'btn btn-outline-primary',
                        'title' => $viewMode === 'week' ? __('Next Week') : __('Next Month'),
                        'data-turbo-frame' => 'gatherings-calendar-grid-table',
                        'data-gatherings-calendar-nav' => 'next',
                    ]) ?>
                <?php endif; ?>
            </div>
            <div class="col-auto">
                <?= $this->Html->link(__('Today'), $todayUrl, [
                    'class' => 'btn btn-outline-secondary',
                    'data-turbo-frame' => 'gatherings-calendar-grid-table',
                    'data-gatherings-calendar-nav' => 'today',
                ]) ?>
            </div>
            <div class="col-auto">
                <?= $this->element('dv_custom_filter_controls') ?>
            </div>
            <div class="col-auto">
                <div class="btn-group" role="group" aria-label="<?= __('View mode') ?>">
                    <?= $this->Html->link('<i class="bi bi-calendar3"></i>', $monthUrl, [
                        'escape' => false,
                        'class' => 'btn btn-sm ' . ($viewMode === 'month' ? 'btn-primary' : 'btn-outline-primary'),
                        'title' => __('Month View'),
                        'data-turbo-frame' => 'gatherings-calendar-grid-table',
                    ]) ?>
                    <?= $this->Html->link('<i class="bi bi-calendar-week"></i>', $weekUrl, [
                        'escape' => false,
                        'class' => 'btn btn-sm ' . ($viewMode === 'week' ? 'btn-primary' : 'btn-outline-primary'),
                        'title' => __('Week View'),
                        'data-turbo-frame' => 'gatherings-calendar-grid-table',
                    ]) ?>
                    <?= $this->Html->link('<i class="bi bi-list-ul"></i>', $listUrl, [
                        'escape' => false,
                        'class' => 'btn btn-sm ' . ($viewMode === 'list' ? 'btn-primary' : 'btn-outline-primary'),
                        'title' => __('List View'),
                        'data-turbo-frame' => 'gatherings-calendar-grid-table',
                    ]) ?>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12 d-flex flex-wrap gap-2 align-items-center grid-view-badges"
                data-filter-pills-container></div>
        </div>
    </div>
</div>
