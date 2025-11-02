<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \App\Model\Entity\Branch[] $branches
 * @var \App\Model\Entity\GatheringType[] $gatheringTypes
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
        <legend><?= $this->element('backButton') ?> <?= __('Add Gathering') ?></legend>

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
            <?= $this->Form->control('description', [
                'type' => 'textarea',
                'rows' => 4,
                'class' => 'form-control',
                'label' => 'Description (Markdown supported)',
                'placeholder' => 'Additional information about the gathering...',
                'data-controller' => 'markdown-editor',
                'data-markdown-editor-placeholder-value' => 'Enter gathering description using Markdown...',
                'data-markdown-editor-min-height-value' => '250px'
            ]) ?>
            <small class="form-text text-muted">
                <?= __('You can use Markdown formatting: **bold**, *italic*, # headings, - lists, [links](url), etc.') ?>
            </small>
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