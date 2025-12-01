<?php

/**
 * Waiver Types Index - Dataverse Grid View
 *
 * @var \App\View\AppView $this
 */

$this->extend("/layout/TwitterBootstrap/dashboard");
$this->append('css', $this->AssetMix->css('waivers'));

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Waiver Types';
$this->KMP->endBlock();
?>

<div class="row align-items-start mb-3">
    <div class="col">
        <h3>Waiver Types</h3>
    </div>
    <div class="col text-end">
        <?php if ($user->checkCan("add", "Waivers.WaiverTypes")): ?>
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle"></i> Add Waiver Type',
                ['action' => 'add'],
                ['class' => 'btn btn-primary btn-sm', 'escape' => false]
            ) ?>
        <?php endif; ?>
    </div>
</div>

<!-- Dataverse Grid with lazy loading -->
<?= $this->element('dv_grid', [
    'gridKey' => 'Waivers.WaiverTypes.index.main',
    'frameId' => 'waiver-types-grid',
    'dataUrl' => $this->Url->build([
        'plugin' => 'Waivers',
        'controller' => 'WaiverTypes',
        'action' => 'gridData',
    ]),
]) ?>