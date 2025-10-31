<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\GatheringType $gatheringType
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Add Gathering Type';
$this->KMP->endBlock();
?>

<div class="gatheringTypes form content">
    <?= $this->Form->create($gatheringType, [
        'data-controller' => 'gathering-type-form',
        'data-gathering-type-form-max-description-length-value' => 500,
        'data-action' => 'submit->gathering-type-form#validateForm'
    ]) ?>
    <fieldset>
        <legend><?= $this->element('backButton') ?> <?= __('Add Gathering Type') ?></legend>
        <div class="mb-3">
            <?= $this->Form->control('name', [
                'required' => true,
                'data-gathering-type-form-target' => 'name',
                'data-action' => 'blur->gathering-type-form#validateName',
                'class' => 'form-control'
            ]) ?>
            <div class="invalid-feedback d-none" data-gathering-type-form-target="nameError">
                Name error
            </div>
        </div>
        <div class="mb-3">
            <?= $this->Form->control('description', [
                'type' => 'textarea',
                'rows' => 3,
                'data-gathering-type-form-target' => 'description',
                'data-action' => 'input->gathering-type-form#updateDescriptionCount',
                'class' => 'form-control'
            ]) ?>
            <div class="invalid-feedback d-none" data-gathering-type-form-target="descriptionError">
                Description is too long
            </div>
            <small class="form-text text-muted" data-gathering-type-form-target="descriptionCount">
                0 / 500 characters
            </small>
        </div>
        <div class="mb-3">
            <?= $this->Form->control('color', [
                'type' => 'color',
                'label' => 'Calendar Color',
                'default' => '#0d6efd',
                'class' => 'form-control form-control-color',
                'help' => 'Choose a color for this gathering type in the calendar view'
            ]) ?>
        </div>
        <?php
        echo $this->Form->control('clonable', [
            'label' => 'Allow cloning gatherings of this type',
            'switch' => true
        ]);
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit'), [
        'class' => 'btn btn-primary',
        'data-gathering-type-form-target' => 'submitButton'
    ]) ?>
    <?= $this->Form->end() ?>
</div>