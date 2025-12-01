<?php

/**
 * Email Templates Dataverse Grid Index Template
 * 
 * Modern grid interface with saved views, column picker, filtering, and sorting.
 * Uses lazy-loading turbo-frame architecture for consistent data flow.
 * 
 * @var \App\View\AppView $this
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Email Templates';
$this->KMP->endBlock();

$this->assign('title', __('Email Templates'));
?>

<div class="email-templates index content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><?= __('Email Templates') ?></h3>
        <div>
            <?php if ($user->checkCan("add", "EmailTemplates")) : ?>
                <?= $this->Html->link(
                    __('Add Email Template'),
                    ['action' => 'add'],
                    ['class' => 'btn btn-primary me-2']
                ) ?>
            <?php endif; ?>
            <?php if ($user->checkCan("sync", "EmailTemplates")) : ?>
                <?= $this->Html->link(
                    __('Discover'),
                    ['action' => 'discover'],
                    ['class' => 'btn btn-outline-secondary me-2']
                ) ?>
                <?= $this->Form->postLink(
                    __('Sync All'),
                    ['action' => 'sync'],
                    ['confirm' => __('Sync will discover all mailer methods and create templates for any that are missing. Continue?'), 'class' => 'btn btn-outline-info']
                ) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dataverse Grid with Lazy Loading -->
    <?= $this->element('dv_grid', [
        'gridKey' => 'EmailTemplates.index.main',
        'frameId' => 'email-templates-grid',
        'dataUrl' => $this->Url->build(['action' => 'gridData']),
    ]) ?>
</div>