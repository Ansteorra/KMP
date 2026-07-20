<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\ApprovalProcess $approvalProcess
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Add Award Approval Process';
$this->KMP->endBlock();
?>

<div class="approvalProcesses form content">
    <?= $this->Form->create($approvalProcess) ?>
    <fieldset>
        <legend><?= $this->element('backButton') ?> <?= __('Add Award Approval Process') ?></legend>
        <?= $this->Form->control('name') ?>
        <?= $this->Form->control('description') ?>
        <?= $this->Form->control('is_active', [
            'type' => 'checkbox',
            'switch' => true,
            'label' => __('Active'),
            'checked' => true,
        ]) ?>
    </fieldset>
    <div class="text-end">
        <?= $this->Form->button(__('Submit'), ['class' => 'btn-primary']) ?>
    </div>
    <?= $this->Form->end() ?>
</div>
