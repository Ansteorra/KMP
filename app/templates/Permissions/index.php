<?php

/**
 * Permissions Dataverse Grid Index Template
 * 
 * Modern grid interface with saved views, column picker, filtering, and sorting.
 * Uses lazy-loading turbo-frame architecture for consistent data flow.
 * 
 * @var \App\View\AppView $this
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Permissions';
$this->KMP->endBlock();

$this->assign('title', __('Permissions'));
?>

<div class="permissions index content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><?= __('Permissions') ?></h3>
        <div>
            <?php if ($user->checkCan("add", "Permissions")) : ?>
                <?= $this->Html->link(
                    __('Add Permission'),
                    ['action' => 'add'],
                    ['class' => 'btn btn-primary']
                ) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dataverse Grid with Lazy Loading -->
    <?= $this->element('dv_grid', [
        'gridKey' => 'Permissions.index.main',
        'frameId' => 'permissions-grid',
        'dataUrl' => $this->Url->build(['action' => 'gridData']),
    ]) ?>
</div>