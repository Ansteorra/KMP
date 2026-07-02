<?php

/**
 * Award Bestowals Index
 *
 * @var \App\View\AppView $this
 */

$this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': Award Bestowals';
$this->KMP->endBlock();
?>

<div class="row align-items-start mb-3">
    <div class="col">
        <h3><?= __('Award Bestowals') ?></h3>
        <p class="text-muted mb-0">
            <?= __('Manage the court and presentation workflow after recommendations become bestowals.') ?>
        </p>
        <p class="text-muted small mb-0">
            <?=
            __(
                'For mass updates, select bestowals in the first grid column, then use Mass Complete Check or ' .
                'Mass Assign Gathering in the grid toolbar.',
            )
            ?>
        </p>
    </div>
    <div class="col text-end">
        <?php if ($user->checkCan('edit', 'Awards.Bestowals')) : ?>
            <button type="button"
                class="btn btn-primary bi bi-plus-circle"
                data-bs-toggle="modal"
                data-bs-target="#adHocBestowalModal">
                <?= __('Record Ad-Hoc Bestowal') ?>
            </button>
        <?php endif; ?>
    </div>
</div>

<?= $this->element('dv_grid', [
    'gridKey' => 'Awards.Bestowals.index.main',
    'frameId' => 'bestowals-grid',
    'dataUrl' => $this->Url->build([
        'plugin' => 'Awards',
        'controller' => 'Bestowals',
        'action' => 'gridData',
    ]),
]) ?>

<?php
echo $this->KMP->startBlock('modals');
if ($user->checkCan('edit', 'Awards.Bestowals')) {
    echo $this->element('ad_hoc_bestowal_modal', ['modalId' => 'adHocBestowalModal']);
    echo $this->element('bestowalEditModal', ['modalId' => 'editBestowalModal']);
}
echo $this->element('bestowalTodosModal', ['modalId' => 'bestowalTodosModal']);
echo $this->element('bestowalBulkTodoModal', [
    'modalId' => 'bestowalBulkTodoModal',
    'checkOptions' => $bestowalCheckOptions ?? [],
]);
echo $this->element('bestowalBulkGatheringModal', [
    'modalId' => 'bestowalBulkGatheringModal',
]);
$this->KMP->endBlock();
