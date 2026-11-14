<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\GatheringActivity $gatheringActivity
 * @var \Cake\Collection\CollectionInterface|array<string> $waiverTypes
 */
?>
<?php
$this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': Add Gathering Activity';
$this->KMP->endBlock();
?>

<div class="gatheringActivities form content">
    <?= $this->Form->create($gatheringActivity, ['type' => 'file']) ?>
    <h1 class="h4"><?= $this->element('backButton') ?> <?= __('Add Gathering Activity') ?></h1>
    <?= $this->element('gatheringActivities/fields', compact('gatheringActivity')) ?>
    <?= $this->Form->button(__('Submit'), ['class' => 'btn btn-primary']) ?>
    <?= $this->Form->end() ?>
</div>
