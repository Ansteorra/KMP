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




<div class="row align-items-start mb-3">
    <div class="col">
        <h3>Offices</h3>
    </div>
    <div class="col text-end">
        <?php if ($user->checkCan("add", "Officers.Offices")): ?>
            <?= $this->Html->link(
                ' Add Office',
                ['action' => 'add'],
                ['class' => 'btn btn-primary bi bi-plus-circle', 'data-turbo-frame' => '_top']
            ) ?>
        <?php endif; ?>
        <?php if ($user->checkCan("syncOfficers", "Officers.Offices")): ?>
            <?= $this->Form->postLink(
                ' Sync Officers',
                ['action' => 'syncOfficers'],
                [
                    'class' => 'btn btn-outline-warning bi bi-arrow-repeat ms-2',
                    'confirm' => __('Recalculate officers for all offices? This may take a few minutes.'),
                    'data-turbo-frame' => '_top',
                ]
            ) ?>
        <?php endif; ?>
    </div>
</div>

<turbo-frame id="offices-grid" data-turbo-action="advance">
    <?= $this->element('dv_grid', [
        'frameId' => 'offices-grid',
        'tableFrameId' => 'offices-grid-table',
        'title' => __('Offices'),
        'dataUrl' => $this->Url->build(['action' => 'gridData']),
    ]) ?>
</turbo-frame>