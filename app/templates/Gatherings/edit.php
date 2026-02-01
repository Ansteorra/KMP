<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \App\Model\Entity\Branch[] $branches
 * @var \App\Model\Entity\GatheringType[] $gatheringTypes
 * @var bool $lockBranch Whether the branch field should be locked (non-editable)
 */

$lockBranch = $lockBranch ?? false;
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
        'data-action' => 'submit->gathering-form#validateForm'
    ]) ?>
    <fieldset>
        <legend><?= $this->element('backButton') ?> <?= __('Edit Gathering') ?></legend>

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
                    'empty' => $lockBranch ? false : __('-- Select Branch --'),
                    'required' => true,
                    'class' => 'form-select',
                    'disabled' => $lockBranch
                ]) ?>
                <?php if ($lockBranch): ?>
                    <?= $this->Form->hidden('branch_id', ['value' => $gathering->branch_id]) ?>
                    <small class="form-text text-muted">
                        <?= __('Branch cannot be changed based on your permissions.') ?>
                    </small>
                <?php endif; ?>
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
                    'type' => 'datetime-local',
                    'required' => true,
                    'class' => 'form-control',
                    'value' => $this->Timezone->forInput($gathering->start_date, null, null, $gathering),
                    'data-gathering-form-target' => 'startDate',
                    'data-action' => 'change->gathering-form#validateDates',
                    'label' => 'Start Date & Time'
                ]) ?>
                <small class="form-text text-muted">
                    Times shown in <?= !empty($gathering->timezone) ? h($gathering->timezone) : 'your timezone' ?>
                </small>
            </div>
            <div class="col-md-6 mb-3">
                <?= $this->Form->control('end_date', [
                    'type' => 'datetime-local',
                    'required' => true,
                    'class' => 'form-control',
                    'value' => $this->Timezone->forInput($gathering->end_date, null, null, $gathering),
                    'data-gathering-form-target' => 'endDate',
                    'data-action' => 'change->gathering-form#validateDates',
                    'label' => 'End Date & Time'
                ]) ?>
                <small class="form-text text-muted">
                    End date must be on or after start date.
                </small>
            </div>
        </div>

        <div class="mb-3">
            <?php
            // Get Google Maps API key for autocomplete
            $apiKey = $this->KMP->getAppSetting('GoogleMaps.ApiKey', '');
            ?>
            <div data-controller="gathering-location-autocomplete"
                data-gathering-location-autocomplete-api-key-value="<?= h($apiKey) ?>">
                <?= $this->Form->control('location', [
                    'type' => 'text',
                    'class' => 'form-control',
                    'placeholder' => 'Start typing an address or place name...',
                    'autocomplete' => 'off',  // Disable browser autocomplete to avoid conflicts
                    'data-gathering-location-autocomplete-target' => 'input'
                ]) ?>

                <?php // Hidden fields to store geocoded coordinates 
                ?>
                <?= $this->Form->hidden('latitude', [
                    'data-gathering-location-autocomplete-target' => 'latitude'
                ]) ?>
                <?= $this->Form->hidden('longitude', [
                    'data-gathering-location-autocomplete-target' => 'longitude'
                ]) ?>
            </div>
            <small class="form-text text-muted">
                <?= __('Start typing to see address suggestions powered by Google Maps') ?>
            </small>
        </div>

        <div class="mb-3">
            <?= $this->Form->control('timezone', [
                'type' => 'select',
                'options' => $this->Timezone->getTimezoneOptions(),
                'empty' => sprintf('(Use User Timezone: %s)', $this->Timezone->getUserTimezone()),
                'class' => 'form-select',
                'label' => 'Event Timezone'
            ]) ?>
            <small class="form-text text-muted">
                <?= __('Set the timezone for this event based on its location. If not set, times will display in each user\'s timezone.') ?>
            </small>
        </div>

        <div class="mb-3">
            <?= $this->Form->control('description', [
                'type' => 'textarea',
                'rows' => 4,
                'class' => 'form-control',
                'label' => 'Description (Markdown supported)',
                'data-controller' => 'markdown-editor',
                'data-markdown-editor-placeholder-value' => 'Enter gathering description using Markdown...',
                'data-markdown-editor-min-height-value' => '250px'
            ]) ?>
            <small class="form-text text-muted">
                <?= __('You can use Markdown formatting: **bold**, *italic*, # headings, - lists, [links](url), etc.') ?>
            </small>
        </div>

        <div class="mb-3">
            <div class="form-check">
                <?= $this->Form->checkbox('public_page_enabled', [
                    'id' => 'public_page_enabled',
                    'class' => 'form-check-input'
                ]) ?>
                <label class="form-check-label" for="public_page_enabled">
                    <?= __('Enable Public Landing Page') ?>
                </label>
                <small class="form-text text-muted d-block">
                    <?= __('Allow public access to this gathering\'s event page (includes schedule, activities, and staff information)') ?>
                </small>
            </div>
        </div>
    </fieldset>

    <div class="mt-3">
        <?= $this->Form->button(__('Save Changes'), [
            'class' => 'btn btn-primary',
            'data-gathering-form-target' => 'submitButton'
        ]) ?>
        <?= $this->Html->link(__('Cancel'), ['action' => 'view', $gathering->public_id], ['class' => 'btn btn-secondary']) ?>
    </div>
    <?= $this->Form->end() ?>
</div>