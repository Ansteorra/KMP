<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\GatheringType $gatheringType
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Edit Gathering Type';
$this->KMP->endBlock();
?>

<div class="gatheringTypes form content">
    <?= $this->Form->create($gatheringType, [
        'data-controller' => 'gathering-type-form',
        'data-gathering-type-form-max-description-length-value' => 500,
        'data-action' => 'submit->gathering-type-form#validateForm'
    ]) ?>
    <fieldset>
        <legend><a href="#" onclick="window.history.back(); return false;" class="bi bi-arrow-left-circle"></a>
            <?= __('Edit Gathering Type') ?></legend>
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
            <small class="form-text text-muted" data-gathering-type-form-target="descriptionCount">
                0 / 500 characters
            </small>
        </div>
        <?php
        echo $this->Form->control('clonable', [
            'label' => 'Allow cloning gatherings of this type',
            'switch' => true
        ]);
        ?>
    </fieldset>
    <div class="form-group">
        <?= $this->Form->button(__('Submit'), [
            'class' => 'btn btn-primary',
            'data-gathering-type-form-target' => 'submitButton'
        ]) ?>
        <?= $this->Form->postLink(
            __('Delete'),
            ['action' => 'delete', $gatheringType->id],
            [
                'confirm' => __('Are you sure you want to delete "{0}"?', $gatheringType->name),
                'class' => 'btn btn-danger'
            ]
        ) ?>
    </div>
    <?= $this->Form->end() ?>
</div>