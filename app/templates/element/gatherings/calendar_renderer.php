<?php

/**
 * Gatherings Calendar Renderer - Just the Calendar Grid
 *
 * This element renders only the calendar grid portion (month/week/list view).
 * The toolbar with filters has been moved to calendar_toolbar.php to prevent
 * the filter dropdown from closing when the calendar reloads.
 *
 * @var \App\View\AppView $this
 * @var iterable $data The gatherings to display
 * @var array $calendarMeta Calendar metadata
 * @var string $viewMode Current view mode (month, week, list)
 */

$calendarMeta = $calendarMeta ?? [];
$viewMode = $viewMode ?? 'month';
$year = $calendarMeta['year'] ?? (int)date('Y');
$month = $calendarMeta['month'] ?? (int)date('m');
$calendarStart = $calendarMeta['calendarStart'] ?? null;
$calendarEnd = $calendarMeta['calendarEnd'] ?? null;
$startDate = $calendarMeta['startDate'] ?? null;
$endDate = $calendarMeta['endDate'] ?? null;
$weekStartValue = null;
if ($viewMode === 'week' && $startDate instanceof \DateTimeInterface) {
    $weekStartValue = clone $startDate;
    $weekDay = (int)$weekStartValue->format('w');
    if ($weekDay > 0) {
        $weekStartValue->modify("-{$weekDay} days");
    }
}
?>

<div class="gatherings-calendar" data-controller="gatherings-calendar"
    data-gatherings-calendar-year-value="<?= h($year) ?>" data-gatherings-calendar-month-value="<?= h($month) ?>"
    data-gatherings-calendar-view-value="<?= h($viewMode) ?>"
    <?= $weekStartValue ? 'data-gatherings-calendar-week-start-value="' . h($weekStartValue->format('Y-m-d')) . '"' : '' ?>>

    <div class="row g-3">
        <div class="col-12">
            <?php if ($viewMode === 'month'): ?>
                <?= $this->element('gatherings/calendar_month', [
                    'gatherings' => $data,
                    'calendarStart' => $calendarStart,
                    'calendarEnd' => $calendarEnd,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                ]) ?>
            <?php elseif ($viewMode === 'week'): ?>
                <?= $this->element('gatherings/calendar_week', [
                    'gatherings' => $data,
                    'startDate' => $startDate,
                ]) ?>
            <?php else: ?>
                <?= $this->element('gatherings/calendar_list', [
                    'gatherings' => $data,
                ]) ?>
            <?php endif; ?>
        </div>
        <div class="col-12">
            <div class="card mt-2">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-info-circle"></i> <?= __('Legend') ?></h6>
                </div>
                <div class="card-body d-flex flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success"><i class="bi bi-check-circle"></i></span>
                        <span><?= __('I\'m Attending') ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-info"><i class="bi bi-geo-alt"></i></span>
                        <span><?= __('Has Location') ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-warning text-dark"><i class="bi bi-calendar-range"></i></span>
                        <span><?= __('Multi-day Event') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
