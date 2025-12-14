<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AuthorizationApproval[]|\Cake\Collection\CollectionInterface $authorizationApprovals
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Authorization Queues';
$this->KMP->endBlock(); ?>
<h3>
    Authorization Queues
</h3>

<?= $this->element('dv_grid', [
    'gridKey' => 'Activities.AuthorizationApprovals.index.main',
    'frameId' => 'authorization-approvals-grid',
    'dataUrl' => $this->Url->build(['action' => 'gridData']),
]) ?>