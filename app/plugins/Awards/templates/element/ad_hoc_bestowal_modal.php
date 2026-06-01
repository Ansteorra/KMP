<?php

/**
 * Ad-hoc bestowal modal stub
 *
 * @var \App\View\AppView $this
 * @var string $modalId Modal DOM ID
 */

$modalId = $modalId ?? 'adHocBestowalModal';
?>

<div class="modal fade" id="<?= h($modalId) ?>" tabindex="-1" aria-labelledby="<?= h($modalId) ?>Label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <?= $this->Form->create(null, [
                'url' => [
                    'plugin' => 'Awards',
                    'controller' => 'Bestowals',
                    'action' => 'adHoc',
                ],
            ]) ?>
            <div class="modal-header">
                <h5 class="modal-title" id="<?= h($modalId) ?>Label">
                    <i class="bi bi-award"></i> <?= __('Record Ad-Hoc Bestowal') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Close') ?>"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted"><?= __('Ad-hoc bestowal recording will be expanded in a follow-up change.') ?></p>
                <?= $this->Form->control('member_id', [
                    'type' => 'number',
                    'label' => __('Member ID'),
                    'required' => true,
                ]) ?>
                <?= $this->Form->control('state', [
                    'type' => 'text',
                    'label' => __('Initial State'),
                    'default' => 'Created',
                ]) ?>
                <?= $this->Form->hidden('current_page', ['value' => $this->request->getRequestTarget()]) ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                <button type="submit" class="btn btn-primary"><?= __('Save') ?></button>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
