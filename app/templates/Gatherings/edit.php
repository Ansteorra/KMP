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
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Edit Gathering - ' . $gathering->name;
$this->KMP->endBlock();
?>

<div class="gatherings form content">
    <?= $this->Form->create($gathering, [
        'data-controller' => 'gathering-form',
        'data-action' => 'submit->gathering-form#validateForm'
    ]) ?>
    <fieldset>
        <legend><a href="#" onclick="window.history.back(); return false;" class="bi bi-arrow-left-circle"></a>
            <?= __('Edit Gathering') ?></legend>

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
                'label' => 'Description (Markdown supported)',
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
        <?= $this->Form->button(__('Save Changes'), [
            'class' => 'btn btn-primary',
            'data-gathering-form-target' => 'submitButton'
        ]) ?>
        <?= $this->Html->link(__('Cancel'), ['action' => 'view', $gathering->id], ['class' => 'btn btn-secondary']) ?>
    </div>
    <?= $this->Form->end() ?>
</div>