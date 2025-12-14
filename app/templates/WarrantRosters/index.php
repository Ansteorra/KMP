<?php

/**
 * Warrant Rosters Index - Dataverse Grid View
 * 
 * @var \App\View\AppView $this
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Warrant Rosters';
$this->KMP->endBlock(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><?= __('Warrant Rosters') ?></h3>
    <?= $this->Html->link(
        __(' Add Roster'),
        ['action' => 'add'],
        ['class' => 'btn btn-primary bi bi-plus-circle']
    ) ?>
</div>

<?= $this->element('dv_grid', [
    'frameId' => 'warrant-rosters-grid',
    'dataUrl' => $this->Url->build(['controller' => 'WarrantRosters', 'action' => 'gridData']),
]) ?>