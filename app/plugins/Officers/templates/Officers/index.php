<?php

/**
 * Officers Index Template - Dataverse Grid
 * 
 * Modern grid interface with saved views, column picker, filtering, and sorting.
 * Uses lazy-loading turbo-frame architecture with system views for officer status tabs.
 * 
 * @var \App\View\AppView $this
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Officers';
$this->KMP->endBlock();

$this->assign('title', __('Officers'));
?>

<div class="officers index content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><?= __('Officers by Warrant Status') ?></h3>
    </div>

    <!-- Dataverse Grid with Lazy Loading and System Views -->
    <?= $this->element('dv_grid', [
        'gridKey' => 'Officers.Officers.index.main',
        'frameId' => 'officers-grid',
        'dataUrl' => $this->Url->build(['plugin' => 'Officers', 'controller' => 'Officers', 'action' => 'gridData']),
    ]) ?>
</div>