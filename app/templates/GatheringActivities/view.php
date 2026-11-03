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
    <td class="col-10"><?= $this->GatheringActivity->badge($gatheringActivity) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Description') ?></th>
    <td class="col-10"><?= h($gatheringActivity->description) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Order Circle') ?></th>
    <td class="col-10"><?= $this->KMP->bool($gatheringActivity->is_circle, $this->Html) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Created') ?></th>
    <td class="col-10"><?= $this->Timezone->format($gatheringActivity->created, null, null, \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Modified') ?></th>
    <td class="col-10"><?= $this->Timezone->format($gatheringActivity->modified, null, null, \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT) ?></td>
</tr>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link" id="nav-assigned-by-tab" data-bs-toggle="tab" data-bs-target="#nav-assigned-by"
    type="button" role="tab" aria-controls="nav-assigned-by" aria-selected="false"
    data-detail-tabs-target="tabBtn" data-tab-order="10" style="order: 10;">
    <?= __('Can be assigned by') ?>
</button>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("tabContent") ?>
<div class="related tab-pane fade m-3" id="nav-assigned-by" role="tabpanel"
    aria-labelledby="nav-assigned-by-tab" data-detail-tabs-target="tabContent" data-tab-order="10" style="order: 10;">
    <p><?= __('With no rules, gathering editors and stewards may assign this activity. With rules, at least one selected permission must apply in the gathering’s branch; that grants access to this activity and its schedule only.') ?></p>
    <?= $this->element('gatheringActivities/assignmentPermissions') ?>
</div>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock('modals') ?>
<?php if ($canManageAssignmentPermissions && $permissionOptions !== []) : ?>
    <?= $this->element('gatheringActivities/addPermissionModal') ?>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>
