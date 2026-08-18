<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $user
 */

$this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': Award Approval Processes';
$this->KMP->endBlock();
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h3 class="mb-0"><?= __('Award Approval Processes') ?></h3>
    <div class="d-flex flex-wrap justify-content-end gap-2">
        <?php if ($user->checkCan('syncOpenRecommendations', 'Awards.ApprovalProcesses')) : ?>
            <?= $this->Form->create(null, [
                'url' => ['action' => 'syncOpenRecommendations'],
                'class' => 'm-0',
                'data-turbo-frame' => '_top',
            ]) ?>
            <?= $this->Form->button(__('Sync Open Recommendations Now'), [
                'class' => 'btn btn-outline-primary',
                'data-confirm-message' => __(
                    'Start current approval workflows for eligible open recommendations that do not have one, '
                    . 'then synchronize eligible active recommendations with each award\'s current approval process? '
                    . 'Approval requirements may change. A satisfied or recovered final approval may continue through '
                    . 'the normal workflow and create exactly one bestowal. '
                    . 'Synchronization never marks a bestowal Given.',
                ),
                'data-confirm-title' => __('Synchronize open recommendations'),
                'data-confirm-label' => __('Sync Now'),
            ]) ?>
            <?= $this->Form->end() ?>
        <?php endif; ?>
        <?php if ($user->checkCan('add', 'Awards.ApprovalProcesses')) : ?>
            <?= $this->Html->link(
                __('Add Approval Process'),
                ['action' => 'add'],
                ['class' => 'btn btn-primary bi bi-plus-circle', 'data-turbo-frame' => '_top'],
            ) ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->element('dv_grid', [
    'gridKey' => 'Awards.ApprovalProcesses.index.main',
    'frameId' => 'approval-processes-grid',
    'dataUrl' => $this->Url->build(['action' => 'gridData']),
]) ?>
