<?php

/**
 * Workflow Instances List
 *
 * @var \App\View\AppView $this
 * @var int|null $definitionId
 * @var \App\Model\Entity\WorkflowDefinition|null $workflowDefinition
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Workflow Instances';
$this->KMP->endBlock();

$this->assign('title', __('Workflow Instances'));

$gridDataUrl = $definitionId !== null
    ? $this->Url->build(['controller' => 'WorkflowInstances', 'action' => 'gridData', $definitionId])
    : $this->Url->build(['controller' => 'WorkflowInstances', 'action' => 'gridData']);

?>

<div class="workflows instances content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>
            <?= $this->element('backButton') ?>
            <?= __('Workflow Instances') ?>
            <?php if ($workflowDefinition) : ?>
                <small class="text-muted"><?= h(__('for {0}', $workflowDefinition->name)) ?></small>
            <?php elseif ($definitionId) : ?>
                <small class="text-muted"><?= __('(filtered)') ?></small>
            <?php endif; ?>
        </h3>
        <div>
            <?php if ($definitionId !== null) : ?>
                <?= $this->Html->link(
                    __('Show All'),
                    ['action' => 'instances'],
                    ['class' => 'btn btn-outline-secondary btn-sm']
                ) ?>
            <?php endif; ?>
        </div>
    </div>

    <?= $this->element('dv_grid', [
        'frameId' => 'workflow-instances-grid',
        'dataUrl' => $gridDataUrl,
    ]) ?>
</div>
