<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $user
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Bestowal States';
$this->KMP->endBlock();
?>

<div class="row align-items-start mb-3">
    <div class="col">
        <h3><?= __('Bestowal States') ?></h3>
    </div>
    <div class="col text-end">
        <?php if ($user->checkCan("add", "Awards.BestowalStates")): ?>
        <?= $this->Html->link(
                ' Add State',
                ['action' => 'add'],
                ['class' => 'btn btn-primary bi bi-plus-circle', 'data-turbo-frame' => '_top']
            ) ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->element('dv_grid', [
    'gridKey' => 'Awards.BestowalStates.index.main',
    'frameId' => 'bestowal-states-grid',
    'dataUrl' => $this->Url->build(['action' => 'gridData']),
]) ?>
