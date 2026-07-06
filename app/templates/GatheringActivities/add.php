<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\GatheringActivity $gatheringActivity
 * @var \Cake\Collection\CollectionInterface|string[] $waiverTypes
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Add Gathering Activity';
$this->KMP->endBlock();
?>

<div class="gatheringActivities form content">
    <?= $this->Form->create($gatheringActivity) ?>
    <fieldset>
        <legend><?= $this->element('backButton') ?> <?= __('Add Gathering Activity') ?></legend>
        <?php
        echo $this->Form->control('name', ['required' => true]);
        echo $this->Form->control('description', ['type' => 'textarea', 'rows' => 3]);
        echo $this->Form->control('is_circle', [
            'type' => 'checkbox',
            'switch' => true,
            'label' => __('Order Circle'),
            'help' => __('Marks this activity as an order circle (Laurel, Pelican, etc.); shown with a circle icon on the public calendar.'),
        ]);
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit'), ['class' => 'btn btn-primary']) ?>
    <?= $this->Form->end() ?>
</div>