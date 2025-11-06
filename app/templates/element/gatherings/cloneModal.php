<?php

/**
 * Clone Gathering Modal Element
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 */
?>
<?= $this->Form->create(null, [
    'id' => 'clone_gathering_form',
    'type' => 'post',
    'url' => [
        'controller' => 'Gatherings',
        'action' => 'clone',
        $gathering->id,
    ],
    'data-controller' => 'gathering-clone',
    'data-action' => 'submit->gathering-clone#validateForm'
]) ?>

<?php echo $this->Modal->create(__('Clone "{0}"', $gathering->name), [
    'id' => 'cloneGatheringModal',
    'close' => true,
    'size' => 'lg'
]); ?>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    <strong><?= __('Cloning this gathering') ?></strong><br>
    <?= __('This will create a new gathering with the same activities and settings. You can customize the name and dates below.') ?>
</div>

<div class="mb-3">
    <?= $this->Form->control('name', [
        'value' => $gathering->name,
        'required' => true,
        'class' => 'form-control',
        'label' => __('New Gathering Name'),
        'data-gathering-clone-target' => 'nameInput'
    ]) ?>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <?php
        // Convert gathering start_date to user's timezone for display
        $currentUser = $this->getRequest()->getAttribute('identity');
        $startInUserTz = $this->Timezone->forInput($gathering->start_date, 'Y-m-d\TH:i', $currentUser, $gathering);
        ?>
        <?= $this->Form->control('start_date', [
            'type' => 'datetime-local',
            'value' => $startInUserTz,
            'required' => true,
            'class' => 'form-control',
            'label' => __('Start Date & Time'),
            'data-gathering-clone-target' => 'startDate',
            'data-action' => 'change->gathering-clone#startDateChanged'
        ]) ?>
        <small class="form-text text-muted">
            <?= __('Time in {0}', $this->Timezone->getUserTimezone($currentUser)) ?>
        </small>
    </div>
    <div class="col-md-6 mb-3">
        <?php
        // Convert gathering end_date to user's timezone for display
        $endInUserTz = $this->Timezone->forInput($gathering->end_date, 'Y-m-d\TH:i', $currentUser, $gathering);
        ?>
        <?= $this->Form->control('end_date', [
            'type' => 'datetime-local',
            'value' => $endInUserTz,
            'required' => false,
            'class' => 'form-control',
            'label' => __('End Date & Time'),
            'data-gathering-clone-target' => 'endDate',
            'data-action' => 'change->gathering-clone#endDateChanged'
        ]) ?>
        <small class="form-text text-muted">
            <?= __('Will default to start date if not specified') ?>
        </small>
    </div>
</div>

<!-- Hidden field to store gathering timezone for conversion -->
<?= $this->Form->hidden('timezone', [
    'value' => $gathering->timezone ?? \App\KMP\TimezoneHelper::getUserTimezone($currentUser)
]) ?>

<div class="mb-3">
    <h6><?= __('Clone Options') ?></h6>
    <div class="form-check">
        <?= $this->Form->checkbox('clone_activities', [
            'checked' => true,
            'id' => 'clone_activities',
            'class' => 'form-check-input'
        ]) ?>
        <label class="form-check-label" for="clone_activities">
            <?= __('Include all activities ({0} activities)', isset($gathering->gathering_activities) ? count($gathering->gathering_activities) : 0) ?>
        </label>
        <small class="form-text text-muted d-block ms-4">
            <?= __('Copies all activities associated with this gathering') ?>
        </small>
    </div>

    <div class="form-check mt-2">
        <?= $this->Form->checkbox('clone_staff', [
            'checked' => true,
            'id' => 'clone_staff',
            'class' => 'form-check-input'
        ]) ?>
        <label class="form-check-label" for="clone_staff">
            <?php
            $staffCount = 0;
            if (isset($gathering->gathering_staff)) {
                $staffCount = count($gathering->gathering_staff);
            }
            ?>
            <?= __('Include staff ({0} staff members)', $staffCount) ?>
        </label>
        <small class="form-text text-muted d-block ms-4">
            <?= __('Copies all staff members including stewards, their roles, and contact information') ?>
        </small>
    </div>

    <div class="form-check mt-2">
        <?= $this->Form->checkbox('clone_schedule', [
            'checked' => true,
            'id' => 'clone_schedule',
            'class' => 'form-check-input'
        ]) ?>
        <label class="form-check-label" for="clone_schedule">
            <?php
            $scheduledCount = 0;
            if (isset($gathering->gathering_scheduled_activities)) {
                $scheduledCount = count($gathering->gathering_scheduled_activities);
            }
            ?>
            <?= __('Include event schedule ({0} scheduled activities)', $scheduledCount) ?>
        </label>
        <small class="form-text text-muted d-block ms-4">
            <?= __('Copies the event schedule with times adjusted to match the new start date') ?>
        </small>
    </div>
</div>

<div class="card bg-light">
    <div class="card-body">
        <h6 class="card-title"><?= __('Original Gathering Details') ?></h6>
        <dl class="row mb-0">
            <dt class="col-sm-4"><?= __('Branch') ?></dt>
            <dd class="col-sm-8"><?= h($gathering->branch->name) ?></dd>

            <dt class="col-sm-4"><?= __('Type') ?></dt>
            <dd class="col-sm-8"><?= h($gathering->gathering_type->name) ?></dd>

            <dt class="col-sm-4"><?= __('Original Dates') ?></dt>
            <dd class="col-sm-8">
                <?= $this->Timezone->format($gathering->start_date, 'Y-m-d g:i A', false, $currentUser, $gathering) ?> to
                <?= $this->Timezone->format($gathering->end_date, 'Y-m-d g:i A', false, $currentUser, $gathering) ?>
            </dd>

            <dt class="col-sm-4"><?= __('Activities') ?></dt>
            <dd class="col-sm-8">
                <?= isset($gathering->gathering_activities) ? count($gathering->gathering_activities) : 0 ?></dd>

            <dt class="col-sm-4"><?= __('Staff') ?></dt>
            <dd class="col-sm-8"><?= isset($gathering->gathering_staff) ? count($gathering->gathering_staff) : 0 ?></dd>

            <dt class="col-sm-4"><?= __('Scheduled Activities') ?></dt>
            <dd class="col-sm-8">
                <?php
                $scheduledCount = 0;
                if (isset($gathering->gathering_scheduled_activities)) {
                    $scheduledCount = count($gathering->gathering_scheduled_activities);
                }
                echo $scheduledCount;
                ?>
            </dd>
        </dl>
    </div>
</div>

<?php echo $this->Modal->end([
    $this->Form->button(__('Create Clone'), [
        'class' => 'btn btn-info',
        'id' => 'clone_gathering_submit',
        'data-gathering-clone-target' => 'submitButton'
    ]),
    $this->Form->button(__('Close'), [
        'data-bs-dismiss' => 'modal',
        'type' => 'button',
        'class' => 'btn btn-secondary'
    ]),
]);
?>
<?= $this->Form->end() ?>