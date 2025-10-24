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
        'value' => $gathering->name . ' (Copy)',
        'required' => true,
        'class' => 'form-control',
        'label' => __('New Gathering Name'),
        'data-gathering-clone-target' => 'nameInput'
    ]) ?>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <?= $this->Form->control('start_date', [
            'type' => 'date',
            'required' => true,
            'class' => 'form-control',
            'label' => __('Start Date'),
            'data-gathering-clone-target' => 'startDate',
            'data-action' => 'change->gathering-clone#startDateChanged'
        ]) ?>
    </div>
    <div class="col-md-6 mb-3">
        <?= $this->Form->control('end_date', [
            'type' => 'date',
            'required' => false,
            'class' => 'form-control',
            'label' => __('End Date'),
            'data-gathering-clone-target' => 'endDate',
            'data-action' => 'change->gathering-clone#endDateChanged'
        ]) ?>
        <small class="form-text text-muted">
            <?= __('Will default to start date if not specified. For single-day gatherings, leave blank or use the same date as start date.') ?>
        </small>
    </div>
</div>

<div class="mb-3">
    <div class="form-check">
        <?= $this->Form->checkbox('clone_activities', [
            'checked' => true,
            'id' => 'clone_activities',
            'class' => 'form-check-input'
        ]) ?>
        <label class="form-check-label" for="clone_activities">
            <?= __('Include all activities ({0} activities)', count($gathering->gathering_activities)) ?>
        </label>
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
            <dd class="col-sm-8"><?= h($gathering->start_date->format('Y-m-d')) ?> to <?= h($gathering->end_date->format('Y-m-d')) ?></dd>

            <dt class="col-sm-4"><?= __('Activities') ?></dt>
            <dd class="col-sm-8"><?= count($gathering->gathering_activities) ?></dd>
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