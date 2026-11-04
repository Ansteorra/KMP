<?php
declare(strict_types=1);

/**
 * Gathering Activities Dataverse Grid Index Template
 *
 * Modern grid interface with saved views, column picker, filtering, and sorting.
 * Uses lazy-loading turbo-frame architecture for consistent data flow.
 *
 * @var \App\View\AppView $this
 */

$this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': Gathering Activities';
$this->KMP->endBlock();

$this->assign('title', __('Gathering Activities'));
?>

<div class="gathering-activities index content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><?= __('Gathering Activities') ?></h3>
        <div>
            <?php if ($user->checkCan('add', 'GatheringActivities')) : ?>
                <button
                    type="button"
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#gatheringActivityModal">
                    <?= $this->Icon->render('circle-plus') ?> <?= __('Add Gathering Activity') ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dataverse Grid with Lazy Loading -->
    <?= $this->element('dv_grid', [
        'gridKey' => 'GatheringActivities.index.main',
        'frameId' => 'gathering-activities-grid',
        'dataUrl' => $this->Url->build(['action' => 'gridData']),
    ]) ?>
</div>
<?php $this->append('modals', $this->element('gatheringActivities/editorModal')); ?>
