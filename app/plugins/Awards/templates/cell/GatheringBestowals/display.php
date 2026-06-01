<?php

/**
 * Gathering Bestowals Cell Display Template
 *
 * @var \App\View\AppView $this
 * @var int|null $gatheringId The ID of the gathering
 * @var bool $isEmpty Whether the gathering has any bestowals
 * @var bool $canManage Whether the user can manage bestowals
 */

if (!isset($gatheringId)) {
    echo '<p class="text-muted">' . __('Unable to load award bestowals for this gathering.') . '</p>';
    return;
}

$frameId = 'gathering-bestowals-grid-' . $gatheringId;
?>

<?php if (!$isEmpty): ?>
    <?= $this->element('dv_grid', [
        'gridKey' => 'Awards.Bestowals.gathering.' . $gatheringId,
        'frameId' => $frameId,
        'dataUrl' => $this->Url->build([
            'plugin' => 'Awards',
            'controller' => 'Bestowals',
            'action' => 'gatheringBestowalsGridData',
            $gatheringId,
        ]),
        'compactMode' => true,
    ]) ?>
    <?php if ($canManage): ?>
        <?= $this->element('ad_hoc_bestowal_modal', ['modalId' => 'adHocBestowalModal']) ?>
        <?= $this->element('bestowalEditModal', ['modalId' => 'editBestowalModal']) ?>
        <?= $this->element('bestowalsBulkEditModal', ['modalId' => 'bulkEditBestowalModal']) ?>
    <?php endif; ?>
<?php else: ?>
    <p class="text-muted"><?= __('No Award Bestowals for this gathering') ?></p>
    <?php if ($canManage): ?>
        <?= $this->element('ad_hoc_bestowal_modal', ['modalId' => 'adHocBestowalModal']) ?>
        <?= $this->element('bestowalEditModal', ['modalId' => 'editBestowalModal']) ?>
        <?= $this->element('bestowalsBulkEditModal', ['modalId' => 'bulkEditBestowalModal']) ?>
    <?php endif; ?>
<?php endif; ?>
