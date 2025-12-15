<?php

/**
 * Gathering Types Dataverse Grid Index Template
 * 
 * Modern grid interface with saved views, column picker, filtering, and sorting.
 * Uses lazy-loading turbo-frame architecture for consistent data flow.
 * 
 * @var \App\View\AppView $this
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Gathering Types';
$this->KMP->endBlock();

$this->assign('title', __('Gathering Types'));
?>

<div class="gathering-types index content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><?= __('Gathering Types') ?></h3>
        <div>
            <?php if ($user->checkCan("add", "GatheringTypes")) : ?>
            <?= $this->Html->link(
                    __(' Add Gathering Type'),
                    ['action' => 'add'],
                    ['class' => 'btn btn-primary  bi bi-plus-circle']
                ) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dataverse Grid with Lazy Loading -->
    <?= $this->element('dv_grid', [
        'gridKey' => 'GatheringTypes.index.main',
        'frameId' => 'gathering-types-grid',
        'dataUrl' => $this->Url->build(['action' => 'gridData']),
    ]) ?>
</div>