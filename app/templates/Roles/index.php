<?php

/**
 * Roles Dataverse Grid Index Template
 * 
 * Modern grid interface with saved views, column picker, filtering, and sorting.
 * Uses lazy-loading turbo-frame architecture for consistent data flow.
 * 
 * @var \App\View\AppView $this
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Roles';
$this->KMP->endBlock();

$this->assign('title', __('Roles'));
?>

<div class="roles index content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><?= __('Roles') ?></h3>
        <div>
            <?php if ($user->checkCan("add", "Roles")) : ?>
                <?= $this->Html->link(
                    __('Add Role'),
                    ['action' => 'add'],
                    ['class' => 'btn btn-primary']
                ) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dataverse Grid with Lazy Loading -->
    <?= $this->element('dv_grid', [
        'gridKey' => 'Roles.index.main',
        'frameId' => 'roles-grid',
        'dataUrl' => $this->Url->build(['action' => 'gridData']),
    ]) ?>
</div>