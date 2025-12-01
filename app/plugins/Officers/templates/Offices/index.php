<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Permission[]|\Cake\Collection\CollectionInterface $permissions
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Offices';
$this->KMP->endBlock(); ?>

<?php
$addLink = null;
if ($user->checkCan("add", "Officers.Offices")) {
    $addLink = $this->Html->link(
        ' Add Office',
        ['action' => 'add'],
        ['class' => 'btn btn-primary btn-sm bi bi-plus-circle', 'data-turbo-frame' => '_top']
    );
}
?>

<turbo-frame id="offices-grid" data-turbo-action="advance">
    <?= $this->element('dv_grid', [
        'frameId' => 'offices-grid',
        'tableFrameId' => 'offices-grid-table',
        'title' => __('Offices'),
        'addLink' => $addLink,
        'dataUrl' => $this->Url->build(['action' => 'gridData']),
    ]) ?>
</turbo-frame>