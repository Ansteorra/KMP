<?php

/**
 * Award Levels Index - Dataverse Grid View
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $user
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Award Levels';
$this->KMP->endBlock();
?>

<div class="row align-items-start mb-3">
    <div class="col">
        <h3>Award Levels</h3>
    </div>
    <div class="col text-end">
        <?php if ($user->checkCan("add", "Awards.Levels")): ?>
        <?= $this->Html->link(
                ' Add Level',
                ['action' => 'add'],
                ['class' => 'btn btn-primary bi bi-plus-circle', 'data-turbo-frame' => '_top']
            ) ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->element('dv_grid', [
    'gridKey' => 'Awards.Levels.index.main',
    'frameId' => 'levels-grid',
    'dataUrl' => $this->Url->build(['action' => 'gridData']),
]) ?>