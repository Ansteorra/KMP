<?php

/**
 * Verification Queue Dataverse Grid Template
 * 
 * Modern grid interface for member verification queue with system views
 * for Youth, Card Uploaded, and Without Card categories.
 * Uses lazy-loading turbo-frame architecture.
 * 
 * @var \App\View\AppView $this
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Verification Queue';
$this->KMP->endBlock();

$this->assign('title', __('Verification Queue'));
?>

<div class="verify-queue index content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><?= __('Verification Queue') ?></h3>
    </div>

    <!-- Dataverse Grid with Lazy Loading -->
    <?= $this->element('dv_grid', [
        'gridKey' => 'Members.verifyQueue.main',
        'frameId' => 'verify-queue-grid',
        'dataUrl' => $this->Url->build(['action' => 'verifyQueueGridData']),
    ]) ?>
</div>