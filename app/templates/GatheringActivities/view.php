<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\GatheringActivity $gatheringActivity
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': View Activity - ' . $gatheringActivity->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($gatheringActivity->name) ?>
<?php $this->KMP->endBlock() ?>

<?= $this->KMP->startBlock("recordActions") ?>
<?php if ($user->checkCan("edit", $gatheringActivity)) : ?>
<?= $this->Html->link(__('Edit'), ['action' => 'edit', $gatheringActivity->id], ['class' => 'btn btn-primary btn-sm']) ?>
<?php endif; ?>
<?php if ($user->checkCan("delete", $gatheringActivity)) : ?>
<?= $this->Form->postLink(
        __('Delete'),
        ['action' => 'delete', $gatheringActivity->id],
        [
            'confirm' => __('Are you sure you want to delete "{0}"?', $gatheringActivity->name),
            'class' => 'btn btn-danger btn-sm'
        ]
    ) ?>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("recordDetails") ?>
<tr scope="row">
    <th class="col"><?= __('Name') ?></th>
    <td class="col-10"><?= h($gatheringActivity->name) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Description') ?></th>
    <td class="col-10"><?= h($gatheringActivity->description) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Created') ?></th>
    <td class="col-10"><?= h($gatheringActivity->created) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Modified') ?></th>
    <td class="col-10"><?= h($gatheringActivity->modified) ?></td>
</tr>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("tabButtons") ?>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("tabContent") ?>
<?php $this->KMP->endBlock() ?>