<?php

/**
 * Gathering Waivers Index - Dataverse Grid View (All Waivers)
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering|null $gathering
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Waivers Uploaded';
$this->KMP->endBlock();
?>

<div class="row align-items-start mb-3">
    <div class="col">
        <h3><?= $this->element('backButton') ?> <?= __('Waivers Uploaded') ?></h3>
    </div>
    <div class="col text-end">
        <?php if ($user->checkCan("index", "Waivers.GatheringWaivers")): ?>
            <?= $this->Html->link(
                '<i class="bi bi-speedometer2"></i> ' . __('Waiver Dashboard'),
                ['action' => 'dashboard'],
                ['class' => 'btn btn-outline-primary', 'escape' => false]
            ) ?>
        <?php endif; ?>
    </div>
</div>

<!-- Dataverse Grid with lazy loading -->
<?= $this->element('dv_grid', [
    'gridKey' => 'Waivers.GatheringWaivers.index.main',
    'frameId' => 'gathering-waivers-grid',
    'dataUrl' => $this->Url->build([
        'plugin' => 'Waivers',
        'controller' => 'GatheringWaivers',
        'action' => 'gridData',
    ]),
]) ?>