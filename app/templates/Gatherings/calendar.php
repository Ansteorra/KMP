<?php

/**
 * Gatherings Calendar View
 *
 * Interactive calendar view for viewing and managing gatherings across the kingdom.
 * Features:
 * - Month/Week/List view modes
 * - Filter by branch, gathering type, and activities
 * - Quick attendance marking
 * - Visual gathering details
 * - Location integration
 * - Responsive design
 *
 * @var \App\View\AppView $this
 * @var \Cake\ORM\ResultSet $gatherings
 * @var int $year
 * @var int $month
 * @var string $view
 * @var \DateTime $startDate
 * @var \DateTime $endDate
 * @var \DateTime $calendarStart
 * @var \DateTime $calendarEnd
 * @var \DateTime $prevMonth
 * @var \DateTime $nextMonth
 * @var array $branches
 * @var array $gatheringTypes
 * @var array $gatheringActivities
 * @var \App\Model\Entity\Branch|null $selectedBranch
 */

use Cake\I18n\DateTime;

$this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': Gatherings Calendar';
$this->KMP->endBlock();
?>

<div class="gatherings-calendar" data-controller="gatherings-calendar"
    data-gatherings-calendar-year-value="<?= h($year) ?>" data-gatherings-calendar-month-value="<?= h($month) ?>"
    data-gatherings-calendar-view-value="<?= h($view) ?>">

    <!-- Header with Navigation and View Controls -->
    <div class="row mb-3">
        <div class="col-md-6">
            <h3>
                <i class="bi bi-calendar-event"></i>
                <?php if ($selectedBranch): ?>
                <?= h($selectedBranch) ?> Calendar
                <?php else: ?>
                Kingdom Calendar
                <?php endif; ?>
            </h3>
        </div>
        <div class="col-md-6 text-end">
            <?php
            $gatheringsTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Gatherings');
            $tempGathering = $gatheringsTable->newEmptyEntity();
            if ($user->checkCan('add', $tempGathering)) :
            ?>
            <?= $this->Html->link(
                    '<i class="bi bi-plus-circle"></i> Add Gathering',
                    ['action' => 'add'],
                    ['class' => 'btn btn-primary btn-sm', 'escape' => false]
                ) ?>
            <?php endif; ?>
            <?= $this->Html->link(
                '<i class="bi bi-list"></i> List View',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary btn-sm', 'escape' => false]
            ) ?>
        </div>
    </div>

    <!-- Calendar Navigation Bar -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="row align-items-center">
                <!-- Previous Month -->
                <div class="col-auto">
                    <?= $this->Html->link(
                        '<i class="bi bi-chevron-left"></i>',
                        [
                            'action' => 'calendar',
                            '?' => array_merge(
                                $this->request->getQueryParams(),
                                [
                                    'year' => $prevMonth->format('Y'),
                                    'month' => $prevMonth->format('m')
                                ]
                            )
                        ],
                        ['class' => 'btn btn-outline-primary', 'escape' => false, 'title' => 'Previous Month']
                    ) ?>
                </div>

                <!-- Current Month/Year Display -->
                <div class="col text-center">
                    <h4 class="mb-0">
                        <?= $startDate->format('F Y') ?>
                    </h4>
                </div>

                <!-- Next Month -->
                <div class="col-auto">
                    <?= $this->Html->link(
                        '<i class="bi bi-chevron-right"></i>',
                        [
                            'action' => 'calendar',
                            '?' => array_merge(
                                $this->request->getQueryParams(),
                                [
                                    'year' => $nextMonth->format('Y'),
                                    'month' => $nextMonth->format('m')
                                ]
                            )
                        ],
                        ['class' => 'btn btn-outline-primary', 'escape' => false, 'title' => 'Next Month']
                    ) ?>
                </div>

                <!-- Today Button -->
                <div class="col-auto">
                    <?= $this->Html->link(
                        'Today',
                        [
                            'action' => 'calendar',
                            '?' => array_filter(
                                $this->request->getQueryParams(),
                                function ($key) {
                                    return !in_array($key, ['year', 'month']);
                                },
                                ARRAY_FILTER_USE_KEY
                            )
                        ],
                        ['class' => 'btn btn-outline-secondary']
                    ) ?>
                </div>

                <!-- View Mode Selector -->
                <div class="col-auto">
                    <div class="btn-group" role="group" aria-label="View mode">
                        <?= $this->Html->link(
                            '<i class="bi bi-calendar3"></i>',
                            ['action' => 'calendar', '?' => array_merge($this->request->getQueryParams(), ['view' => 'month'])],
                            [
                                'class' => 'btn btn-sm ' . ($view === 'month' ? 'btn-primary' : 'btn-outline-primary'),
                                'escape' => false,
                                'title' => 'Month View'
                            ]
                        ) ?>
                        <?= $this->Html->link(
                            '<i class="bi bi-calendar-week"></i>',
                            ['action' => 'calendar', '?' => array_merge($this->request->getQueryParams(), ['view' => 'week'])],
                            [
                                'class' => 'btn btn-sm ' . ($view === 'week' ? 'btn-primary' : 'btn-outline-primary'),
                                'escape' => false,
                                'title' => 'Week View'
                            ]
                        ) ?>
                        <?= $this->Html->link(
                            '<i class="bi bi-list-ul"></i>',
                            ['action' => 'calendar', '?' => array_merge($this->request->getQueryParams(), ['view' => 'list'])],
                            [
                                'class' => 'btn btn-sm ' . ($view === 'list' ? 'btn-primary' : 'btn-outline-primary'),
                                'escape' => false,
                                'title' => 'List View'
                            ]
                        ) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Filters Sidebar -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-funnel"></i> Filters</h5>
                </div>
                <div class="card-body">
                    <?= $this->Form->create(null, ['type' => 'get', 'valueSources' => 'query']) ?>

                    <!-- Preserve current month/year/view -->
                    <?= $this->Form->hidden('year', ['value' => $year]) ?>
                    <?= $this->Form->hidden('month', ['value' => $month]) ?>
                    <?= $this->Form->hidden('view', ['value' => $view]) ?>

                    <!-- Branch Filter -->
                    <div class="mb-3">
                        <?= $this->Form->control('branch_id', [
                            'type' => 'select',
                            'options' => $branches,
                            'empty' => '-- All Branches --',
                            'label' => 'Branch',
                            'class' => 'form-select form-select-sm',
                            'value' => $branchFilter
                        ]) ?>
                    </div>

                    <!-- Gathering Type Filter -->
                    <div class="mb-3">
                        <?= $this->Form->control('gathering_type_id', [
                            'type' => 'select',
                            'options' => $gatheringTypes,
                            'empty' => '-- All Types --',
                            'label' => 'Gathering Type',
                            'class' => 'form-select form-select-sm',
                            'value' => $typeFilter
                        ]) ?>
                    </div>

                    <!-- Activity Filter -->
                    <div class="mb-3">
                        <?= $this->Form->control('activity_id', [
                            'type' => 'select',
                            'options' => $gatheringActivities,
                            'empty' => '-- All Activities --',
                            'label' => 'Activity',
                            'class' => 'form-select form-select-sm',
                            'value' => $activityFilter
                        ]) ?>
                    </div>

                    <div class="d-grid gap-2">
                        <?= $this->Form->button('Apply Filters', [
                            'class' => 'btn btn-primary btn-sm'
                        ]) ?>
                        <?= $this->Html->link(
                            'Clear Filters',
                            [
                                'action' => 'calendar',
                                '?' => ['year' => $year, 'month' => $month, 'view' => $view]
                            ],
                            ['class' => 'btn btn-outline-secondary btn-sm']
                        ) ?>
                    </div>

                    <?= $this->Form->end() ?>
                </div>
            </div>

            <!-- Legend -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-info-circle"></i> Legend</h6>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <span class="badge bg-success">
                            <i class="bi bi-check-circle"></i>
                        </span>
                        I'm Attending
                    </div>
                    <div class="mb-2">
                        <span class="badge bg-info">
                            <i class="bi bi-geo-alt"></i>
                        </span>
                        Has Location
                    </div>
                    <div class="mb-2">
                        <span class="badge bg-warning text-dark">
                            <i class="bi bi-calendar-range"></i>
                        </span>
                        Multi-day Event
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar Display -->
        <div class="col-md-9">
            <?php if ($view === 'month'): ?>
            <!-- Month View -->
            <?= $this->element('gatherings/calendar_month', [
                    'gatherings' => $gatherings,
                    'calendarStart' => $calendarStart,
                    'calendarEnd' => $calendarEnd,
                    'startDate' => $startDate,
                    'endDate' => $endDate
                ]) ?>
            <?php elseif ($view === 'week'): ?>
            <!-- Week View -->
            <?= $this->element('gatherings/calendar_week', [
                    'gatherings' => $gatherings,
                    'startDate' => $startDate
                ]) ?>
            <?php else: ?>
            <!-- List View -->
            <?= $this->element('gatherings/calendar_list', [
                    'gatherings' => $gatherings
                ]) ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background-color: #dee2e6;
    border: 1px solid #dee2e6;
}

