<?php

/**
 * Branches Dataverse Grid Index Template
 * 
 * Modern grid interface with saved views, column picker, filtering, and sorting.
 * Uses lazy-loading turbo-frame architecture for consistent data flow.
 * Shows hierarchy through computed path column.
 * 
 * @var \App\View\AppView $this
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Branches';
$this->KMP->endBlock();

$this->assign('title', __('Branches'));
?>

<div class="branches index content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><?= __('Branches') ?></h3>
        <div>
            <?php if ($user->checkCan("add", "Branches")) : ?>
                <?= $this->Html->link(
                    __('Add Branch'),
                    ['action' => 'add'],
                    ['class' => 'btn btn-primary']
                ) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dataverse Grid with Lazy Loading -->
    <?= $this->element('dv_grid', [
        'gridKey' => 'Branches.index.main',
        'frameId' => 'branches-grid',
        'dataUrl' => $this->Url->build(['action' => 'gridData']),
    ]) ?>
</div>