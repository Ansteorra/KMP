<?php

declare(strict_types=1);

/** @var \App\View\AppView $this */
/** @var string $mode */
$targetPrefix = $mode === 'edit' ? 'edit' : '';
$target = static fn(string $name): string => $targetPrefix ? $targetPrefix . ucfirst($name) : $name;
$times = [];
for ($minutes = 0; $minutes < 1440; $minutes += 15) {
    $hour = intdiv($minutes, 60);
    $minute = $minutes % 60;
    $times[sprintf('%02d:%02d', $hour, $minute)] = sprintf(
        '%d:%02d %s',
        $hour % 12 ?: 12,
        $minute,
        $hour < 12 ? 'AM' : 'PM',
    );
}
$durations = [];
for ($minutes = 15; $minutes <= 240; $minutes += 15) {
    $durations[$minutes] = __('{0} minutes', $minutes);
}
$durations['other'] = __('Other — see description');
?>
<div class="row g-3">
    <div class="col-md-4">
        <?= $this->Form->control('schedule_date', [
            'type' => 'date',
            'label' => __('Start Date'),
            'required' => true,
            'class' => 'form-control',
            'id' => $mode . '-schedule-date',
            'data-gathering-schedule-target' => $target('startDate'),
            'data-action' => 'change->gathering-schedule#timingChanged',
        ]) ?>
    </div>
    <div class="col-md-4">
        <?= $this->Form->control('schedule_time', [
            'type' => 'select',
            'options' => $times,
            'label' => __('Start Time'),
            'required' => true,
            'class' => 'form-select',
            'id' => $mode . '-schedule-time',
            'data-gathering-schedule-target' => $target('startTime'),
            'data-action' => 'change->gathering-schedule#timingChanged',
        ]) ?>
    </div>
    <div class="col-md-4">
        <?= $this->Form->control('duration_minutes', [
            'type' => 'select',
            'options' => $durations,
            'default' => '60',
            'label' => __('Duration'),
            'required' => true,
            'class' => 'form-select',
            'id' => $mode . '-schedule-duration',
            'aria-describedby' => $mode . '-duration-help',
            'data-gathering-schedule-target' => $target('duration'),
        ]) ?>
    </div>
    <div class="col-12">
        <p class="form-text mb-0" id="<?= h($mode) ?>-duration-help">
            <?= __(
                'Choose 15 minutes to 4 hours. For other durations, explain the timing in Description; '
                . 'only the start time will appear in the schedule.',
            ) ?>
        </p>
    </div>
</div>
<?= $this->Form->hidden('start_datetime', [
    'id' => $mode . '-start-datetime',
    'data-gathering-schedule-target' => $target('startDatetime'),
]) ?>
