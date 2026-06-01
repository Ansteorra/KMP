<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $user
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Bestowal Statuses';
$this->KMP->endBlock();
?>

<div class="row align-items-start mb-3">
    <div class="col">
        <h3><?= __('Bestowal Statuses') ?></h3>
    </div>
    <div class="col text-end">
        <?php if ($user->checkCan("add", "Awards.BestowalStatuses")): ?>
        <?= $this->Html->link(
                ' Add Status',
                ['action' => 'add'],
                ['class' => 'btn btn-primary bi bi-plus-circle', 'data-turbo-frame' => '_top']
            ) ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->element('dv_grid', [
    'gridKey' => 'Awards.BestowalStatuses.index.main',
    'frameId' => 'bestowal-statuses-grid',
    'dataUrl' => $this->Url->build(['action' => 'gridData']),
]) ?>
