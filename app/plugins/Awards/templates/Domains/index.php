<?php

/**
 * Award Domains Index - Dataverse Grid View
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $user
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Award Domains';
$this->KMP->endBlock();
?>

<div class="row align-items-start mb-3">
    <div class="col">
        <h3>Award Domains</h3>
    </div>
    <div class="col text-end">
        <?php if ($user->checkCan("add", "Awards.Domains")): ?>
            <?= $this->Html->link(
                ' Add Domain',
                ['action' => 'add'],
                ['class' => 'btn btn-primary btn-sm bi bi-plus-circle', 'data-turbo-frame' => '_top']
            ) ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->element('dv_grid', [
    'gridKey' => 'Awards.Domains.index.main',
    'frameId' => 'domains-grid',
    'dataUrl' => $this->Url->build(['action' => 'gridData']),
]) ?>