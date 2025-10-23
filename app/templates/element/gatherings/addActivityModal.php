<?php

/**
 * Add Activity Modal Element
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \App\Model\Entity\GatheringActivity[] $availableActivities
 */
?>
<?= $this->Form->create(null, [
    'id' => 'add_activity_form',
    'type' => 'post',
    'url' => [
        'controller' => 'Gatherings',
        'action' => 'add-activity',
        $gathering->id,
    ],
]) ?>

<?php echo $this->Modal->create(__('Add Activities to {0}', $gathering->name), [
    'id' => 'addActivityModal',
    'close' => true,
    'size' => 'lg'
]); ?>

<div class="mb-3">
    <p class="text-muted">
        <?= __('Select one or more activities to add to this gathering.') ?>
    </p>

    <?php if (!empty($availableActivities)): ?>
        <div class="list-group">
            <?php foreach ($availableActivities as $activity): ?>
                <label class="list-group-item">
                    <div class="d-flex align-items-start">
                        <div class="form-check">
                            <?= $this->Form->checkbox('activity_ids[]', [
                                'value' => $activity->id,
                                'id' => 'modal-activity-' . $activity->id,
                                'class' => 'form-check-input',
                                'hiddenField' => false
                            ]) ?>
                        </div>
                        <div class="ms-2 flex-grow-1">
                            <strong><?= h($activity->name) ?></strong>
                            <?php if (!empty($activity->description)): ?>
                                <div class="text-muted small"><?= h($activity->description) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            <?= __('All available activities have already been added to this gathering.') ?>
        </div>
    <?php endif; ?>
</div>

<?php echo $this->Modal->end([
    $this->Form->button(__('Add Selected Activities'), [
        'class' => 'btn btn-primary',
        'id' => 'add_activity_submit',
        'disabled' => empty($availableActivities)
    ]),
    $this->Form->button(__('Close'), [
        'data-bs-dismiss' => 'modal',
        'type' => 'button',
        'class' => 'btn btn-secondary'
    ]),
]);
?>
<?= $this->Form->end() ?>