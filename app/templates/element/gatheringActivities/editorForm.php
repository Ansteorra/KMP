<?php
declare(strict_types=1);

/** @var \App\Model\Entity\GatheringActivity $gatheringActivity */
$isNew = $gatheringActivity->isNew();
?>
<?= $this->Form->create($gatheringActivity, [
    'url' => $isNew ? ['action' => 'add'] : ['action' => 'edit', $gatheringActivity->id],
    'type' => 'file',
    'data-controller' => 'turbo-modal',
    'data-gathering-activity-modal-target' => 'form',
    'data-action' => 'submit->turbo-modal#submitAsTurboStream',
    'data-turbo' => 'true',
    'data-turbo-modal-success-message-value' => __('Activity saved.'),
    'data-turbo-modal-error-message-value' => __('Check the activity settings and try again.'),
]) ?>
<?php $this->Form->unlockField('page_context_url'); ?>
<?= $this->Form->hidden('page_context_url', ['value' => '']) ?>
<div class="modal-body">
    <?= $this->Flash->render() ?>
    <?php if ($gatheringActivity->hasErrors()) : ?>
        <div
            class="alert alert-danger"
            role="alert"><?= __('Please correct the highlighted activity settings.') ?></div>
    <?php endif; ?>
    <?= $this->element('gatheringActivities/fields', compact('gatheringActivity')) ?>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
    <?= $this->Form->button($isNew ? __('Add activity') : __('Save changes'), ['class' => 'btn btn-primary']) ?>
</div>
<?= $this->Form->end() ?>
