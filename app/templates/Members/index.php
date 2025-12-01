<?php

/**
 * Members Dataverse Grid Index Template
 * 
 * Modern grid interface with saved views, column picker, filtering, and sorting.
 * Uses lazy-loading turbo-frame architecture for consistent data flow.
 * 
 * @var \App\View\AppView $this
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Members';
$this->KMP->endBlock();


$this->assign('title', __('Members'));
?>

<div class="members index content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><?= __('Members - Dataverse Grid') ?></h3>
        <div>
            <?= $this->Html->link(
                __('Add Member'),
                ['action' => 'add'],
                ['class' => 'btn btn-primary']
            ) ?>
            <?= $this->Html->link(
                __('Classic View'),
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary ms-2']
            ) ?>
        </div>
    </div>

    <!-- Dataverse Grid with Lazy Loading -->
    <?= $this->element('dv_grid', [
        'gridKey' => 'Members.index.main',
        'frameId' => 'members-grid',
        'dataUrl' => $this->Url->build(['action' => 'gridData']),
    ]) ?>
</div>