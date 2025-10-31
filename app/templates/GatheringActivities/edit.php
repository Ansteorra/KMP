<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\GatheringActivity $gatheringActivity
 * @var \Cake\Collection\CollectionInterface|string[] $waiverTypes
 * @var array $selectedWaiverIds
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Edit Gathering Activity';
$this->KMP->endBlock();
?>

<div class="gatheringActivities form content">
    <?= $this->Form->create($gatheringActivity) ?>
    <fieldset>
        <legend><?= $this->element('backButton') ?> <?= __('Edit Gathering Activity') ?></legend>
        <?php
        echo $this->Form->control('name', ['required' => true]);
        echo $this->Form->control('description', ['type' => 'textarea', 'rows' => 3]);
        ?>
    </fieldset>
    <div class="form-group">
        <?= $this->Form->button(__('Submit'), ['class' => 'btn btn-primary']) ?>
        <?= $this->Form->postLink(
            __('Delete'),
            ['action' => 'delete', $gatheringActivity->id],
            [
                'confirm' => __('Are you sure you want to delete "{0}"?', $gatheringActivity->name),
                'class' => 'btn btn-danger'
            ]
        ) ?>
    </div>
    <?= $this->Form->end() ?>
</div>