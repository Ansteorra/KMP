<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \App\Model\Entity\Branch[] $branches
 * @var \App\Model\Entity\GatheringType[] $gatheringTypes
 * @var \App\Model\Entity\GatheringActivity[] $gatheringActivities
 * @var bool $hasWaivers
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Edit Gathering - ' . $gathering->name;
$this->KMP->endBlock();
?>

<div class="gatherings form content">
    <?= $this->Form->create($gathering, [
        'data-controller' => 'gathering-form',
        'data-action' => 'submit->gathering-form#validateForm',
        'data-gathering-form-has-waivers-value' => $hasWaivers ? 'true' : 'false'
    ]) ?>
    <fieldset>
        <legend><?= __('Edit Gathering') ?></legend>

        <div class="mb-3">
            <?= $this->Form->control('name', [
                'required' => true,
                'class' => 'form-control',
                'data-gathering-form-target' => 'name'
            ]) ?>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <?= $this->Form->control('branch_id', [
                    'options' => $branches,
                    'empty' => __('-- Select Branch --'),
                    'required' => true,
                    'class' => 'form-select'
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <?= $this->Form->control('gathering_type_id', [
                    'options' => $gatheringTypes,
                    'empty' => __('-- Select Type --'),
                    'required' => true,
                    'class' => 'form-select'
                ]) ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <?= $this->Form->control('start_date', [
                    'type' => 'date',
                    'required' => true,
                    'class' => 'form-control',
                    'data-gathering-form-target' => 'startDate',
                    'data-action' => 'change->gathering-form#validateDates'
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <?= $this->Form->control('end_date', [
                    'type' => 'date',
                    'required' => true,
                    'class' => 'form-control',
                    'data-gathering-form-target' => 'endDate',
                    'data-action' => 'change->gathering-form#validateDates'
                ]) ?>
                <small class="form-text text-muted">
                    End date must be on or after start date.
                </small>
            </div>
        </div>

        <div class="mb-3">
            <?= $this->Form->control('location', [
                'type' => 'text',
                'class' => 'form-control'
            ]) ?>
        </div>

        <div class="mb-3">
            <?= $this->Form->control('description', [
                'type' => 'textarea',
                'rows' => 4,
                'class' => 'form-control',
                'label' => 'Notes'
            ]) ?>
        </div>

        <div class="mb-3">
            <label class="form-label"><?= __('Gathering Activities') ?></label>

            <?php if ($hasWaivers): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-lock-fill"></i>
                    <strong><?= __('Activities are locked') ?></strong><br>
                    <?= __('Waivers have been uploaded for this gathering, so activities cannot be changed. This ensures the integrity of collected waivers.') ?>
                </div>

                <!-- Display selected activities as read-only -->
                <?php if (!empty($gathering->gathering_activities)): ?>
                    <div class="list-group">
                        <?php foreach ($gathering->gathering_activities as $activity): ?>
                            <div class="list-group-item">
                                <div class="d-flex align-items-start">
                                    <div class="form-check">
                                        <input type="checkbox" checked disabled class="form-check-input">
                                    </div>
                                    <div class="ms-2 flex-grow-1">
                                        <strong><?= h($activity->name) ?></strong>
                                        <?php if (!empty($activity->description)): ?>
                                            <div class="text-muted small"><?= h($activity->description) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Editable activity selection -->
                <small class="form-text text-muted d-block mb-2">
                    Select the activities that will be part of this gathering.
                </small>

                <?php if (!empty($gatheringActivities)): ?>
                    <div class="list-group">
                        <?php foreach ($gatheringActivities as $activity): ?>
                            <label class="list-group-item">
                                <div class="d-flex align-items-start">
                                    <div class="form-check">
                                        <?= $this->Form->checkbox('gathering_activities._ids[]', [
                                            'value' => $activity->id,
                                            'id' => 'activity-' . $activity->id,
                                            'class' => 'form-check-input',
                                            'checked' => in_array($activity->id, array_column($gathering->gathering_activities, 'id'))
                                        ]) ?>
                                    </div>
                                    <div class="ms-2 flex-grow-1">
                                        <strong><?= h($activity->name) ?></strong>
                                        <?php if (!empty($activity->description)): ?>
                                            <div class="text-muted small"><?= h($activity->description) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        No gathering activities have been created yet.
                        <?= $this->Html->link(
                            'Create one now',
                            ['controller' => 'GatheringActivities', 'action' => 'add'],
                            ['target' => '_blank']
                        ) ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </fieldset>

    <div class="mt-3">
        <?= $this->Form->button(__('Save Changes'), [
            'class' => 'btn btn-primary',
            'data-gathering-form-target' => 'submitButton'
        ]) ?>
        <?= $this->Html->link(__('Cancel'), ['action' => 'view', $gathering->id], ['class' => 'btn btn-secondary']) ?>
    </div>
    <?= $this->Form->end() ?>
</div>