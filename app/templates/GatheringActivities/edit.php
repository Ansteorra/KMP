<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\GatheringActivity $gatheringActivity
 * @var \Cake\Collection\CollectionInterface|array<string> $waiverTypes
 * @var array $selectedWaiverIds
 */
?>
<?php
$this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': Edit Gathering Activity';
$this->KMP->endBlock();
?>

<div class="gatheringActivities form content">
    <?= $this->Form->create($gatheringActivity, ['type' => 'file']) ?>
    <h1 class="h4"><?= $this->element('backButton') ?> <?= __('Edit Gathering Activity') ?></h1>
    <?= $this->element('gatheringActivities/fields', compact('gatheringActivity')) ?>
    <div class="form-group">
        <?= $this->Form->button(__('Submit'), ['class' => 'btn btn-primary']) ?>
        <?= $this->Form->postLink(
            __('Delete'),
            ['action' => 'delete', $gatheringActivity->id],
            [
                'confirm' => __('Are you sure you want to delete "{0}"?', $gatheringActivity->name),
                'class' => 'btn btn-danger',
            ],
        ) ?>
    </div>
    <?= $this->Form->end() ?>
</div>
