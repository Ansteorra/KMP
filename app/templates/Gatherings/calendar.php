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
 * @var int $defaultYear
 * @var int $defaultMonth
 * @var string $defaultView
 */

use Cake\I18n\DateTime;

$this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': Gatherings Calendar';
$this->KMP->endBlock();
?>

<?php
$stickyDefaults = json_encode([
    'year' => $defaultYear,
    'month' => $defaultMonth,
    'view' => $defaultView,
    'week_start' => $this->request->getQuery('week_start'),
]);
?>
<div data-controller="gatherings-calendar grid-view"
    data-grid-view-sticky-query-value="year,month,view,week_start"
    data-grid-view-sticky-default-value='<?= h($stickyDefaults) ?>'>
    <?= $this->element('dv_custom', [
        'gridKey' => 'Gatherings.calendar.main',
        'frameId' => 'gatherings-calendar-grid',
        'dataUrl' => $this->Url->build(['action' => 'calendarGridData']),
    ]) ?>
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
        overflow: hidden;
        min-width: 0;
    }

    .calendar-day .calendar-day-add {
        position: absolute;
        bottom: 4px;
        right: 6px;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-size: 0.85rem;
        color: #198754;
        opacity: 0;
        transition: opacity 0.15s;
        text-decoration: none;
    }

    .calendar-day:hover .calendar-day-add {
        opacity: 0.7;
    }

    .calendar-day .calendar-day-add:hover {
        opacity: 1;
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
        overflow: hidden;
        min-width: 0;
    }

    .gathering-item .fw-bold {
        white-space: normal;
        word-wrap: break-word;
        overflow-wrap: break-word;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
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