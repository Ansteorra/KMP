<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \App\Model\Entity\Branch[] $branches
 * @var \App\Model\Entity\GatheringType[] $gatheringTypes
 * @var \App\Model\Entity\GatheringActivity[] $gatheringActivities
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Add Gathering';
$this->KMP->endBlock();
?>

<div class="gatherings form content">
    <?= $this->Form->create($gathering, [
        'data-controller' => 'gathering-form',
        'data-action' => 'submit->gathering-form#validateForm'
    ]) ?>
    <fieldset>
        <legend><?= __('Add Gathering') ?></legend>

        <div class="mb-3">
            <?= $this->Form->control('name', [
                'required' => true,
                'class' => 'form-control',
                'data-gathering-form-target' => 'name',
                'placeholder' => 'e.g., Spring Fighter Practice, Summer Camp 2025'
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
                    'data-action' => 'change->gathering-form#startDateChanged'
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <?= $this->Form->control('end_date', [
                    'type' => 'date',
                    'required' => false,
                    'class' => 'form-control',
                    'data-gathering-form-target' => 'endDate',
                    'data-action' => 'change->gathering-form#endDateChanged'
                ]) ?>
                <small class="form-text text-muted">
                    Will default to start date if not specified. For single-day gatherings, leave blank or use the same
                    date as start date.
                </small>
            </div>
        </div>

        <div class="mb-3">
            <?php
            // Get Google Maps API key for autocomplete
            $apiKey = $this->KMP->getAppSetting('GoogleMaps.ApiKey', '');
            ?>
            <?= $this->Form->control('location', [
                'type' => 'text',
                'class' => 'form-control',
                'placeholder' => 'Start typing an address or place name...',
                'data-controller' => 'gathering-location-autocomplete',
                'data-gathering-location-autocomplete-api-key-value' => h($apiKey),
                'autocomplete' => 'off'  // Disable browser autocomplete to avoid conflicts
            ]) ?>
            <small class="form-text text-muted">
                <?= __('Start typing to see address suggestions powered by Google Maps') ?>
            </small>
        </div>

        <div class="mb-3">
            <?= $this->Form->control('description', [
                'type' => 'textarea',
                'rows' => 4,
                'class' => 'form-control',
                'label' => 'Notes',
                'placeholder' => 'Additional information about the gathering...'
            ]) ?>
        </div>

        <div class="mb-3">
            <label class="form-label"><?= __('Gathering Activities') ?></label>
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
                                        'class' => 'form-check-input'
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
        </div>
    </fieldset>

    <div class="mt-3">
        <?= $this->Form->button(__('Create Gathering'), [
            'class' => 'btn btn-primary',
            'data-gathering-form-target' => 'submitButton'
        ]) ?>
        <?= $this->Html->link(__('Cancel'), ['action' => 'index'], ['class' => 'btn btn-secondary']) ?>
    </div>
    <?= $this->Form->end() ?>
</div>