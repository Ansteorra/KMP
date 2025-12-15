<?php

/**
 * Warrants Index - Dataverse Grid View
 *
 * @var \App\View\AppView $this
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Warrants';
$this->KMP->endBlock(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><?= __('Warrants') ?></h3>
</div>

<?= $this->element('dv_grid', [
    'frameId' => 'warrants-grid',
    'dataUrl' => $this->Url->build(['controller' => 'Warrants', 'action' => 'gridData']),
]) ?>