.calendar-day-header {
    background-color: #e9ecef;
    padding: 0.5rem;
    text-align: center;
    font-weight: bold;
    border: 1px solid #dee2e6;
}

.calendar-day {
    background-color: #fff;
    min-height: 120px;
    padding: 0.5rem;
    position: relative;
    border: 1px solid #dee2e6;
}

.calendar-day.other-month {
    background-color: #f8f9fa;
    opacity: 0.6;
}

.calendar-day.today {
    background-color: #fff3cd;
}

.calendar-day-number {
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.gathering-item {
    font-size: 0.75rem;
    padding: 0.25rem;
    margin-bottom: 0.25rem;
    border-radius: 0.25rem;
    cursor: pointer;
    transition: all 0.2s;
}

.gathering-item:hover {
    transform: translateX(2px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.gathering-item.multi-day {
    border-left: 3px solid #ffc107;
}

.gathering-item.attending {
    border-right: 3px solid #198754;
}

.gathering-badges {
    display: flex;
    gap: 0.25rem;
    flex-wrap: wrap;
    margin-top: 0.25rem;
}

.gathering-badges .badge {
    font-size: 0.65rem;
}
</style>

<?php
// Add modal block to layout
$this->KMP->startBlock("modals");
?>
<!-- Quick View Modal -->
<div class="modal fade" id="gatheringQuickViewModal" tabindex="-1" aria-labelledby="gatheringQuickViewModalLabel"
    aria-hidden="true" data-controller="gatherings-calendar">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="gatheringQuickViewModalLabel">Gathering Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <turbo-frame id="gatheringQuickView" data-gatherings-calendar-target="turboFrame">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading gathering details...</p>
                    </div>
                </turbo-frame>
            </div>
        </div>
    </div>
</div>

</turbo-frame>
</div>

<!-- Attendance Modal - Reusing attendGatheringModal element -->
<?php
// Create a temporary gathering object for the modal (will be populated via JS)
$tempGathering = new \App\Model\Entity\Gathering([
    'id' => 0,
    'name' => '',
    'start_date' => new \Cake\I18n\FrozenDate(),
    'end_date' => new \Cake\I18n\FrozenDate(),
]);

echo $this->element('gatherings/attendGatheringModal', [
    'gathering' => $tempGathering,
    'userAttendance' => null,
    'user' => $this->request->getAttribute('identity'),
    'modalId' => 'attendanceModal',
    'fromCalendar' => true,
]);
?>
<?php
$this->KMP->endBlock();
?>