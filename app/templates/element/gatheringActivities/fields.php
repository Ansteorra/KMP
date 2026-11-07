<?php
declare(strict_types=1);

use App\Model\Entity\GatheringActivity;

/** @var \App\Model\Entity\GatheringActivity $gatheringActivity */
?>
<fieldset class="mb-3">
    <legend class="fs-5"><?= __('Activity details') ?></legend>
    <?= $this->Form->control('name', ['required' => true]) ?>
    <?= $this->Form->control('description', ['type' => 'textarea', 'rows' => 3]) ?>
    <?= $this->Form->control('display_order', [
        'type' => 'number', 'min' => 0, 'max' => 2147483647, 'step' => 1, 'default' => 100,
        'label' => __('Display order'),
        'help' => __('Lower numbers appear first within each activity group. Ties sort alphabetically.'),
    ]) ?>
    <?= $this->Form->control('activity_type', [
        'type' => 'select', 'label' => __('Activity Type'),
        'options' => array_combine(
            GatheringActivity::ACTIVITY_TYPES,
            GatheringActivity::ACTIVITY_TYPES,
        ),
        'default' => GatheringActivity::TYPE_GENERAL,
        'required' => true,
    ]) ?>
</fieldset>
<?= $this->element('gatherings/activity_appearance_form', compact('gatheringActivity')) ?>
