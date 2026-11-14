<?php
declare(strict_types=1);
?>
<div class="modal fade" id="report-lost-waiver-modal" tabindex="-1"
    aria-labelledby="report-lost-waiver-title" aria-describedby="report-lost-waiver-context" aria-hidden="true"
    data-action="show.bs.modal->waiver-loss#selectRow shown.bs.modal->waiver-loss#focusNotes
        hidden.bs.modal->waiver-loss#restoreFocus">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <?= $this->Form->create(null, ['url' => ['action' => 'recordLost']]) ?>
            <?= $this->Form->hidden('gathering_id', ['value' => $gathering->id]) ?>
            <?php $this->Form->unlockField('waiver_type_id'); ?>
            <?= $this->Form->hidden('waiver_type_id', [
                'id' => 'report-lost-waiver-type',
                'data-waiver-loss-target' => 'type',
            ]) ?>
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="report-lost-waiver-title"><?= __('Report/Mark Lost') ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="<?= __('Close') ?>"></button>
            </div>
            <div class="modal-body">
                <p>
                    <?= __('Existing files and waiver records will be kept. Collection status will stay unchanged.') ?>
                </p>
                <p id="report-lost-waiver-context">
                    <?= __('Waiver Type:') ?> <strong data-waiver-loss-target="typeName"></strong>
                </p>
                <p id="report-lost-notes-help" class="form-text">
                    <?= __(
                        'Include who reported the loss, the date reported, and an email reference if useful. '
                            . 'Keep the reporting email as the supporting paper trail. Maximum 10,000 characters.',
                    ) ?>
                </p>
                <?= $this->Form->control('notes', [
                    'type' => 'textarea',
                    'label' => __('Notes (Required)'),
                    'required' => true,
                    'maxlength' => 10000,
                    'rows' => 4,
                    'id' => 'report-lost-notes',
                    'data-waiver-loss-target' => 'notes',
                    'aria-describedby' => 'report-lost-notes-help',
                ]) ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                <?= $this->Form->button(__('Record Waivers Lost'), ['class' => 'btn btn-danger']) ?>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
