<?php

/**
 * Activity Awards Cell Template
 * 
 * Displays awards that can be given out during a specific gathering activity.
 * Uses the shared awards dataverse grid with an activity-specific remove action.
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\GatheringActivity $gatheringActivity
 * @var bool $canEdit
 * @var array $availableAwards
 */

$gridFrameId = 'activity-awards-grid-' . $gatheringActivity->id;
?>

<turbo-frame id="activity-awards-<?= $gatheringActivity->id ?>">
    <div class="related">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <?php if ($canEdit && !empty($availableAwards)) : ?>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addAwardModal">
                    <i class="bi bi-plus-circle"></i> <?= __('Add Award') ?>
                </button>
            <?php endif; ?>
        </div>

        <?= $this->element('dv_grid', [
            'gridKey' => 'Awards.Awards.activity.' . $gatheringActivity->id,
            'frameId' => $gridFrameId,
            'dataUrl' => $this->Url->build([
                'plugin' => 'Awards',
                'controller' => 'Awards',
                'action' => 'activity-awards-grid-data',
                $gatheringActivity->id,
            ]),
            'compactMode' => true,
        ]) ?>
    </div>
</turbo-frame>

<?php if ($canEdit && !empty($availableAwards)) : ?>
    <!-- Add Award Modal -->
    <?= $this->Form->create(null, [
        'url' => [
            'plugin' => 'Awards',
            'controller' => 'Awards',
            'action' => 'add-activity-to-gathering-activity',
            $gatheringActivity->id,
        ],
        'data-turbo' => 'true',
        'data-controller' => 'turbo-modal',
        'data-action' => 'turbo:submit-start->turbo-modal#closeModalBeforeSubmit',
    ]) ?>
    <?= $this->Modal->create('Add Award', [
        'id' => 'addAwardModal',
        'close' => true,
    ]) ?>
    <div class="mb-3">
        <label for="award_id" class="form-label"><?= __('Select Award') ?></label>
        <?= $this->Form->control('award_id', [
            'options' => $availableAwards,
            'empty' => __('-- Select an award --'),
            'class' => 'form-select',
            'label' => false,
            'required' => true,
        ]) ?>
        <div class="form-text">
            <?= __('Select an award that can be given out during this activity.') ?>
        </div>
    </div>
    <?= $this->Modal->end([
        $this->Form->button(__('Add Award'), [
            'class' => 'btn btn-primary',
        ]),
        $this->Form->button(__('Close'), [
            'data-bs-dismiss' => 'modal',
            'type' => 'button',
            'class' => 'btn btn-secondary',
        ]),
    ]) ?>
    <?= $this->Form->end() ?>
<?php endif; ?>
